<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Store;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Exports\SalesReportExport;
use App\Exports\InventoryReportExport;
use App\Exports\CustomerReportExport;
use App\Exports\FinancialReportExport;

class ReportGenerationService
{
    /**
     * Generate sales report
     */
    public function generateSalesReport($filters = [])
    {
        $dateRange = $this->getDateRangeFromFilters($filters);

        $query = Sale::with(['contact', 'store'])
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']]);

        if (isset($filters['store_id']) && $filters['store_id']) {
            $query->where('store_id', $filters['store_id']);
        }

        if (isset($filters['customer_id']) && $filters['customer_id']) {
            $query->where('contact_id', $filters['customer_id']);
        }

        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        $sales = $query->orderBy('sale_date', 'desc')->get();

        $reportData = [
            'title' => 'Sales Report',
            'period' => $dateRange['start']->format('M d, Y') . ' - ' . $dateRange['end']->format('M d, Y'),
            'generated_at' => now()->format('M d, Y H:i:s'),
            'summary' => $this->calculateSalesSummary($sales),
            'sales' => $sales->map(function($sale) {
                return [
                    'invoice_number' => $sale->invoice_number,
                    'date' => $sale->sale_date->format('Y-m-d'),
                    'customer' => $sale->contact?->name ?? 'Walk-in',
                    'store' => $sale->store?->name,
                    'amount' => number_format($sale->total_amount, 2),
                    'profit' => number_format($sale->profit_amount, 2),
                    'status' => ucfirst($sale->status),
                    'payment_status' => ucfirst($sale->payment_status),
                    'items_count' => $sale->saleItems->count(),
                ];
            })->toArray(),
            'charts' => [
                'sales_trend' => $this->generateSalesTrendData($sales),
                'payment_methods' => $this->generatePaymentMethodData($sales),
                'top_products' => $this->generateTopProductsData($sales),
            ],
        ];

        return $reportData;
    }

    /**
     * Generate inventory report
     */
    public function generateInventoryReport($filters = [])
    {
        $query = Product::with(['category', 'brand']);

        if (isset($filters['category_id']) && $filters['category_id']) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['brand_id']) && $filters['brand_id']) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (isset($filters['stock_status'])) {
            switch ($filters['stock_status']) {
                case 'in_stock':
                    $query->where('quantity', '>', 0);
                    break;
                case 'low_stock':
                    $query->whereRaw('quantity <= alert_quantity AND quantity > 0');
                    break;
                case 'out_of_stock':
                    $query->where('quantity', 0);
                    break;
            }
        }

        $products = $query->get();

        $reportData = [
            'title' => 'Inventory Report',
            'generated_at' => now()->format('M d, Y H:i:s'),
            'summary' => $this->calculateInventorySummary($products),
            'products' => $products->map(function($product) {
                $costValue = $product->quantity * $product->cost;
                $saleValue = $product->quantity * $product->price;
                $profit = $saleValue - $costValue;

                return [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'category' => $product->category?->name,
                    'brand' => $product->brand?->name,
                    'quantity' => number_format($product->quantity, 2),
                    'cost_price' => number_format($product->cost, 2),
                    'sale_price' => number_format($product->price, 2),
                    'cost_value' => number_format($costValue, 2),
                    'sale_value' => number_format($saleValue, 2),
                    'potential_profit' => number_format($profit, 2),
                    'profit_margin' => $saleValue > 0 ? round(($profit / $saleValue) * 100, 2) : 0,
                    'stock_status' => $product->stock_status,
                    'is_active' => $product->is_active ? 'Yes' : 'No',
                ];
            })->toArray(),
            'charts' => [
                'category_breakdown' => $this->generateCategoryBreakdown($products),
                'stock_status' => $this->generateStockStatusChart($products),
                'value_distribution' => $this->generateValueDistribution($products),
            ],
        ];

        return $reportData;
    }

    /**
     * Generate financial report
     */
    public function generateFinancialReport($filters = [])
    {
        $dateRange = $this->getDateRangeFromFilters($filters);

        // Revenue data
        $revenueData = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when(isset($filters['store_id']) && $filters['store_id'], function($query) use ($filters) {
                $query->where('store_id', $filters['store_id']);
            })
            ->selectRaw('
                SUM(total_amount) as total_revenue,
                SUM(profit_amount) as gross_profit,
                SUM(discount) as total_discount,
                SUM(tax_amount) as total_tax,
                COUNT(*) as transaction_count
            ')
            ->first();

        // Expense data
        $expenseData = Expense::whereBetween('expense_date', [$dateRange['start'], $dateRange['end']])
            ->when(isset($filters['store_id']) && $filters['store_id'], function($query) use ($filters) {
                $query->where('store_id', $filters['store_id']);
            })
            ->selectRaw('
                SUM(amount) as total_expenses,
                COUNT(*) as expense_count
            ')
            ->first();

        // Cost of Goods Sold
        $cogsData = Sale::completed()
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sale_date', [$dateRange['start'], $dateRange['end']])
            ->when(isset($filters['store_id']) && $filters['store_id'], function($query) use ($filters) {
                $query->where('sales.store_id', $filters['store_id']);
            })
            ->selectRaw('
                SUM(sale_items.quantity * sale_items.unit_cost) as total_cogs
            ')
            ->first();

        // Monthly breakdown
        $monthlyData = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->when(isset($filters['store_id']) && $filters['store_id'], function($query) use ($filters) {
                $query->where('store_id', $filters['store_id']);
            })
            ->selectRaw('
                DATE_FORMAT(sale_date, "%Y-%m") as month,
                SUM(total_amount) as revenue,
                SUM(profit_amount) as profit,
                COUNT(*) as sales_count
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $totalRevenue = $revenueData->total_revenue ?? 0;
        $totalExpenses = $expenseData->total_expenses ?? 0;
        $totalCOGS = $cogsData->total_cogs ?? 0;
        $grossProfit = $revenueData->gross_profit ?? 0;
        $netProfit = $grossProfit - $totalExpenses;

        $reportData = [
            'title' => 'Financial Report',
            'period' => $dateRange['start']->format('M d, Y') . ' - ' . $dateRange['end']->format('M d, Y'),
            'generated_at' => now()->format('M d, Y H:i:s'),
            'summary' => [
                'total_revenue' => number_format($totalRevenue, 2),
                'total_expenses' => number_format($totalExpenses, 2),
                'total_cogs' => number_format($totalCOGS, 2),
                'gross_profit' => number_format($grossProfit, 2),
                'net_profit' => number_format($netProfit, 2),
                'gross_profit_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
                'net_profit_margin' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0,
                'transaction_count' => $revenueData->transaction_count,
                'expense_count' => $expenseData->expense_count,
            ],
            'monthly_breakdown' => $monthlyData->map(function($data) {
                return [
                    'month' => $data->month,
                    'revenue' => number_format($data->revenue, 2),
                    'profit' => number_format($data->profit, 2),
                    'sales_count' => $data->sales_count,
                    'avg_sale_value' => number_format($data->revenue / $data->sales_count, 2),
                ];
            })->toArray(),
            'charts' => [
                'revenue_trend' => $monthlyData->pluck('revenue'),
                'profit_trend' => $monthlyData->pluck('profit'),
                'expense_breakdown' => $this->generateExpenseBreakdown($dateRange),
                'profit_margin_trend' => $monthlyData->map(function($data) {
                    return $data->revenue > 0 ? round(($data->profit / $data->revenue) * 100, 2) : 0;
                }),
            ],
        ];

        return $reportData;
    }

    /**
     * Generate customer report
     */
    public function generateCustomerReport($filters = [])
    {
        $dateRange = $this->getDateRangeFromFilters($filters);

        $query = Contact::with(['sales' => function($query) use ($dateRange) {
            $query->completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']]);
        }]);

        if (isset($filters['segment'])) {
            switch ($filters['segment']) {
                case 'new':
                    $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                    break;
                case 'active':
                    $query->whereHas('sales', function($query) use ($dateRange) {
                        $query->completed()->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']]);
                    });
                    break;
                case 'vip':
                    $query->whereHas('sales', function($query) {
                        $query->completed()->havingRaw('SUM(total_amount) > 5000');
                    });
                    break;
            }
        }

        $customers = $query->customers()->get();

        $reportData = [
            'title' => 'Customer Report',
            'period' => $dateRange['start']->format('M d, Y') . ' - ' . $dateRange['end']->format('M d, Y'),
            'generated_at' => now()->format('M d, Y H:i:s'),
            'summary' => $this->calculateCustomerSummary($customers),
            'customers' => $customers->map(function($customer) {
                $totalSpent = $customer->sales->sum('total_amount');
                $totalOrders = $customer->sales->count();
                $avgOrderValue = $totalOrders > 0 ? $totalSpent / $totalOrders : 0;

                return [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'company' => $customer->company_name,
                    'loyalty_points' => $customer->loyalty_points,
                    'balance' => number_format($customer->balance, 2),
                    'total_spent' => number_format($totalSpent, 2),
                    'total_orders' => $totalOrders,
                    'avg_order_value' => number_format($avgOrderValue, 2),
                    'first_purchase' => $customer->sales->min('sale_date'),
                    'last_purchase' => $customer->sales->max('sale_date'),
                ];
            })->toArray(),
            'charts' => [
                'customer_segments' => $this->generateCustomerSegments($customers),
                'revenue_distribution' => $this->generateRevenueDistribution($customers),
                'loyalty_distribution' => $this->generateLoyaltyDistribution($customers),
            ],
        ];

        return $reportData;
    }

    /**
     * Export report to PDF
     */
    public function exportToPDF($reportData, $filename = null)
    {
        $filename = $filename ?: 'report_' . time() . '.pdf';

        $pdf = PDF::loadView('reports.pdf', $reportData)
            ->setPaper('a4')
            ->setOrientation('landscape')
            ->setOption('margin-bottom', 15);

        return $pdf->download($filename);
    }

    /**
     * Export report to Excel
     */
    public function exportToExcel($reportData, $reportType, $filename = null)
    {
        $filename = $filename ?: $reportType . '_report_' . time() . '.xlsx';

        switch ($reportType) {
            case 'sales':
                $export = new SalesReportExport($reportData);
                break;
            case 'inventory':
                $export = new InventoryReportExport($reportData);
                break;
            case 'customers':
                $export = new CustomerReportExport($reportData);
                break;
            case 'financial':
                $export = new FinancialReportExport($reportData);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported report type: {$reportType}");
        }

        return Excel::download($export, $filename);
    }

    /**
     * Export report to CSV
     */
    public function exportToCSV($reportData, $filename = null)
    {
        $filename = $filename ?: 'report_' . time() . '.csv';

        $csv = '';
        $headers = [];

        // Determine headers based on report type
        if (isset($reportData['sales'])) {
            $headers = ['Invoice #', 'Date', 'Customer', 'Store', 'Amount', 'Profit', 'Status', 'Payment Status'];
            $csv .= implode(',', $headers) . "\n";

            foreach ($reportData['sales'] as $row) {
                $csv .= implode(',', [
                    $row['invoice_number'],
                    $row['date'],
                    $row['customer'],
                    $row['store'],
                    $row['amount'],
                    $row['profit'],
                    $row['status'],
                    $row['payment_status']
                ]) . "\n";
            }
        } elseif (isset($reportData['products'])) {
            $headers = ['Product Name', 'SKU', 'Category', 'Brand', 'Quantity', 'Cost Price', 'Sale Price', 'Potential Profit', 'Stock Status'];
            $csv .= implode(',', $headers) . "\n";

            foreach ($reportData['products'] as $row) {
                $csv .= implode(',', [
                    $row['name'],
                    $row['sku'],
                    $row['category'],
                    $row['brand'],
                    $row['quantity'],
                    $row['cost_price'],
                    $row['sale_price'],
                    $row['potential_profit'],
                    $row['stock_status']
                ]) . "\n";
            }
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Generate automated daily summary report
     */
    public function generateDailySummary($storeId = null)
    {
        $today = now();
        $yesterday = $today->copy()->subDay();

        // Get today's data
        $todayData = Sale::completed()
            ->whereDate('sale_date', $today)
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                COUNT(*) as sales_count,
                SUM(total_amount) as revenue,
                SUM(profit_amount) as profit
            ')
            ->first();

        // Get yesterday's data for comparison
        $yesterdayData = Sale::completed()
            ->whereDate('sale_date', $yesterday)
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->selectRaw('
                COUNT(*) as sales_count,
                SUM(total_amount) as revenue,
                SUM(profit_amount) as profit
            ')
            ->first();

        // Get low stock alerts
        $lowStockCount = Product::whereRaw('quantity <= alert_quantity')
            ->when($storeId, function($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->count();

        // Get top performing products today
        $topProducts = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereDate('sales.sale_date', $today)
            ->where('sales.status', 'completed')
            ->when($storeId, function($query) use ($storeId) {
                $query->where('sales.store_id', $storeId);
            })
            ->selectRaw('
                products.name,
                SUM(sale_items.quantity) as quantity_sold,
                SUM(sale_items.total_price) as revenue
            ')
            ->groupBy('products.id', 'products.name')
            ->orderBy('revenue', 'desc')
            ->limit(5)
            ->get();

        $summary = [
            'date' => $today->format('Y-m-d'),
            'store_id' => $storeId,
            'performance' => [
                'today' => [
                    'sales' => $todayData->sales_count ?? 0,
                    'revenue' => $todayData->revenue ?? 0,
                    'profit' => $todayData->profit ?? 0,
                ],
                'yesterday' => [
                    'sales' => $yesterdayData->sales_count ?? 0,
                    'revenue' => $yesterdayData->revenue ?? 0,
                    'profit' => $yesterdayData->profit ?? 0,
                ],
                'growth' => [
                    'sales' => $yesterdayData->sales_count > 0 ?
                        round((($todayData->sales_count - $yesterdayData->sales_count) / $yesterdayData->sales_count) * 100, 2) : 0,
                    'revenue' => $yesterdayData->revenue > 0 ?
                        round((($todayData->revenue - $yesterdayData->revenue) / $yesterdayData->revenue) * 100, 2) : 0,
                    'profit' => $yesterdayData->profit > 0 ?
                        round((($todayData->profit - $yesterdayData->profit) / $yesterdayData->profit) * 100, 2) : 0,
                ],
            ],
            'alerts' => [
                'low_stock_count' => $lowStockCount,
                'top_products' => $topProducts->map(function($product) {
                    return [
                        'name' => $product->name,
                        'quantity' => $product->quantity_sold,
                        'revenue' => number_format($product->revenue, 2),
                    ];
                })->toArray(),
            ],
        ];

        return $summary;
    }

    /**
     * Schedule and send automated reports
     */
    public function scheduleReport($reportType, $recipients, $filters = [])
    {
        $reportData = null;

        switch ($reportType) {
            case 'daily_summary':
                $reportData = $this->generateDailySummary($filters['store_id'] ?? null);
                break;
            case 'sales':
                $reportData = $this->generateSalesReport($filters);
                break;
            case 'inventory':
                $reportData = $this->generateInventoryReport($filters);
                break;
            case 'financial':
                $reportData = $this->generateFinancialReport($filters);
                break;
            case 'customers':
                $reportData = $this->generateCustomerReport($filters);
                break;
        }

        // Send email with report attachment
        foreach ($recipients as $recipient) {
            // This would integrate with email service
            Log::info("Report scheduled: {$reportType} for {$recipient}");
        }

        return $reportData;
    }

    // Helper methods
    private function getDateRangeFromFilters($filters)
    {
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            return [
                'start' => Carbon::parse($filters['start_date']),
                'end' => Carbon::parse($filters['end_date']),
            ];
        }

        $period = $filters['period'] ?? '30days';
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
            default:
                $startDate = $endDate->copy()->subDays(30);
                break;
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    private function calculateSalesSummary($sales)
    {
        return [
            'total_sales' => $sales->count(),
            'total_revenue' => $sales->sum('total_amount'),
            'total_profit' => $sales->sum('profit_amount'),
            'average_order_value' => $sales->avg('total_amount'),
        ];
    }

    private function calculateInventorySummary($products)
    {
        return [
            'total_products' => $products->count(),
            'total_quantity' => $products->sum('quantity'),
            'total_cost_value' => $products->sum(function($product) {
                return $product->quantity * $product->cost;
            }),
            'total_sale_value' => $products->sum(function($product) {
                return $product->quantity * $product->price;
            }),
            'total_potential_profit' => $products->sum(function($product) {
                return $product->quantity * ($product->price - $product->cost);
            }),
            'low_stock_count' => $products->where('stock_status', 'Low Stock')->count(),
            'out_of_stock_count' => $products->where('quantity', 0)->count(),
        ];
    }

    private function calculateCustomerSummary($customers)
    {
        return [
            'total_customers' => $customers->count(),
            'total_revenue' => $customers->sum(function($customer) {
                return $customer->sales->sum('total_amount');
            }),
            'average_revenue_per_customer' => $customers->avg(function($customer) {
                return $customer->sales->sum('total_amount');
            }),
            'total_loyalty_points' => $customers->sum('loyalty_points'),
        ];
    }

    // Chart generation methods
    private function generateSalesTrendData($sales)
    {
        return $sales->groupBy(function($sale) {
            return $sale->sale_date->format('Y-m-d');
        })->map(function($daySales) {
            return [
                'date' => $daySales->first()->sale_date->format('Y-m-d'),
                'sales_count' => $daySales->count(),
                'revenue' => $daySales->sum('total_amount'),
                'profit' => $daySales->sum('profit_amount'),
            ];
        })->values()->toArray();
    }

    private function generatePaymentMethodData($sales)
    {
        return $sales->groupBy('payment_method')
            ->map(function($paymentSales, $method) {
                return [
                    'method' => ucfirst($method),
                    'count' => $paymentSales->count(),
                    'total' => $paymentSales->sum('total_amount'),
                    'percentage' => round(($paymentSales->sum('total_amount') / $sales->sum('total_amount')) * 100, 2),
                ];
            })->values()->toArray();
    }

    private function generateTopProductsData($sales)
    {
        return $sales->flatMap(function($sale) {
            return $sale->saleItems;
        })->groupBy('product_id')
            ->map(function($items) {
                $product = $items->first()->product;
                return [
                    'name' => $product->name,
                    'quantity_sold' => $items->sum('quantity'),
                    'revenue' => $items->sum('total_price'),
                ];
            })
            ->sortByDesc('revenue')
            ->take(10)
            ->values()
            ->toArray();
    }

    private function generateCategoryBreakdown($products)
    {
        return $products->groupBy('category_id')
            ->map(function($categoryProducts) {
                $category = $categoryProducts->first()->category;
                $totalValue = $categoryProducts->sum(function($product) {
                    return $product->quantity * $product->price;
                });

                return [
                    'category' => $category->name ?? 'Uncategorized',
                    'count' => $categoryProducts->count(),
                    'total_value' => $totalValue,
                ];
            })
            ->sortByDesc('total_value')
            ->values()
            ->toArray();
    }

    private function generateStockStatusChart($products)
    {
        return [
            'in_stock' => $products->where('quantity', '>', 0)->count(),
            'low_stock' => $products->whereRaw('quantity <= alert_quantity AND quantity > 0')->count(),
            'out_of_stock' => $products->where('quantity', 0)->count(),
        ];
    }

    private function generateValueDistribution($products)
    {
        $ranges = [
            'low' => [0, 100],
            'medium' => [100, 500],
            'high' => [500, 1000],
            'premium' => [1000, PHP_INT_MAX],
        ];

        $distribution = [];
        foreach ($ranges as $label => [$min, $max]) {
            $count = $products->filter(function($product) use ($min, $max) {
                $value = $product->quantity * $product->price;
                return $value >= $min && $value < $max;
            })->count();

            $distribution[] = [
                'range' => ucfirst($label),
                'count' => $count,
            ];
        }

        return $distribution;
    }

    private function generateExpenseBreakdown($dateRange)
    {
        return Expense::whereBetween('expense_date', [$dateRange['start'], $dateRange['end']])
            ->groupBy('category')
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->orderByDesc('total')
            ->get()
            ->map(function($expense) {
                return [
                    'category' => $expense->category,
                    'total' => $expense->total,
                    'count' => $expense->count,
                ];
            })->toArray();
    }

    private function generateCustomerSegments($customers)
    {
        $segments = [
            'new' => 0,
            'active' => 0,
            'vip' => 0,
            'inactive' => 0,
        ];

        foreach ($customers as $customer) {
            $totalSpent = $customer->sales->sum('total_amount');
            $lastPurchase = $customer->sales->max('sale_date');

            if ($totalSpent > 5000) {
                $segments['vip']++;
            } elseif ($lastPurchase && $lastPurchase->diffInDays(now()) <= 30) {
                $segments['active']++;
            } elseif ($lastPurchase && $lastPurchase->diffInDays(now()) <= 90) {
                $segments['new']++;
            } else {
                $segments['inactive']++;
            }
        }

        return $segments;
    }

    private function generateRevenueDistribution($customers)
    {
        return $customers->map(function($customer) {
            return [
                'name' => $customer->name,
                'revenue' => $customer->sales->sum('total_amount'),
            ];
        })
        ->sortByDesc('revenue')
        ->take(20)
        ->values()
        ->toArray();
    }

    private function generateLoyaltyDistribution($customers)
    {
        return [
            'bronze' => $customers->where('loyalty_points', '<', 1000)->count(),
            'silver' => $customers->whereBetween('loyalty_points', [1000, 5000])->count(),
            'gold' => $customers->whereBetween('loyalty_points', [5000, 10000])->count(),
            'platinum' => $customers->where('loyalty_points', '>', 10000)->count(),
        ];
    }
}