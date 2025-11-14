<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryItemStore;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class EnhancedInventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:inventory']);
    }

    public function index(Request $request)
    {
        $storeId = $request->input('store_id', Auth::user()->store_id);

        $query = InventoryItem::with(['product', 'stores' => function($query) use ($storeId) {
            $query->where('store_id', $storeId);
        }]);

        // Filter by store
        if ($request->filled('store_id')) {
            $query->whereHas('stores', function($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        // Search
        if ($request->filled('search')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->search($request->search);
            });
        }

        // Filter by stock status
        if ($request->filled('stock_status')) {
            $query->whereHas('stores', function($q) use ($request, $storeId) {
                $q->where('store_id', $storeId)
                   ->where('quantity', $request->stock_status === 'out_of_stock' ? 0 : '>', 0);
            });
        }

        $inventoryItems = $query->paginate(25);
        $stores = Store::active()->orderBy('name')->get();

        return Inertia::render('Inventory/Index', [
            'inventoryItems' => $inventoryItems,
            'stores' => $stores,
            'filters' => $request->only(['store_id', 'search', 'stock_status']),
        ]);
    }

    public function transactions(Request $request)
    {
        $query = InventoryTransaction::with(['product', 'user', 'store'])
            ->byStore(Auth::user()->store_id);

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Filter by transaction type
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(50);
        $products = Product::active()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Inventory/Transactions', [
            'transactions' => $transactions,
            'products' => $products,
            'filters' => $request->only(['start_date', 'end_date', 'transaction_type', 'product_id']),
            'transactionTypes' => [
                'initial_stock' => 'Initial Stock',
                'purchase' => 'Purchase',
                'sale' => 'Sale',
                'return' => 'Return',
                'adjustment' => 'Adjustment',
                'transfer' => 'Transfer',
                'damage' => 'Damage',
                'expiry' => 'Expiry',
            ],
        ]);
    }

    public function adjustStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'store_id' => 'required|exists:stores,id',
            'adjustment_type' => 'required|in:add,subtract,set',
            'quantity' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
            'transaction_type' => 'required|in:adjustment,damage,expiry,transfer',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $product = Product::findOrFail($request->product_id);
            $storeId = $request->store_id;
            $adjustmentType = $request->adjustment_type;
            $adjustmentQuantity = $request->quantity;
            $reason = $request->reason;

            // Get current quantity
            $currentQuantity = $product->quantity;
            $newQuantity = $currentQuantity;

            switch ($adjustmentType) {
                case 'add':
                    $newQuantity = $currentQuantity + $adjustmentQuantity;
                    $change = $adjustmentQuantity;
                    break;
                case 'subtract':
                    $newQuantity = max(0, $currentQuantity - $adjustmentQuantity);
                    $change = -$adjustmentQuantity;
                    break;
                case 'set':
                    $newQuantity = $adjustmentQuantity;
                    $change = $adjustmentQuantity - $currentQuantity;
                    break;
            }

            // Update product quantity
            $product->update(['quantity' => $newQuantity]);

            // Create inventory transaction
            InventoryTransaction::create([
                'product_id' => $product->id,
                'previous_quantity' => $currentQuantity,
                'new_quantity' => $newQuantity,
                'change' => $change,
                'transaction_type' => $request->transaction_type,
                'reason' => $reason,
                'notes' => $request->notes,
                'user_id' => Auth::id(),
                'store_id' => $storeId,
            ]);

            // Update or create inventory item store record
            InventoryItemStore::updateOrCreate(
                [
                    'inventory_item_id' => $product->id,
                    'store_id' => $storeId,
                ],
                [
                    'quantity' => $newQuantity,
                    'last_updated' => now(),
                ]
            );

            DB::commit();

            return redirect()->back()
                ->with('success', "Stock adjusted successfully! {$product->name}: {$currentQuantity} → {$newQuantity}");

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error adjusting stock: ' . $e->getMessage());
        }
    }

    public function transferStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_store_id' => 'required|exists:stores,id',
            'to_store_id' => 'required|exists:stores,id|different:from_store_id',
            'quantity' => 'required|numeric|min:1',
            'reason' => 'required|string|max:255',
        ]);

        if ($request->from_store_id == $request->to_store_id) {
            return redirect()->back()
                ->with('error', 'Source and destination stores cannot be the same.');
        }

        DB::beginTransaction();

        try {
            $product = Product::findOrFail($request->product_id);
            $fromStoreId = $request->from_store_id;
            $toStoreId = $request->to_store_id;
            $transferQuantity = $request->quantity;

            // Check if source store has enough stock
            $fromStoreStock = InventoryItemStore::where('inventory_item_id', $product->id)
                ->where('store_id', $fromStoreId)
                ->value('quantity') ?? 0;

            if ($fromStoreStock < $transferQuantity) {
                throw new \Exception("Insufficient stock in source store. Available: {$fromStoreStock}, Requested: {$transferQuantity}");
            }

            // Get current total quantity
            $currentQuantity = $product->quantity;

            // Update source store stock
            InventoryItemStore::where('inventory_item_id', $product->id)
                ->where('store_id', $fromStoreId)
                ->decrement('quantity', $transferQuantity);

            // Update destination store stock
            InventoryItemStore::updateOrCreate(
                [
                    'inventory_item_id' => $product->id,
                    'store_id' => $toStoreId,
                ],
                [
                    'quantity' => DB::raw("COALESCE(quantity, 0) + {$transferQuantity}"),
                    'last_updated' => now(),
                ]
            );

            // Create transfer transaction for source store
            InventoryTransaction::create([
                'product_id' => $product->id,
                'previous_quantity' => $currentQuantity,
                'new_quantity' => $currentQuantity, // Total quantity remains the same
                'change' => -$transferQuantity,
                'transaction_type' => 'transfer_out',
                'reason' => "Transfer to Store #{$toStoreId}: {$request->reason}",
                'user_id' => Auth::id(),
                'store_id' => $fromStoreId,
                'metadata' => [
                    'to_store_id' => $toStoreId,
                    'transfer_quantity' => $transferQuantity,
                ],
            ]);

            // Create transfer transaction for destination store
            InventoryTransaction::create([
                'product_id' => $product->id,
                'previous_quantity' => 0,
                'new_quantity' => $transferQuantity,
                'change' => $transferQuantity,
                'transaction_type' => 'transfer_in',
                'reason' => "Transfer from Store #{$fromStoreId}: {$request->reason}",
                'user_id' => Auth::id(),
                'store_id' => $toStoreId,
                'metadata' => [
                    'from_store_id' => $fromStoreId,
                    'transfer_quantity' => $transferQuantity,
                ],
            ]);

            DB::commit();

            return redirect()->back()
                ->with('success', "Stock transfer completed! {$transferQuantity} units of {$product->name} transferred from Store #{$fromStoreId} to Store #{$toStoreId}");

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error transferring stock: ' . $e->getMessage());
        }
    }

    public function bulkAdjustment(Request $request)
    {
        $request->validate([
            'adjustments' => 'required|array|min:1',
            'adjustments.*.product_id' => 'required|exists:products,id',
            'adjustments.*.quantity' => 'required|numeric',
            'adjustments.*.reason' => 'required|string',
            'transaction_type' => 'required|in:adjustment,damage,expiry',
        ]);

        DB::beginTransaction();

        try {
            $successCount = 0;
            $adjustments = $request->adjustments;

            foreach ($adjustments as $adjustment) {
                $product = Product::findOrFail($adjustment['product_id']);
                $newQuantity = $adjustment['quantity'];
                $currentQuantity = $product->quantity;
                $change = $newQuantity - $currentQuantity;

                if ($change == 0) {
                    continue; // Skip if no change
                }

                // Update product quantity
                $product->update(['quantity' => $newQuantity]);

                // Create inventory transaction
                InventoryTransaction::create([
                    'product_id' => $product->id,
                    'previous_quantity' => $currentQuantity,
                    'new_quantity' => $newQuantity,
                    'change' => $change,
                    'transaction_type' => $request->transaction_type,
                    'reason' => $adjustment['reason'],
                    'user_id' => Auth::id(),
                    'store_id' => Auth::user()->store_id,
                ]);

                $successCount++;
            }

            DB::commit();

            return redirect()->back()
                ->with('success', "Bulk adjustment completed! {$successCount} products updated successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error in bulk adjustment: ' . $e->getMessage());
        }
    }

    public function stockReport(Request $request)
    {
        $storeId = $request->input('store_id', Auth::user()->store_id);

        $inventoryItems = InventoryItem::with(['product', 'stores' => function($query) use ($storeId) {
            $query->where('store_id', $storeId);
        }])
        ->whereHas('stores', function($query) use ($storeId) {
            $query->where('store_id', $storeId);
        })
        ->get();

        $report = [
            'summary' => [
                'total_items' => $inventoryItems->count(),
                'total_quantity' => $inventoryItems->sum(fn($item) => $item->stores->first()?->quantity ?? 0),
                'total_value' => $inventoryItems->sum(fn($item) =>
                    ($item->stores->first()?->quantity ?? 0) * ($item->product?->cost ?? 0)
                ),
                'low_stock_items' => $inventoryItems->filter(fn($item) =>
                    ($item->stores->first()?->quantity ?? 0) <= ($item->product?->alert_quantity ?? 0)
                )->count(),
                'out_of_stock_items' => $inventoryItems->filter(fn($item) =>
                    ($item->stores->first()?->quantity ?? 0) == 0
                )->count(),
            ],
            'categories' => $inventoryItems->groupBy(fn($item) => $item->product?->category?->name ?? 'Uncategorized')
                ->map(fn($items, $category) => [
                    'category' => $category,
                    'count' => $items->count(),
                    'total_quantity' => $items->sum(fn($item) => $item->stores->first()?->quantity ?? 0),
                    'total_value' => $items->sum(fn($item) =>
                        ($item->stores->first()?->quantity ?? 0) * ($item->product?->cost ?? 0)
                    ),
                ])
                ->values(),
            'low_stock_items' => $inventoryItems->filter(fn($item) =>
                ($item->stores->first()?->quantity ?? 0) <= ($item->product?->alert_quantity ?? 0)
            )
            ->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->product?->name,
                'sku' => $item->product?->sku,
                'current_quantity' => $item->stores->first()?->quantity ?? 0,
                'alert_quantity' => $item->product?->alert_quantity ?? 0,
                'value' => ($item->stores->first()?->quantity ?? 0) * ($item->product?->cost ?? 0),
            ])
            ->values(),
        ];

        return Inertia::render('Inventory/Report', [
            'report' => $report,
            'storeId' => $storeId,
            'stores' => Store::active()->orderBy('name')->get(),
        ]);
    }

    public function valuationReport(Request $request)
    {
        $storeId = $request->input('store_id', Auth::user()->store_id);

        $inventoryItems = InventoryItem::with(['product.category', 'product.brand', 'stores' => function($query) use ($storeId) {
            $query->where('store_id', $storeId);
        }])
        ->whereHas('stores', function($query) use ($storeId) {
            $query->where('store_id', $storeId);
        })
        ->get();

        $valuation = $inventoryItems->map(fn($item) => [
            'id' => $item->id,
            'name' => $item->product?->name,
            'sku' => $item->product?->sku,
            'category' => $item->product?->category?->name,
            'brand' => $item->product?->brand?->name,
            'quantity' => $item->stores->first()?->quantity ?? 0,
            'cost_price' => $item->product?->cost,
            'sale_price' => $item->product?->price,
            'total_cost_value' => ($item->stores->first()?->quantity ?? 0) * ($item->product?->cost ?? 0),
            'total_sale_value' => ($item->stores->first()?->quantity ?? 0) * ($item->product?->price ?? 0),
            'potential_profit' => ($item->stores->first()?->quantity ?? 0) * (($item->product?->price ?? 0) - ($item->product?->cost ?? 0)),
        ]);

        $summary = [
            'total_cost_value' => $valuation->sum('total_cost_value'),
            'total_sale_value' => $valuation->sum('total_sale_value'),
            'potential_profit' => $valuation->sum('potential_profit'),
            'total_items' => $valuation->sum('quantity'),
        ];

        return Inertia::render('Inventory/Valuation', [
            'valuation' => $valuation,
            'summary' => $summary,
            'storeId' => $storeId,
            'stores' => Store::active()->orderBy('name')->get(),
        ]);
    }

    // API endpoints for React components
    public function apiStockLevels(Request $request)
    {
        $storeId = $request->input('store_id', Auth::user()->store_id);

        $products = Product::with(['category', 'brand'])
            ->where('is_stock_managed', true)
            ->where('is_active', true)
            ->byStore($storeId)
            ->get()
            ->map(fn($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'quantity' => $product->quantity,
                'alert_quantity' => $product->alert_quantity,
                'stock_status' => $product->stock_status,
                'category' => $product->category?->name,
                'brand' => $product->brand?->name,
                'cost' => $product->cost,
                'price' => $product->price,
            ]);

        return response()->json($products);
    }

    public function apiLowStockAlerts()
    {
        $products = Product::with(['category', 'brand'])
            ->byStore(Auth::user()->store_id)
            ->lowStock()
            ->active()
            ->get();

        return response()->json($products);
    }

    public function apiRecentTransactions(Request $request)
    {
        $limit = min($request->input('limit', 10), 50);

        $transactions = InventoryTransaction::with(['product', 'user'])
            ->byStore(Auth::user()->store_id)
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json($transactions);
    }
}