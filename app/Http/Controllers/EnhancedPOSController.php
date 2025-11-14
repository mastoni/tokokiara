<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Contact;
use App\Models\Category;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\InventoryTransaction;
use App\Models\CashLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Inertia\Inertia;

class EnhancedPOSController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:pos']);
    }

    /**
     * Display the POS interface
     */
    public function index()
    {
        $user = Auth::user();
        $store = $user->store;

        if (!$store) {
            return redirect()->route('dashboard')
                ->with('error', 'No store assigned to your account.');
        }

        // Get featured products and categories
        $featuredProducts = Product::with(['category', 'brand'])
            ->active()
            ->featured()
            ->where('quantity', '>', 0)
            ->limit(12)
            ->get();

        $categories = Category::active()
            ->withCount(['products' => function($query) {
                $query->active()->where('quantity', '>', 0);
            }])
            ->orderBy('name')
            ->get();

        $customers = Contact::customers()
            ->active()
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email', 'phone', 'balance']);

        // Get store settings
        $storeSettings = $this->getPOSSettings();

        return Inertia::render('POS/Index', [
            'store' => $store,
            'featuredProducts' => $featuredProducts,
            'categories' => $categories,
            'customers' => $customers,
            'settings' => $storeSettings,
            'user' => $user,
        ]);
    }

    /**
     * Search products for POS
     */
    public function searchProducts(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'category_id' => 'nullable|exists:categories,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $storeId = Auth::user()->store_id;
        $query = $request->input('query');
        $limit = $request->input('limit', 20);

        $products = Product::with(['category', 'brand'])
            ->active()
            ->where('quantity', '>', 0)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->when($request->filled('category_id'), function($q) use ($request) {
                $q->byCategory($request->category_id);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'products' => $products,
            'query' => $query,
            'total' => $products->count(),
        ]);
    }

    /**
     * Get product by barcode
     */
    public function getProductByBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        $product = Product::with(['category', 'brand'])
            ->active()
            ->where('barcode', $request->barcode)
            ->first();

        if (!$product) {
            return response()->json([
                'error' => 'Product not found',
                'message' => 'No product found with this barcode'
            ], 404);
        }

        if ($product->quantity <= 0) {
            return response()->json([
                'error' => 'Out of stock',
                'message' => 'Product is currently out of stock'
            ], 400);
        }

        return response()->json([
            'product' => $product,
        ]);
    }

    /**
     * Get products by category
     */
    public function getProductsByCategory(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $storeId = Auth::user()->store_id;
        $limit = $request->input('limit', 50);

        $products = Product::with(['category', 'brand'])
            ->active()
            ->where('quantity', '>', 0)
            ->byCategory($request->category_id)
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'products' => $products,
            'category_id' => $request->category_id,
            'total' => $products->count(),
        ]);
    }

    /**
     * Process a sale
     */
    public function processSale(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:contacts,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,card,bank_transfer,credit,mobile_money',
            'payments.*.amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'staff_note' => 'nullable|string|max:1000',
            'customer_note' => 'nullable|string|max:1000',
            'delivery_address' => 'nullable|string|max:500',
            'delivery_date' => 'nullable|date',
        ]);

        DB::beginTransaction();

        try {
            $user = Auth::user();
            $store = $user->store;
            $items = $request->items;
            $payments = $request->payments;

            // Calculate totals
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;
            $total = 0;

            foreach ($items as $item) {
                $itemTotal = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
                $itemTax = $itemTotal * ($item['tax_rate'] ?? 0);

                $subtotal += $item['quantity'] * $item['unit_price'];
                $totalTax += $itemTax;
                $totalDiscount += $item['discount'] ?? 0;
                $total += $itemTotal + $itemTax;
            }

            $shipping = $request->input('shipping_amount', 0);
            $grandTotal = $total + $shipping;

            // Validate payment amounts
            $totalPayment = collect($payments)->sum('amount');
            if ($totalPayment < $grandTotal && !$request->has('customer_id')) {
                throw new \Exception('Insufficient payment amount for sale without customer credit.');
            }

            // Create sale
            $sale = Sale::create([
                'store_id' => $store->id,
                'contact_id' => $request->customer_id,
                'sale_date' => now(),
                'sale_time' => now(),
                'total_amount' => $subtotal,
                'discount' => $totalDiscount,
                'tax_amount' => $totalTax,
                'shipping_amount' => $shipping,
                'amount_received' => $totalPayment,
                'profit_amount' => $this->calculateProfit($items),
                'status' => $totalPayment >= $grandTotal ? 'completed' : 'pending',
                'payment_status' => $totalPayment >= $grandTotal ? 'paid' : 'partial',
                'note' => $request->notes,
                'staff_note' => $request->staff_note,
                'customer_note' => $request->customer_note,
                'delivery_address' => $request->delivery_address,
                'delivery_date' => $request->delivery_date,
                'created_by' => $user->id,
                'cart_snapshot' => [
                    'items_count' => count($items),
                    'customer_id' => $request->customer_id,
                    'subtotal' => $subtotal,
                    'total' => $grandTotal,
                ],
            ]);

            // Create sale items and update inventory
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Validate stock
                if ($product->is_stock_managed && $product->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->quantity}, Requested: {$item['quantity']}");
                }

                // Create sale item
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $product->cost,
                    'discount' => $item['discount'] ?? 0,
                    'total_price' => ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0),
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'sale_date' => $sale->sale_date,
                    'created_by' => $user->id,
                ]);

                // Update product quantity
                if ($product->is_stock_managed) {
                    $newQuantity = $product->quantity - $item['quantity'];
                    $product->updateStock($newQuantity, 'Sale: ' . $sale->invoice_number);
                }
            }

            // Process payments
            foreach ($payments as $payment) {
                Transaction::create([
                    'sales_id' => $sale->id,
                    'store_id' => $store->id,
                    'contact_id' => $request->customer_id,
                    'transaction_date' => now(),
                    'amount' => $payment['amount'],
                    'payment_method' => $payment['method'],
                    'transaction_type' => 'payment',
                    'note' => $payment['note'] ?? '',
                    'created_by' => $user->id,
                ]);
            }

            // Update customer balance if credit payment
            if ($request->customer_id && $totalPayment < $grandTotal) {
                $balance = $grandTotal - $totalPayment;
                $customer = Contact::findOrFail($request->customer_id);
                $customer->updateBalance($balance, 'Sale: ' . $sale->invoice_number);
            }

            // Add loyalty points if customer exists
            if ($request->customer_id) {
                $customer = Contact::findOrFail($request->customer_id);
                $loyaltyPoints = intval($grandTotal * 0.1); // 1 point per $10 spent
                if ($loyaltyPoints > 0) {
                    $customer->addLoyaltyPoints($loyaltyPoints, 'Purchase: ' . $sale->invoice_number);
                    $sale->update(['loyalty_points_earned' => $loyaltyPoints]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'sale' => $sale->load(['contact', 'saleItems.product']),
                'message' => 'Sale processed successfully!',
                'invoice_url' => route('pos.receipt', $sale->id),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('POS Sale Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error processing sale: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate receipt
     */
    public function generateReceipt(Sale $sale)
    {
        $sale->load(['contact', 'store', 'saleItems.product', 'transactions']);

        return Inertia::render('POS/Receipt', [
            'sale' => $sale,
        ]);
    }

    /**
     * Get today's sales summary
     */
    public function getTodaySales()
    {
        $storeId = Auth::user()->store_id;
        $today = now()->startOfDay();

        $sales = Sale::where('store_id', $storeId)
            ->where('created_at', '>=', $today)
            ->completed();

        return response()->json([
            'total_sales' => $sales->count(),
            'total_revenue' => $sales->sum('total_amount'),
            'total_items_sold' => $sales->withCount('saleItems')->get()->sum('sale_items_count'),
            'average_sale_value' => $sales->count() > 0 ? $sales->sum('total_amount') / $sales->count() : 0,
            'cash_sales' => $sales->whereHas('transactions', function($q) {
                $q->where('payment_method', 'cash');
            })->sum('total_amount'),
            'card_sales' => $sales->whereHas('transactions', function($q) {
                $q->where('payment_method', 'card');
            })->sum('total_amount'),
        ]);
    }

    /**
     * Hold/Save current cart
     */
    public function holdCart(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:contacts,id',
            'items' => 'required|array|min:1',
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $heldCart = [
            'id' => Str::uuid(),
            'name' => $request->name,
            'customer_id' => $request->customer_id,
            'items' => $request->items,
            'notes' => $request->notes,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ];

        // Store in cache or database - for now using session
        session(['held_carts' => array_merge(session('held_carts', []), [$heldCart['id'] => $heldCart])]);

        return response()->json([
            'success' => true,
            'cart_id' => $heldCart['id'],
            'message' => 'Cart held successfully!',
        ]);
    }

    /**
     * Retrieve held cart
     */
    public function getHeldCart($cartId)
    {
        $heldCarts = session('held_carts', []);

        if (!isset($heldCarts[$cartId])) {
            return response()->json([
                'error' => 'Cart not found',
            ], 404);
        }

        return response()->json([
            'cart' => $heldCarts[$cartId],
        ]);
    }

    /**
     * Get all held carts for current user
     */
    public function getHeldCarts()
    {
        $heldCarts = session('held_carts', []);
        $userCarts = array_filter($heldCarts, function($cart) {
            return $cart['created_by'] === Auth::id();
        });

        return response()->json([
            'carts' => array_values($userCarts),
        ]);
    }

    /**
     * Delete held cart
     */
    public function deleteHeldCart($cartId)
    {
        $heldCarts = session('held_carts', []);

        if (isset($heldCarts[$cartId])) {
            unset($heldCarts[$cartId]);
            session(['held_carts' => $heldCarts]);

            return response()->json([
                'success' => true,
                'message' => 'Cart deleted successfully!',
            ]);
        }

        return response()->json([
            'error' => 'Cart not found',
        ], 404);
    }

    /**
     * Quick refund
     */
    public function quickRefund(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'reason' => 'required|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $sale = Sale::findOrFail($request->sale_id);
            $user = Auth::user();

            if (!$sale->canBeRefunded()) {
                throw new \Exception('This sale cannot be refunded.');
            }

            // Create refund sale
            $refundSale = Sale::create([
                'store_id' => $sale->store_id,
                'contact_id' => $sale->contact_id,
                'reference_id' => $sale->id,
                'sale_type' => 'refund',
                'sale_date' => now(),
                'sale_time' => now(),
                'total_amount' => 0, // Will be calculated
                'status' => 'completed',
                'payment_status' => 'refunded',
                'note' => 'Refund for sale: ' . $sale->invoice_number,
                'staff_note' => $request->reason,
                'created_by' => $user->id,
            ]);

            $totalRefund = 0;

            foreach ($request->items as $item) {
                $saleItem = SaleItem::findOrFail($item['sale_item_id']);
                $product = $saleItem->product;

                if ($item['quantity'] > $saleItem->quantity) {
                    throw new \Exception("Cannot refund more than sold quantity for {$product->name}.");
                }

                $refundAmount = ($item['quantity'] * $saleItem->unit_price) -
                               ($item['quantity'] * $saleItem->discount / $saleItem->quantity);

                // Create refund sale item
                SaleItem::create([
                    'sale_id' => $refundSale->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => -$item['quantity'], // Negative for refund
                    'unit_price' => $saleItem->unit_price,
                    'unit_cost' => $saleItem->unit_cost,
                    'discount' => $item['quantity'] * ($saleItem->discount / $saleItem->quantity),
                    'total_price' => -$refundAmount,
                    'sale_date' => $refundSale->sale_date,
                    'created_by' => $user->id,
                ]);

                // Restore stock
                if ($product->is_stock_managed) {
                    $newQuantity = $product->quantity + $item['quantity'];
                    $product->updateStock($newQuantity, 'Refund: ' . $refundSale->invoice_number);
                }

                $totalRefund += $refundAmount;
            }

            // Update refund sale totals
            $refundSale->update([
                'total_amount' => $totalRefund,
                'amount_received' => $totalRefund,
            ]);

            // Create refund transaction
            Transaction::create([
                'sales_id' => $refundSale->id,
                'store_id' => $refundSale->store_id,
                'contact_id' => $refundSale->contact_id,
                'transaction_date' => now(),
                'amount' => -$totalRefund,
                'payment_method' => 'refund',
                'transaction_type' => 'refund',
                'note' => $request->reason,
                'created_by' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'refund_sale' => $refundSale,
                'message' => 'Refund processed successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error processing refund: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get POS settings
     */
    private function getPOSSettings()
    {
        return [
            'default_tax_rate' => Setting::getMiscSettings()['tax_rate'] ?? 0.08,
            'currency' => Setting::getMiscSettings()['currency'] ?? 'USD',
            'decimal_places' => 2,
            'cart_first_focus' => Setting::getMiscSettings()['cart_first_focus'] ?? 'quantity',
            'auto_print_receipt' => Setting::getMiscSettings()['auto_print_receipt'] ?? false,
            'allow_negative_stock' => Setting::getMiscSettings()['allow_negative_stock'] ?? false,
            'require_customer_for_credit' => true,
            'loyalty_points_rate' => 0.1, // 1 point per $10
            'receipt_header' => Setting::getMiscSettings()['receipt_header'] ?? '',
            'receipt_footer' => Setting::getMiscSettings()['receipt_footer'] ?? 'Thank you for your purchase!',
        ];
    }

    /**
     * Calculate profit for sale items
     */
    private function calculateProfit($items)
    {
        $totalProfit = 0;

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $itemProfit = ($item['unit_price'] - $product->cost) * $item['quantity'];
                $totalProfit += $itemProfit;
            }
        }

        return $totalProfit;
    }
}