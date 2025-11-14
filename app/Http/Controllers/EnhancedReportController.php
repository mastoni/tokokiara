<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class EnhancedReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:reports']);
    }

    /**
     * Display analytics dashboard
     */
    public function dashboard(Request $request)
    {
        $storeId = $request->input('store_id', Auth::user()->store_id);
        $period = $request->input('period', '30days');

        $dateRange = $this->getDateRange($period);

        // Get key metrics
        $metrics = $this->getDashboardMetrics($storeId, $dateRange);

        // Get sales trends
        $salesTrends = $this->getSalesTrends($storeId, $dateRange);

        // Get top products
        $topProducts = $this->getTopProducts($storeId, $dateRange);

        // Get low stock alerts
        $lowStockAlerts = $this->getLowStockAlerts($storeId);

        // Get recent transactions
        $recentTransactions = $this->getRecentTransactions($storeId);

        // Get customer analytics
        $customerAnalytics = $this->getCustomerAnalytics($storeId, $dateRange);

        return Inertia::render('Reports/Dashboard', [
            'metrics' => $metrics,
            'salesTrends' => $salesTrends,
            'topProducts' => $topProducts,
            'lowStockAlerts' => $lowStockAlerts,
            'recentTransactions' => $recentTransactions,
            'customerAnalytics' => $customerAnalytics,
            'period' => $period,
            'storeId' => $storeId,
            'stores' => Store::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Sales report with detailed analytics
     */
    public function salesReport(Request $request)
    {
        $storeId = $request->input('store_id', Auth::user()->store_id);
        $period = $request->input('period', '30days');
        $groupBy = $request->input('group_by', 'day');

        $dateRange = $this->getDateRange($period);

        $query = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->with(['contact', 'store']);

        if ($storeId && $storeId !== 'all') {
            $query->where('store_id', $storeId);
        }

        // Apply additional filters
        if ($request->filled('customer_id')) {
            $query->where('contact_id', $request->customer_id);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('min_amount')) {
            $query->where('total_amount', '>=', $request->min_amount);
        }

        if ($request->filled('max_amount')) {
            $query->where('total_amount', '<=', $request->max_amount);
        }

        // Group by period
        switch ($groupBy) {
            case 'hour':
                $salesData = $query->selectRaw('
                    HOUR(sale_time) as period,
                    COUNT(*) as total_sales,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_sale_value,
                    SUM(profit_amount) as total_profit
                ')->groupBy('period')->orderBy('period')->get();
                break;

            case 'day':
                $salesData = $query->selectRaw('
                    DATE(sale_date) as period,
                    COUNT(*) as total_sales,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_sale_value,
                    SUM(profit_amount) as total_profit
                ')->groupBy('period')->orderBy('period')->get();
                break;

            case 'week':
                $salesData = $query->selectRaw('
                    YEARWEEK(sale_date) as period,
                    COUNT(*) as total_sales,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_sale_value,
                    SUM(profit_amount) as total_profit
                ')->groupBy('period')->orderBy('period')->get();
                break;

            case 'month':
            default:
                $salesData = $query->selectRaw('
                    DATE_FORMAT(sale_date, "%Y-%m") as period,
                    COUNT(*) as total_sales,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_sale_value,
                    SUM(profit_amount) as total_profit
                ')->groupBy('period')->orderBy('period')->get();
                break;
        }

        // Get sales breakdown by payment method
        $paymentMethods = $query->join('transactions', 'sales.id', '=', 'transactions.sales_id')
            ->selectRaw('
                transactions.payment_method,
                COUNT(DISTINCT sales.id) as total_sales,
                SUM(transactions.amount) as total_amount
            ')
            ->groupBy('transactions.payment_method')
            ->get();

        // Get sales by customer segment
        $customerSegments = $this->getSalesByCustomerSegment($storeId, $dateRange);

        return Inertia::render('Reports/Sales', [
            'salesData' => $salesData,
            'paymentMethods' => $paymentMethods,
            'customerSegments' => $customerSegments,
            'summary' => [
                'total_sales' => $salesData->sum('total_sales'),
                'total_revenue' => $salesData->sum('total_revenue'),
                'avg_sale_value' => $salesData->avg('avg_sale_value'),
                'total_profit' => $salesData->sum('total_profit'),
                'profit_margin' => $salesData->sum('total_revenue') > 0 ?
                    ($salesData->sum('total_profit') / $salesData->sum('total_revenue')) * 100 : 0,
            ],
            'filters' => $request->only(['period', 'store_id', 'group_by', 'customer_id', 'payment_status', 'min_amount', 'max_amount']),
            'stores' => Store::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Inventory report with valuation
     */
    public function inventoryReport(Request $request)
    {
        $storeId = $request->input('store_id', Auth::user()->store_id);
        $includeInactive = $request->boolean('include_inactive', false);

        $query = Product::with(['category', 'brand']);

        if (!$includeInactive) {
            $query->active();
        }

        if ($storeId && $storeId !== 'all') {
            $query->where('store_id', $storeId);
        }

        // Get inventory valuation
        $products = $query->get()->map(function($product) {
            $quantity = $product->quantity;
            $costValue = $quantity * $product->cost;
            $saleValue = $quantity * $product->price;
            $profit = $saleValue - $costValue;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'category' => $product->category?->name,
                'brand' => $product->brand?->name,
                'quantity' => $quantity,
                'cost_price' => $product->cost,
                'sale_price' => $product->price,
                'cost_value' => $costValue,
                'sale_value' => $saleValue,
                'potential_profit' => $profit,
                'profit_margin' => $saleValue > 0 ? ($profit / $saleValue) * 100 : 0,
                'stock_status' => $product->stock_status,
                'last_updated' => $product->updated_at,
            ];
        });

        // Category-wise breakdown
        $categoryBreakdown = $products->groupBy('category')
            ->map(function($items, $category) {
                return [
                    'category' => $category,
                    'products_count' => $items->count(),
                    'total_quantity' => $items->sum('quantity'),
                    'total_cost_value' => $items->sum('cost_value'),
                    'total_sale_value' => $items->sum('sale_value'),
                    'total_profit' => $items->sum('potential_profit'),
                ];
            })->values();

        // Summary statistics
        $summary = [
            'total_products' => $products->count(),
            'total_quantity' => $products->sum('quantity'),
            'total_cost_value' => $products->sum('cost_value'),
            'total_sale_value' => $products->sum('sale_value'),
            'total_potential_profit' => $products->sum('potential_profit'),
            'average_profit_margin' => $products->avg('profit_margin'),
            'low_stock_count' => $products->where('stock_status', 'Low Stock')->count(),
            'out_of_stock_count' => $products->where('quantity', 0)->count(),
        ];

        return Inertia::render('Reports/Inventory', [
            'products' => $products->sortBy('name')->values(),
            'categoryBreakdown' => $categoryBreakdown,
            'summary' => $summary,
            'filters' => $request->only(['store_id', 'include_inactive']),
            'stores' => Store::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Financial report with P&L analysis
     */
    public function financialReport(Request $request)
    {
        $storeId = $request->input('store_id', Auth::user()->store_id);
        $period = $request->input('period', '30days');
        $dateRange = $this->getDateRange($period);

        // Get revenue data
        $revenue = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                SUM(total_amount) as total_sales,
                SUM(profit_amount) as total_profit,
                SUM(discount) as total_discount,
                SUM(tax_amount) as total_tax,
                COUNT(*) as total_transactions
            ')
            ->first();

        // Get cost of goods sold (COGS)
        $cogs = Sale::completed()
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('sales.store_id', $storeId);
            })
            ->selectRaw('
                SUM(sale_items.quantity * sale_items.unit_cost) as total_cogs
            ')
            ->first();

        // Get expenses
        $expenses = Expense::whereBetween('expense_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                SUM(amount) as total_expenses,
                COUNT(*) as total_expense_transactions
            ')
            ->first();

        // Calculate financial metrics
        $totalRevenue = $revenue->total_sales ?? 0;
        $grossProfit = $revenue->total_profit ?? 0;
        $totalCOGS = $cogs->total_cogs ?? 0;
        $totalExpenses = $expenses->total_expenses ?? 0;
        $netProfit = $grossProfit - $totalExpenses;

        // Monthly trend
        $monthlyTrend = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                DATE_FORMAT(sale_date, "%Y-%m") as month,
                SUM(total_amount) as revenue,
                SUM(profit_amount) as profit,
                COUNT(*) as transactions
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Expense breakdown
        $expenseBreakdown = Expense::whereBetween('expense_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                category,
                SUM(amount) as total,
                COUNT(*) as count
            ')
            ->groupBy('category')
            ->orderBy('total', 'desc')
            ->get();

        $profitLoss = [
            'revenue' => $totalRevenue,
            'cogs' => $totalCOGS,
            'gross_profit' => $grossProfit,
            'expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'gross_profit_margin' => $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0,
            'net_profit_margin' => $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0,
        ];

        return Inertia::render('Reports/Financial', [
            'profitLoss' => $profitLoss,
            'monthlyTrend' => $monthlyTrend,
            'expenseBreakdown' => $expenseBreakdown,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'period' => $period,
            'filters' => $request->only(['period', 'store_id']),
            'stores' => Store::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Customer analytics report
     */
    public function customerReport(Request $request)
    {
        $storeId = $request->input('store_id', Auth::user()->store_id);
        $period = $request->input('period', '30days');
        $dateRange = $this->getDateRange($period);

        // Get customer metrics
        $totalCustomers = Contact::customers()
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        $activeCustomers = Contact::customers()
            ->whereHas('sales', function($query) use ($dateRange) {
                $query->completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']]);
            })
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        // Customer acquisition
        $newCustomers = Contact::customers()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        // Top customers by revenue
        $topCustomers = Contact::customers()
            ->with(['sales' => function($query) use ($dateRange) {
                $query->completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']]);
            }])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->get()
            ->map(function($customer) use ($dateRange) {
                $revenue = $customer->sales()
                    ->completed()
                    ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
                    ->sum('total_amount');

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'revenue' => $revenue,
                    'orders' => $customer->sales()->completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])->count(),
                    'avg_order_value' => $revenue / max(1, $customer->sales()->completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])->count()),
                    'loyalty_points' => $customer->loyalty_points,
                ];
            })
            ->sortByDesc('revenue')
            ->take(20)
            ->values();

        // Customer segmentation
        $customerSegments = $this->getCustomerSegmentation($storeId);

        // Customer retention
        $retention = $this->getCustomerRetention($storeId, $dateRange);

        return Inertia::render('Reports/Customer', [
            'metrics' => [
                'total_customers' => $totalCustomers,
                'active_customers' => $activeCustomers,
                'new_customers' => $newCustomers,
                'retention_rate' => $retention['retention_rate'] ?? 0,
            ],
            'topCustomers' => $topCustomers,
            'segments' => $customerSegments,
            'retention' => $retention,
            'period' => $period,
            'filters' => $request->only(['period', 'store_id']),
            'stores' => Store::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Export report data
     */
    public function exportReport(Request $request)
    {
        $type = $request->input('type', 'sales');
        $format = $request->input('format', 'csv');
        $period = $request->input('period', '30days');

        $dateRange = $this->getDateRange($period);

        switch ($type) {
            case 'sales':
                $data = $this->getSalesExportData($dateRange);
                break;

            case 'inventory':
                $data = $this->getInventoryExportData();
                break;

            case 'customers':
                $data = $this->getCustomersExportData($dateRange);
                break;

            case 'financial':
                $data = $this->getFinancialExportData($dateRange);
                break;

            default:
                return response()->json(['error' => 'Invalid report type'], 400);
        }

        if ($format === 'csv') {
            return $this->exportToCSV($data, $type);
        } elseif ($format === 'excel') {
            return $this->exportToExcel($data, $type);
        }

        return response()->json($data);
    }

    /**
     * Get dashboard metrics
     */
    private function getDashboardMetrics($storeId, $dateRange)
    {
        // Sales metrics
        $salesQuery = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            });

        $salesMetrics = $salesQuery->selectRaw('
            COUNT(*) as total_sales,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_sale_value,
            SUM(profit_amount) as total_profit
        ')->first();

        // Previous period comparison
        $previousRange = [
            'start' => $dateRange['start']->copy()->subDays($dateRange['start']->diffInDays($dateRange['end'])),
            'end' => $dateRange['start']->copy()->subDay(),
        ];

        $previousSales = Sale::completed()
            ->whereBetween('sale_date', [$previousRange['start'], $previousRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->sum('total_amount');

        $salesGrowth = $previousSales > 0 ?
            (($salesMetrics->total_revenue - $previousSales) / $previousSales) * 100 : 0;

        // Customer metrics
        $activeCustomers = Contact::customers()
            ->whereHas('sales', function($query) use ($dateRange) {
                $query->completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']]);
            })
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        // Inventory metrics
        $lowStockCount = Product::where('is_stock_managed', true)
            ->whereRaw('quantity <= alert_quantity')
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        return [
            'total_sales' => $salesMetrics->total_sales ?? 0,
            'total_revenue' => $salesMetrics->total_revenue ?? 0,
            'avg_sale_value' => $salesMetrics->avg_sale_value ?? 0,
            'total_profit' => $salesMetrics->total_profit ?? 0,
            'sales_growth' => $salesGrowth,
            'active_customers' => $activeCustomers,
            'low_stock_count' => $lowStockCount,
        ];
    }

    /**
     * Get sales trends data
     */
    private function getSalesTrends($storeId, $dateRange)
    {
        return Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                DATE(sale_date) as date,
                SUM(total_amount) as revenue,
                COUNT(*) as sales
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get top products by revenue
     */
    private function getTopProducts($storeId, $dateRange)
    {
        return SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('sales.store_id', $storeId);
            })
            ->selectRaw('
                products.name,
                SUM(sale_items.quantity) as total_quantity,
                SUM(sale_items.total_price) as total_revenue
            ')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Get low stock alerts
     */
    private function getLowStockAlerts($storeId)
    {
        return Product::where('is_stock_managed', true)
            ->whereRaw('quantity <= alert_quantity')
            ->where('quantity', '>', 0)
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->with(['category'])
            ->orderBy('quantity', 'asc')
            ->limit(10)
            ->get(['id', 'name', 'quantity', 'alert_quantity', 'category_id']);
    }

    /**
     * Get recent transactions
     */
    private function getRecentTransactions($storeId)
    {
        return Transaction::with(['contact', 'sale'])
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->latest()
            ->limit(10)
            ->get();
    }

    /**
     * Get customer analytics
     */
    private function getCustomerAnalytics($storeId, $dateRange)
    {
        $totalCustomers = Contact::customers()
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        $activeCustomers = Contact::customers()
            ->whereHas('sales', function($query) use ($dateRange) {
                $query->completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']]);
            })
            ->when($storeId && $storeId !== 'all', function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        return [
            'total' => $totalCustomers,
            'active' => $activeCustomers,
            'activity_rate' => $totalCustomers > 0 ? ($activeCustomers / $totalCustomers) * 100 : 0,
        ];
    }

    /**
     * Helper method to get date range
     */
    private function getDateRange($period)
    {
        $endDate = now();

        switch ($period) {
            case '7days':
                $startDate = $endDate->copy()->subDays(7);
                break;
            case '30days':
                $startDate = $endDate->copy()->subDays(30);
                break;
            case '90days':
                $startDate = $endDate->copy()->subDays(90);
                break;
            case '1year':
                $startDate = $endDate->copy()->subYear();
                break;
            case 'this_month':
                $startDate = $endDate->copy()->startOfMonth();
                break;
            case 'last_month':
                $startDate = $endDate->copy()->subMonth()->startOfMonth();
                $endDate = $endDate->copy()->subMonth()->endOfMonth();
                break;
            case 'this_year':
                $startDate = $endDate->copy()->startOfYear();
                break;
            default:
                $startDate = $endDate->copy()->subDays(30);
                break;
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    // Additional helper methods would be implemented here
    private function getSalesByCustomerSegment($storeId, $dateRange) { /* ... */ }
    private function getCustomerSegmentation($storeId) { /* ... */ }
    private function getCustomerRetention($storeId, $dateRange) { /* ... */ }
    private function getSalesExportData($dateRange) { /* ... */ }
    private function getInventoryExportData() { /* ... */ }
    private function getCustomersExportData($dateRange) { /* ... */ }
    private function getFinancialExportData($dateRange) { /* ... */ }
    private function exportToCSV($data, $type) { /* ... */ }
    private function exportToExcel($data, $type) { /* ... */ }
}