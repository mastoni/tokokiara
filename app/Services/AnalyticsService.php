<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Generate comprehensive business analytics
     */
    public function generateBusinessAnalytics($period = '30days', $storeId = null)
    {
        $dateRange = $this->getDateRange($period);

        return [
            'overview' => $this->getBusinessOverview($dateRange, $storeId),
            'sales_analytics' => $this->getSalesAnalytics($dateRange, $storeId),
            'inventory_analytics' => $this->getInventoryAnalytics($storeId),
            'customer_analytics' => $this->getCustomerAnalyticsData($dateRange, $storeId),
            'financial_analytics' => $this->getFinancialAnalytics($dateRange, $storeId),
            'operational_analytics' => $this->getOperationalAnalytics($dateRange, $storeId),
            'performance_metrics' => $this->getPerformanceMetrics($dateRange, $storeId),
        ];
    }

    /**
     * Get business overview metrics
     */
    public function getBusinessOverview($dateRange, $storeId = null)
    {
        $salesQuery = Sale::completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']]);
        $purchaseQuery = Purchase::whereBetween('purchase_date', [$dateRange['start'], $dateRange['end']]);
        $expenseQuery = Expense::whereBetween('expense_date', [$dateRange['start'], $dateRange['end']]);

        if ($storeId) {
            $salesQuery->where('store_id', $storeId);
            $purchaseQuery->where('store_id', $storeId);
            $expenseQuery->where('store_id', $storeId);
        }

        $salesData = $salesQuery->selectRaw('
            COUNT(*) as total_sales,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_sale_value,
            SUM(profit_amount) as total_profit,
            SUM(discount) as total_discount
        ')->first();

        $purchaseData = $purchaseQuery->selectRaw('
            COUNT(*) as total_purchases,
            SUM(total_amount) as total_purchase_amount
        ')->first();

        $expenseData = $expenseQuery->selectRaw('
            COUNT(*) as total_expenses,
            SUM(amount) as total_expense_amount
        ')->first();

        // Previous period comparison
        $previousRange = [
            'start' => $dateRange['start']->copy()->subDays($dateRange['start']->diffInDays($dateRange['end'])),
            'end' => $dateRange['start']->copy()->subDay(),
        ];

        $previousRevenue = Sale::completed()
            ->whereBetween('sale_date', [$previousRange['start'], $previousRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->sum('total_amount');

        $revenueGrowth = $previousRevenue > 0 ?
            (($salesData->total_revenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        return [
            'revenue' => [
                'current' => $salesData->total_revenue ?? 0,
                'previous' => $previousRevenue,
                'growth_rate' => round($revenueGrowth, 2),
            ],
            'profitability' => [
                'gross_profit' => $salesData->total_profit ?? 0,
                'gross_margin' => $salesData->total_revenue > 0 ?
                    round(($salesData->total_profit / $salesData->total_revenue) * 100, 2) : 0,
                'net_profit' => ($salesData->total_profit ?? 0) - ($expenseData->total_expense_amount ?? 0),
                'net_margin' => $salesData->total_revenue > 0 ?
                    round((($salesData->total_profit - $expenseData->total_expense_amount) / $salesData->total_revenue) * 100, 2) : 0,
            ],
            'sales_performance' => [
                'total_sales' => $salesData->total_sales ?? 0,
                'avg_sale_value' => round($salesData->avg_sale_value ?? 0, 2),
                'total_discount' => $salesData->total_discount ?? 0,
            ],
            'expenses' => [
                'total_amount' => $expenseData->total_expense_amount ?? 0,
                'total_transactions' => $expenseData->total_expenses ?? 0,
            ],
            'purchases' => [
                'total_amount' => $purchaseData->total_purchase_amount ?? 0,
                'total_transactions' => $purchaseData->total_purchases ?? 0,
            ],
        ];
    }

    /**
     * Get detailed sales analytics
     */
    public function getSalesAnalytics($dateRange, $storeId = null)
    {
        // Sales trends
        $salesTrends = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                DATE(sale_date) as date,
                COUNT(*) as sales_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_value
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Sales by payment method
        $paymentMethods = Sale::completed()
            ->join('transactions', 'sales.id', '=', 'transactions.sales_id')
            ->whereBetween('sales.sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('sales.store_id', $storeId);
            })
            ->selectRaw('
                transactions.payment_method,
                COUNT(DISTINCT sales.id) as sales_count,
                SUM(transactions.amount) as amount,
                AVG(transactions.amount) as avg_amount
            ')
            ->groupBy('transactions.payment_method')
            ->get();

        // Sales by hour of day
        $salesByHour = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                HOUR(sale_time) as hour,
                COUNT(*) as sales_count,
                SUM(total_amount) as revenue
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Sales by day of week
        $salesByDayOfWeek = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                DAYNAME(sale_date) as day_name,
                DAYOFWEEK(sale_date) as day_number,
                COUNT(*) as sales_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_value
            ')
            ->groupBy('day_number', 'day_name')
            ->orderBy('day_number')
            ->get();

        // Best and worst performing days
        $dailyPerformance = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                sale_date,
                COUNT(*) as sales_count,
                SUM(total_amount) as revenue
            ')
            ->groupBy('sale_date')
            ->orderBy('revenue', 'desc')
            ->get();

        return [
            'trends' => $salesTrends,
            'payment_methods' => $paymentMethods,
            'by_hour' => $salesByHour,
            'by_day_of_week' => $salesByDayOfWeek,
            'best_day' => $dailyPerformance->first(),
            'worst_day' => $dailyPerformance->last(),
            'peak_hours' => $salesByHour->sortByDesc('revenue')->take(3),
        ];
    }

    /**
     * Get inventory analytics
     */
    public function getInventoryAnalytics($storeId = null)
    {
        // Inventory valuation
        $inventoryValuation = Product::query()
            ->selectRaw('
                COUNT(*) as total_products,
                SUM(quantity) as total_quantity,
                SUM(quantity * cost) as total_cost_value,
                SUM(quantity * price) as total_retail_value,
                SUM(quantity * (price - cost)) as total_potential_profit
            ')
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->first();

        // Stock status breakdown
        $stockStatus = Product::query()
            ->selectRaw('
                CASE
                    WHEN quantity = 0 THEN "out_of_stock"
                    WHEN quantity <= alert_quantity THEN "low_stock"
                    ELSE "in_stock"
                END as stock_status,
                COUNT(*) as count,
                SUM(quantity) as total_quantity
            ')
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->groupBy('stock_status')
            ->get();

        // Category performance
        $categoryPerformance = Product::join('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('
                categories.name as category_name,
                COUNT(products.id) as product_count,
                SUM(products.quantity) as total_quantity,
                SUM(products.quantity * products.cost) as total_cost_value,
                SUM(products.quantity * products.price) as total_retail_value,
                AVG(products.quantity) as avg_quantity_per_product
            ')
            ->when($storeId, function($query) use ($storeId) {
                $query->where('products.store_id', $storeId);
            })
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_retail_value', 'desc')
            ->get();

        // Slow-moving inventory
        $slowMoving = Product::where('is_stock_managed', true)
            ->where('quantity', '>', 0)
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->whereDoesntHave('saleItems', function($query) {
                $query->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                    ->where('sales.status', 'completed')
                    ->where('sales.sale_date', '>=', now()->subDays(90));
            })
            ->with(['category'])
            ->orderBy('quantity', 'desc')
            ->limit(20)
            ->get(['id', 'name', 'quantity', 'cost', 'price', 'category_id']);

        // Dead inventory (never sold)
        $deadInventory = Product::where('is_stock_managed', true)
            ->where('quantity', '>', 0)
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->whereDoesntHave('saleItems')
            ->where('created_at', '<', now()->subDays(180))
            ->with(['category'])
            ->get(['id', 'name', 'quantity', 'cost', 'price', 'created_at', 'category_id']);

        return [
            'valuation' => $inventoryValuation,
            'stock_status' => $stockStatus,
            'category_performance' => $categoryPerformance,
            'slow_moving' => $slowMoving,
            'dead_inventory' => $deadInventory,
            'turnover_ratio' => $this->calculateInventoryTurnover($storeId),
            'aging_analysis' => $this->getInventoryAging($storeId),
        ];
    }

    /**
     * Get customer analytics data
     */
    public function getCustomerAnalyticsData($dateRange, $storeId = null)
    {
        // Customer acquisition
        $newCustomers = Contact::customers()
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        $activeCustomers = Contact::customers()
            ->whereHas('sales', function($query) use ($dateRange) {
                $query->completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']]);
            })
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        // Customer segmentation
        $customerSegments = $this->getCustomerSegments($storeId);

        // Customer lifetime value
        $clvData = $this->calculateCustomerLifetimeValue($storeId);

        // Purchase frequency analysis
        $purchaseFrequency = $this->analyzePurchaseFrequency($dateRange, $storeId);

        // Customer retention
        $retentionData = $this->calculateCustomerRetention($dateRange, $storeId);

        return [
            'acquisition' => [
                'new_customers' => $newCustomers,
                'acquisition_rate' => $retentionData['total_customers'] > 0 ?
                    ($newCustomers / $retentionData['total_customers']) * 100 : 0,
            ],
            'engagement' => [
                'active_customers' => $activeCustomers,
                'total_customers' => $retentionData['total_customers'],
                'activity_rate' => $retentionData['total_customers'] > 0 ?
                    ($activeCustomers / $retentionData['total_customers']) * 100 : 0,
            ],
            'segments' => $customerSegments,
            'lifetime_value' => $clvData,
            'purchase_frequency' => $purchaseFrequency,
            'retention' => $retentionData,
        ];
    }

    /**
     * Get financial analytics
     */
    public function getFinancialAnalytics($dateRange, $storeId = null)
    {
        // Revenue breakdown
        $revenueBreakdown = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                SUM(total_amount) as total_revenue,
                SUM(discount) as total_discount,
                SUM(tax_amount) as total_tax,
                SUM(shipping_amount) as total_shipping,
                COUNT(*) as transaction_count
            ')
            ->first();

        // Cost of goods sold
        $cogs = Sale::completed()
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('sales.store_id', $storeId);
            })
            ->selectRaw('
                SUM(sale_items.quantity * sale_items.unit_cost) as total_cogs,
                SUM(sale_items.quantity * sale_items.unit_price) as total_retail
            ')
            ->first();

        // Expense analysis
        $expenseAnalysis = Expense::whereBetween('expense_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                category,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            ')
            ->groupBy('category')
            ->orderBy('total_amount', 'desc')
            ->get();

        // Cash flow analysis
        $cashFlow = $this->analyzeCashFlow($dateRange, $storeId);

        // Profitability analysis by product category
        $categoryProfitability = Sale::completed()
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('sales.sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('sales.store_id', $storeId);
            })
            ->selectRaw('
                categories.name as category,
                SUM(sale_items.total_price) as revenue,
                SUM(sale_items.quantity * sale_items.unit_cost) as cost,
                SUM(sale_items.total_price - (sale_items.quantity * sale_items.unit_cost)) as profit,
                SUM(sale_items.quantity) as quantity_sold
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('profit', 'desc')
            ->get();

        return [
            'revenue_breakdown' => $revenueBreakdown,
            'cost_analysis' => $cogs,
            'expense_analysis' => $expenseAnalysis,
            'cash_flow' => $cashFlow,
            'category_profitability' => $categoryProfitability,
            'profit_margins' => [
                'gross_margin' => $revenueBreakdown->total_revenue > 0 ?
                    round((($revenueBreakdown->total_revenue - $cogs->total_cogs) / $revenueBreakdown->total_revenue) * 100, 2) : 0,
                'net_margin' => $revenueBreakdown->total_revenue > 0 ?
                    round((($revenueBreakdown->total_revenue - $cogs->total_cogs - $expenseAnalysis->sum('total_amount')) / $revenueBreakdown->total_revenue) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Get operational analytics
     */
    public function getOperationalAnalytics($dateRange, $storeId = null)
    {
        // Employee performance
        $employeePerformance = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->join('users', 'sales.created_by', '=', 'users.id')
            ->selectRaw('
                users.name as employee_name,
                COUNT(sales.id) as sales_count,
                SUM(sales.total_amount) as total_revenue,
                AVG(sales.total_amount) as avg_sale_value
            ')
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // Peak business hours
        $peakHours = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                HOUR(sale_time) as hour,
                COUNT(*) as sales_count,
                SUM(total_amount) as revenue
            ')
            ->groupBy('hour')
            ->orderBy('revenue', 'desc')
            ->get();

        // Seasonal trends
        $seasonalTrends = Sale::completed()
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                MONTH(sale_date) as month,
                YEAR(sale_date) as year,
                COUNT(*) as sales_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_sale_value
            ')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month')
            ->limit(12)
            ->get();

        return [
            'employee_performance' => $employeePerformance,
            'peak_hours' => $peakHours,
            'seasonal_trends' => $seasonalTrends,
            'efficiency_metrics' => [
                'sales_per_day' => $this->calculateSalesPerDay($dateRange, $storeId),
                'revenue_per_employee' => $this->calculateRevenuePerEmployee($dateRange, $storeId),
                'transaction_processing_time' => '2.5', // Would need actual time tracking data
            ],
        ];
    }

    /**
     * Get performance KPIs
     */
    public function getPerformanceMetrics($dateRange, $storeId = null)
    {
        return [
            'sales_metrics' => [
                'conversion_rate' => $this->calculateConversionRate($storeId),
                'average_order_value' => $this->calculateAverageOrderValue($dateRange, $storeId),
                'sales_per_employee' => $this->calculateSalesPerEmployee($dateRange, $storeId),
                'sales_growth_rate' => $this->calculateSalesGrowthRate($dateRange, $storeId),
            ],
            'inventory_metrics' => [
                'inventory_turnover' => $this->calculateInventoryTurnover($storeId),
                'stockout_rate' => $this->calculateStockoutRate($storeId),
                'carrying_cost' => $this->calculateCarryingCost($storeId),
                'shrinkage_rate' => $this->calculateShrinkageRate($storeId),
            ],
            'customer_metrics' => [
                'customer_acquisition_cost' => $this->calculateCAC($dateRange, $storeId),
                'customer_lifetime_value' => $this->calculateCLV($storeId),
                'customer_retention_rate' => $this->calculateRetentionRate($dateRange, $storeId),
                'repeat_purchase_rate' => $this->calculateRepeatPurchaseRate($dateRange, $storeId),
            ],
            'financial_metrics' => [
                'gross_profit_margin' => $this->calculateGrossProfitMargin($dateRange, $storeId),
                'net_profit_margin' => $this->calculateNetProfitMargin($dateRange, $storeId),
                'return_on_investment' => $this->calculateROI($dateRange, $storeId),
                'operating_cash_flow' => $this->calculateOperatingCashFlow($dateRange, $storeId),
            ],
        ];
    }

    // Helper methods for calculations
    private function getDateRange($period) { /* ... */ }
    private function getCustomerSegments($storeId) { /* ... */ }
    private function calculateCustomerLifetimeValue($storeId) { /* ... */ }
    private function analyzePurchaseFrequency($dateRange, $storeId) { /* ... */ }
    private function calculateCustomerRetention($dateRange, $storeId) { /* ... */ }
    private function analyzeCashFlow($dateRange, $storeId) { /* ... */ }
    private function calculateInventoryTurnover($storeId) { /* ... */ }
    private function getInventoryAging($storeId) { /* ... */ }
    private function calculateSalesPerDay($dateRange, $storeId) { /* ... */ }
    private function calculateRevenuePerEmployee($dateRange, $storeId) { /* ... */ }
    private function calculateConversionRate($storeId) { /* ... */ }
    private function calculateAverageOrderValue($dateRange, $storeId) { /* ... */ }
    private function calculateSalesGrowthRate($dateRange, $storeId) { /* ... */ }
    private function calculateStockoutRate($storeId) { /* ... */ }
    private function calculateCarryingCost($storeId) { /* ... */ }
    private function calculateShrinkageRate($storeId) { /* ... */ }
    private function calculateCAC($dateRange, $storeId) { /* ... */ }
    private function calculateCLV($storeId) { /* ... */ }
    private function calculateRetentionRate($dateRange, $storeId) { /* ... */ }
    private function calculateRepeatPurchaseRate($dateRange, $storeId) { /* ... */ }
    private function calculateGrossProfitMargin($dateRange, $storeId) { /* ... */ }
    private function calculateNetProfitMargin($dateRange, $storeId) { /* ... */ }
    private function calculateROI($dateRange, $storeId) { /* ... */ }
    private function calculateOperatingCashFlow($dateRange, $storeId) { /* ... */ }
}