<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Transaction;
use App\Models\LoyaltyPointTransaction;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class EnhancedContactController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:customers,vendors']);
    }

    /**
     * Display contacts listing
     */
    public function index(Request $request, $type = 'customer')
    {
        $query = Contact::byStore(Auth::user()->store_id);

        // Filter by type
        if ($type === 'customer') {
            $query->customers();
        } elseif ($type === 'vendor') {
            $query->vendors();
        } else {
            return redirect()->route('contacts.index', 'customer')
                ->with('error', 'Invalid contact type');
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Filter by balance status
        if ($request->filled('balance_status')) {
            switch ($request->balance_status) {
                case 'has_balance':
                    $query->where('balance', '!=', 0);
                    break;
                case 'positive_balance':
                    $query->where('balance', '>', 0);
                    break;
                case 'negative_balance':
                    $query->where('balance', '<', 0);
                    break;
                case 'no_balance':
                    $query->where('balance', 0);
                    break;
                case 'over_limit':
                    $query->whereRaw('ABS(balance) > credit_limit');
                    break;
            }
        }

        // Filter by loyalty points
        if ($request->filled('loyalty_points')) {
            if ($request->loyalty_points === 'has_points') {
                $query->where('loyalty_points', '>', 0);
            } elseif ($request->loyalty_points === 'no_points') {
                $query->where('loyalty_points', 0);
            }
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $contacts = $query->withCount(['sales' => function($q) {
                $q->where('status', 'completed');
            }])
            ->orderBy('name')
            ->paginate(25);

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'type' => $type,
            'filters' => $request->only(['search', 'balance_status', 'loyalty_points', 'is_active']),
        ]);
    }

    /**
     * Show contact details
     */
    public function show(Contact $contact)
    {
        $contact->load([
            'sales' => function($query) {
                $query->completed()->withCount('saleItems')->latest()->limit(10);
            },
            'purchases' => function($query) {
                $query->latest()->limit(5);
            },
            'loyaltyPointTransactions' => function($query) {
                $query->latest()->limit(10);
            },
            'transactions' => function($query) {
                $query->latest()->limit(10);
            },
        ]);

        // Calculate statistics
        $totalSales = $contact->sales()->completed()->sum('total_amount');
        $totalPurchases = $contact->purchases()->sum('total_amount');
        $averageOrderValue = $contact->sales()->completed()->avg('total_amount') ?? 0;
        $totalOrders = $contact->sales()->completed()->count();

        // Get recent activity
        $recentSales = $contact->sales()
            ->completed()
            ->with('saleItems.product')
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'statistics' => [
                'total_sales' => $totalSales,
                'total_purchases' => $totalPurchases,
                'average_order_value' => $averageOrderValue,
                'total_orders' => $totalOrders,
                'first_purchase_date' => $contact->sales()->min('sale_date'),
                'last_purchase_date' => $contact->sales()->max('sale_date'),
            ],
            'recentSales' => $recentSales,
        ]);
    }

    /**
     * Show create form
     */
    public function create(Request $request)
    {
        $type = $request->input('type', 'customer');

        return Inertia::render('Contacts/Create', [
            'type' => $type,
        ]);
    }

    /**
     * Store new contact
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:customer,vendor',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_number' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'balance' => 'nullable|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $contact = Contact::create([
            'type' => $request->type,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'whatsapp' => $request->whatsapp,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'tax_number' => $request->tax_number,
            'company_name' => $request->company_name,
            'balance' => $request->balance ?? 0,
            'loyalty_points' => 0,
            'credit_limit' => $request->credit_limit,
            'payment_terms' => $request->payment_terms,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', true),
            'store_id' => Auth::user()->store_id,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('contacts.show', $contact)
            ->with('success', ucfirst($request->type) . ' created successfully!');
    }

    /**
     * Show edit form
     */
    public function edit(Contact $contact)
    {
        return Inertia::render('Contacts/Edit', [
            'contact' => $contact,
        ]);
    }

    /**
     * Update contact
     */
    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'type' => 'required|in:customer,vendor',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_number' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $contact->update([
            'type' => $request->type,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'whatsapp' => $request->whatsapp,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'tax_number' => $request->tax_number,
            'company_name' => $request->company_name,
            'credit_limit' => $request->credit_limit,
            'payment_terms' => $request->payment_terms,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active'),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('contacts.show', $contact)
            ->with('success', ucfirst($request->type) . ' updated successfully!');
    }

    /**
     * Delete contact
     */
    public function destroy(Contact $contact)
    {
        // Check if contact has transactions
        if ($contact->sales()->exists() || $contact->purchases()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete contact with transaction history. Consider deactivating instead.');
        }

        $type = $contact->type;
        $contact->delete();

        return redirect()->route('contacts.index', 'customer')
            ->with('success', ucfirst($type) . ' deleted successfully!');
    }

    /**
     * Get contact purchase history
     */
    public function getPurchaseHistory(Contact $contact, Request $request)
    {
        $query = $contact->sales()->completed()->with('saleItems.product');

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        $sales = $query->orderBy('sale_date', 'desc')->paginate(20);

        return response()->json($sales);
    }

    /**
     * Adjust contact balance
     */
    public function adjustBalance(Request $request, Contact $contact)
    {
        $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'reason' => 'required|string|max:255',
            'type' => 'required|in:credit,debit,payment,refund',
        ]);

        DB::beginTransaction();

        try {
            $previousBalance = $contact->balance;
            $contact->updateBalance($request->amount, $request->reason, Auth::id());

            // Create transaction record
            Transaction::create([
                'contact_id' => $contact->id,
                'store_id' => Auth::user()->store_id,
                'transaction_date' => now(),
                'amount' => $request->amount,
                'payment_method' => 'adjustment',
                'transaction_type' => $request->type,
                'note' => $request->reason,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->back()
                ->with('success', 'Balance adjusted successfully! ' .
                    'Previous: ' . number_format($previousBalance, 2) .
                    ' → New: ' . number_format($contact->balance, 2));

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error adjusting balance: ' . $e->getMessage());
        }
    }

    /**
     * Add loyalty points
     */
    public function addLoyaltyPoints(Request $request, Contact $contact)
    {
        $request->validate([
            'points' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);

        $contact->addLoyaltyPoints($request->points, $request->reason);

        return redirect()->back()
            ->with('success', "Added {$request->points} loyalty points to {$contact->name}");
    }

    /**
     * Redeem loyalty points
     */
    public function redeemLoyaltyPoints(Request $request, Contact $contact)
    {
        $request->validate([
            'points' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);

        if ($contact->redeemLoyaltyPoints($request->points, $request->reason)) {
            return redirect()->back()
                ->with('success', "Redeemed {$request->points} loyalty points from {$contact->name}");
        }

        return redirect()->back()
            ->with('error', 'Insufficient loyalty points for redemption');
    }

    /**
     * Get contact statistics
     */
    public function getStatistics(Contact $contact)
    {
        $salesData = $contact->sales()->completed()
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(total_amount) as total_sales,
                AVG(total_amount) as avg_order_value,
                MIN(sale_date) as first_order,
                MAX(sale_date) as last_order
            ')
            ->first();

        // Monthly sales trend
        $monthlySales = $contact->sales()
            ->completed()
            ->selectRaw('DATE_FORMAT(sale_date, "%Y-%m") as month, SUM(total_amount) as total')
            ->where('sale_date', '>=', now()->subYear())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top purchased products
        $topProducts = $contact->sales()
            ->completed()
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('
                products.name,
                SUM(sale_items.quantity) as total_quantity,
                SUM(sale_items.total_price) as total_spent
            ')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'overview' => $salesData,
            'monthly_trend' => $monthlySales,
            'top_products' => $topProducts,
            'loyalty_stats' => [
                'current_points' => $contact->loyalty_points,
                'total_earned' => $contact->loyaltyPointTransactions()
                    ->where('transaction_type', 'earned')
                    ->sum('points'),
                'total_redeemed' => $contact->loyaltyPointTransactions()
                    ->where('transaction_type', 'redeemed')
                    ->sum('points'),
            ],
        ]);
    }

    /**
     * Search contacts for autocomplete
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'type' => 'nullable|in:customer,vendor',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Contact::active()
            ->byStore(Auth::user()->store_id)
            ->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->query}%")
                  ->orWhere('email', 'like', "%{$request->query}%")
                  ->orWhere('phone', 'like', "%{$request->query}%")
                  ->orWhere('company_name', 'like', "%{$request->query}%");
            });

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $contacts = $query->limit($request->input('limit', 20))
            ->get(['id', 'name', 'email', 'phone', 'company_name', 'balance', 'type']);

        return response()->json($contacts);
    }

    /**
     * Get contact balance summary
     */
    public function getBalanceSummary(Request $request)
    {
        $storeId = Auth::user()->store_id;

        $customers = Contact::customers()
            ->byStore($storeId)
            ->selectRaw('
                COUNT(*) as total_customers,
                SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) as total_credit,
                SUM(CASE WHEN balance < 0 THEN ABS(balance) ELSE 0 END) as total_debit,
                COUNT(CASE WHEN balance != 0 THEN 1 END) as customers_with_balance
            ')
            ->first();

        $vendors = Contact::vendors()
            ->byStore($storeId)
            ->selectRaw('
                COUNT(*) as total_vendors,
                SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) as total_payable,
                SUM(CASE WHEN balance < 0 THEN ABS(balance) ELSE 0 END) as total_receivable
            ')
            ->first();

        return response()->json([
            'customers' => $customers,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Bulk operations on contacts
     */
    public function bulkOperation(Request $request)
    {
        $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'exists:contacts,id',
            'operation' => 'required|in:activate,deactivate,delete,add_points,remove_points',
            'value' => 'required_if:operation,add_points,remove_points|integer|min:1',
            'reason' => 'required_if:operation,add_points,remove_points|string|max:255',
        ]);

        $contacts = Contact::whereIn('id', $request->contact_ids);
        $operation = $request->operation;

        switch ($operation) {
            case 'activate':
                $contacts->update(['is_active' => true]);
                $message = 'Contacts activated successfully';
                break;

            case 'deactivate':
                $contacts->update(['is_active' => false]);
                $message = 'Contacts deactivated successfully';
                break;

            case 'delete':
                // Check for transactions before deleting
                $contactsWithTransactions = $contacts->whereHas('sales')->orWhereHas('purchases')->count();
                if ($contactsWithTransactions > 0) {
                    return redirect()->back()
                        ->with('error', 'Cannot delete contacts with transaction history');
                }
                $contacts->delete();
                $message = 'Contacts deleted successfully';
                break;

            case 'add_points':
                foreach ($contacts->get() as $contact) {
                    $contact->addLoyaltyPoints($request->value, $request->reason);
                }
                $message = "Added {$request->value} loyalty points to selected contacts";
                break;

            case 'remove_points':
                foreach ($contacts->get() as $contact) {
                    $contact->redeemLoyaltyPoints($request->value, $request->reason);
                }
                $message = "Removed {$request->value} loyalty points from selected contacts";
                break;
        }

        return redirect()->back()
            ->with('success', $message);
    }

    // API endpoints for React components
    public function apiIndex(Request $request)
    {
        $query = Contact::active()->byStore(Auth::user()->store_id);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $contacts = $query->orderBy('name')->limit(50)->get();

        return response()->json($contacts);
    }

    public function apiShow(Contact $contact)
    {
        $contact->load(['sales' => function($query) {
            $query->completed()->latest()->limit(5);
        }]);

        return response()->json($contact);
    }
}