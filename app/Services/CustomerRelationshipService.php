<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\LoyaltyPointTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerRelationshipService
{
    /**
     * Get customer analytics and insights
     */
    public function getCustomerAnalytics(Contact $customer, $period = '12months')
    {
        $dateRange = $this->getDateRange($period);

        // Basic purchase statistics
        $purchaseStats = $customer->sales()
            ->completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(total_amount) as total_spent,
                AVG(total_amount) as avg_order_value,
                MIN(sale_date) as first_purchase,
                MAX(sale_date) as last_purchase,
                SUM(CASE WHEN payment_status = "paid" THEN total_amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN payment_status = "unpaid" OR payment_status = "partial" THEN total_amount ELSE 0 END) as unpaid_amount
            ')
            ->first();

        // Monthly purchase trend
        $monthlyTrend = $customer->sales()
            ->completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                DATE_FORMAT(sale_date, "%Y-%m") as month,
                COUNT(*) as orders,
                SUM(total_amount) as revenue
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Purchase frequency analysis
        $purchaseFrequency = $this->calculatePurchaseFrequency($customer, $dateRange);

        // Product preferences
        $topProducts = $this->getTopProducts($customer, $dateRange);
        $categoryPreferences = $this->getCategoryPreferences($customer, $dateRange);

        // Loyalty analytics
        $loyaltyAnalytics = $this->getLoyaltyAnalytics($customer, $dateRange);

        // Customer segmentation
        $segment = $this->segmentCustomer($customer, $purchaseStats);

        // Churn risk prediction
        $churnRisk = $this->calculateChurnRisk($customer, $purchaseStats);

        return [
            'purchase_stats' => $purchaseStats,
            'monthly_trend' => $monthlyTrend,
            'purchase_frequency' => $purchaseFrequency,
            'top_products' => $topProducts,
            'category_preferences' => $categoryPreferences,
            'loyalty_analytics' => $loyaltyAnalytics,
            'segment' => $segment,
            'churn_risk' => $churnRisk,
            'period' => $period,
        ];
    }

    /**
     * Calculate customer lifetime value (CLV)
     */
    public function calculateLifetimeValue(Contact $customer)
    {
        $totalRevenue = $customer->sales()
            ->completed()
            ->sum('total_amount');

        $totalOrders = $customer->sales()
            ->completed()
            ->count();

        $firstPurchaseDate = $customer->sales()
            ->completed()
            ->min('sale_date');

        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Customer lifetime in months
        $lifetimeMonths = $firstPurchaseDate ?
            Carbon::parse($firstPurchaseDate)->diffInMonths(now()) : 0;

        // Purchase frequency (orders per month)
        $purchaseFrequency = $lifetimeMonths > 0 ? $totalOrders / $lifetimeMonths : 0;

        // Predicted annual value
        $annualValue = $avgOrderValue * $purchaseFrequency * 12;

        // Lifetime value (simplified formula)
        $lifetimeValue = $avgOrderValue * $totalOrders;

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'avg_order_value' => $avgOrderValue,
            'lifetime_months' => $lifetimeMonths,
            'purchase_frequency' => $purchaseFrequency,
            'annual_value' => $annualValue,
            'lifetime_value' => $lifetimeValue,
            'first_purchase_date' => $firstPurchaseDate,
        ];
    }

    /**
     * Get customer segmentation
     */
    public function getCustomerSegmentation($limit = 100)
    {
        $customers = Contact::customers()
            ->with(['sales' => function($query) {
                $query->completed()->select('contact_id', 'total_amount', 'sale_date');
            }])
            ->limit($limit)
            ->get();

        $segments = [];

        foreach ($customers as $customer) {
            $segment = $this->segmentCustomer($customer);
            $segments[$segment][] = $customer->id;
        }

        return [
            'segments' => $segments,
            'segment_counts' => array_map('count', $segments),
            'total_customers' => $customers->count(),
        ];
    }

    /**
     * Get personalized product recommendations
     */
    public function getProductRecommendations(Contact $customer, $limit = 10)
    {
        // Get customer's purchase history
        $purchasedProducts = $customer->sales()
            ->completed()
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->pluck('sale_items.product_id')
            ->unique();

        // Get frequently purchased categories
        $preferredCategories = $customer->sales()
            ->completed()
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select('products.category_id', DB::raw('COUNT(*) as purchase_count'))
            ->groupBy('products.category_id')
            ->orderBy('purchase_count', 'desc')
            ->limit(3)
            ->pluck('category_id');

        // Find products in preferred categories that customer hasn't purchased
        $recommendations = Product::with(['category', 'brand'])
            ->active()
            ->where('quantity', '>', 0)
            ->whereIn('category_id', $preferredCategories)
            ->whereNotIn('id', $purchasedProducts)
            ->orderBy('is_featured', 'desc')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $recommendations;
    }

    /**
     * Calculate purchase frequency patterns
     */
    public function getPurchasePatterns(Contact $customer)
    {
        $sales = $customer->sales()
            ->completed()
            ->orderBy('sale_date')
            ->get(['sale_date', 'total_amount']);

        if ($sales->count() < 2) {
            return null;
        }

        $intervals = [];
        for ($i = 1; $i < $sales->count(); $i++) {
            $interval = $sales[$i]->sale_date->diffInDays($sales[$i-1]->sale_date);
            $intervals[] = $interval;
        }

        return [
            'avg_interval_days' => array_sum($intervals) / count($intervals),
            'min_interval_days' => min($intervals),
            'max_interval_days' => max($intervals),
            'most_common_interval' => $this->getMode($intervals),
            'total_intervals' => count($intervals),
        ];
    }

    /**
     * Get customers at risk of churn
     */
    public function getCustomersAtRisk($threshold = 0.7)
    {
        $customers = Contact::customers()->get();

        $atRiskCustomers = [];

        foreach ($customers as $customer) {
            $analytics = $this->getCustomerAnalytics($customer, '6months');
            $churnRisk = $analytics['churn_risk'];

            if ($churnRisk >= $threshold) {
                $atRiskCustomers[] = [
                    'customer' => $customer,
                    'risk_score' => $churnRisk,
                    'last_purchase' => $customer->sales()->completed()->max('sale_date'),
                    'risk_factors' => $this->identifyRiskFactors($customer, $analytics),
                ];
            }
        }

        // Sort by risk score (highest first)
        usort($atRiskCustomers, function($a, $b) {
            return $b['risk_score'] <=> $a['risk_score'];
        });

        return $atRiskCustomers;
    }

    /**
     * Generate customer retention report
     */
    public function getRetentionReport($period = '12months')
    {
        $dateRange = $this->getDateRange($period);

        // Get customers by cohort (month of first purchase)
        $cohorts = Sale::completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->join('contacts', 'sales.contact_id', '=', 'contacts.id')
            ->where('contacts.type', 'customer')
            ->selectRaw('
                DATE_FORMAT(MIN(sales.sale_date), "%Y-%m") as cohort_month,
                contacts.id as customer_id,
                MIN(sales.sale_date) as first_purchase_date
            ')
            ->groupBy('contacts.id')
            ->get()
            ->groupBy('cohort_month');

        $retentionData = [];

        foreach ($cohorts as $cohortMonth => $cohortCustomers) {
            $cohortSize = $cohortCustomers->count();
            $retentionByMonth = [];

            // Calculate retention for each month after cohort
            for ($monthOffset = 0; $monthOffset <= 12; $monthOffset++) {
                $retainedCustomers = 0;
                $checkDate = Carbon::parse($cohortMonth)->addMonths($monthOffset);

                foreach ($cohortCustomers as $customer) {
                    $hasPurchaseInMonth = Sale::completed()
                        ->where('contact_id', $customer->customer_id)
                        ->whereYear('sale_date', $checkDate->year)
                        ->whereMonth('sale_date', $checkDate->month)
                        ->exists();

                    if ($hasPurchaseInMonth) {
                        $retainedCustomers++;
                    }
                }

                $retentionRate = $cohortSize > 0 ? ($retainedCustomers / $cohortSize) * 100 : 0;
                $retentionByMonth[] = round($retentionRate, 2);
            }

            $retentionData[$cohortMonth] = [
                'cohort_size' => $cohortSize,
                'retention_rates' => $retentionByMonth,
            ];
        }

        return $retentionData;
    }

    /**
     * Get customer satisfaction metrics
     */
    public function getSatisfactionMetrics(Contact $customer)
    {
        // Repeat purchase rate
        $totalPurchases = $customer->sales()->completed()->count();
        $repeatPurchases = $totalPurchases - 1; // First purchase isn't repeat
        $repeatPurchaseRate = $totalPurchases > 0 ? ($repeatPurchases / $totalPurchases) * 100 : 0;

        // Average time between purchases
        $patterns = $this->getPurchasePatterns($customer);
        $avgTimeBetweenPurchases = $patterns['avg_interval_days'] ?? 0;

        // Complaint/return rate (would need returns table)
        $complaintRate = 0; // Placeholder

        // Loyalty program engagement
        $loyaltyEngagement = $this->getLoyaltyEngagement($customer);

        return [
            'repeat_purchase_rate' => round($repeatPurchaseRate, 2),
            'avg_time_between_purchases' => round($avgTimeBetweenPurchases, 0),
            'complaint_rate' => $complaintRate,
            'loyalty_engagement' => $loyaltyEngagement,
            'overall_satisfaction_score' => $this->calculateSatisfactionScore(
                $repeatPurchaseRate,
                $avgTimeBetweenPurchases,
                $loyaltyEngagement
            ),
        ];
    }

    /**
     * Calculate purchase frequency
     */
    private function calculatePurchaseFrequency(Contact $customer, $dateRange)
    {
        $sales = $customer->sales()
            ->completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->count();

        $months = $dateRange['start']->diffInMonths($dateRange['end']) ?: 1;

        return [
            'total_purchases' => $sales,
            'purchases_per_month' => $sales / $months,
            'days_between_purchases' => $sales > 1 ?
                ($dateRange['end']->diffInDays($dateRange['start']) / ($sales - 1)) : 0,
        ];
    }

    /**
     * Get top products for customer
     */
    private function getTopProducts(Contact $customer, $dateRange)
    {
        return $customer->sales()
            ->completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('
                products.id,
                products.name,
                SUM(sale_items.quantity) as total_quantity,
                SUM(sale_items.total_price) as total_spent,
                COUNT(DISTINCT sales.id) as purchase_occasions
            ')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_spent', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Get category preferences
     */
    private function getCategoryPreferences(Contact $customer, $dateRange)
    {
        return $customer->sales()
            ->completed()
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('
                categories.name as category_name,
                SUM(sale_items.total_price) as total_spent,
                COUNT(DISTINCT sales.id) as purchase_occasions
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_spent', 'desc')
            ->get();
    }

    /**
     * Get loyalty analytics
     */
    private function getLoyaltyAnalytics(Contact $customer, $dateRange)
    {
        return [
            'current_points' => $customer->loyalty_points,
            'points_earned' => $customer->loyaltyPointTransactions()
                ->where('transaction_type', 'earned')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->sum('points'),
            'points_redeemed' => $customer->loyaltyPointTransactions()
                ->where('transaction_type', 'redeemed')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->sum('points'),
            'engagement_score' => $this->getLoyaltyEngagement($customer),
        ];
    }

    /**
     * Segment customer based on purchase behavior
     */
    private function segmentCustomer(Contact $customer, $purchaseStats = null)
    {
        if (!$purchaseStats) {
            $purchaseStats = $customer->sales()
                ->completed()
                ->selectRaw('COUNT(*) as total_orders, SUM(total_amount) as total_spent')
                ->first();
        }

        $totalOrders = $purchaseStats->total_orders ?? 0;
        $totalSpent = $purchaseStats->total_spent ?? 0;

        // RFM Segmentation (Recency, Frequency, Monetary)
        $lastPurchase = $customer->sales()->completed()->max('sale_date');
        $recency = $lastPurchase ? now()->diffInDays($lastPurchase) : 999;
        $frequency = $totalOrders;
        $monetary = $totalSpent;

        // Simple segmentation logic
        if ($frequency >= 10 && $monetary >= 1000 && $recency <= 30) {
            return 'champion';
        } elseif ($frequency >= 5 && $monetary >= 500) {
            return 'loyal_customer';
        } elseif ($frequency >= 3 && $recency <= 90) {
            return 'potential_loyalist';
        } elseif ($monetary >= 200) {
            return 'big_spender';
        } elseif ($frequency >= 1 && $recency <= 60) {
            return 'new_customer';
        } elseif ($frequency >= 1 && $recency > 180) {
            return 'at_risk';
        } elseif ($frequency >= 1) {
            return 'need_attention';
        } else {
            return 'prospect';
        }
    }

    /**
     * Calculate churn risk
     */
    private function calculateChurnRisk(Contact $customer, $purchaseStats = null)
    {
        $risk = 0;

        // Recency factor (40% weight)
        $lastPurchase = $customer->sales()->completed()->max('sale_date');
        if ($lastPurchase) {
            $daysSinceLastPurchase = now()->diffInDays($lastPurchase);
            if ($daysSinceLastPurchase > 180) {
                $risk += 0.4;
            } elseif ($daysSinceLastPurchase > 90) {
                $risk += 0.2;
            }
        } else {
            $risk += 0.4;
        }

        // Frequency factor (30% weight)
        $totalOrders = $purchaseStats->total_orders ?? 0;
        if ($totalOrders == 0 || $totalOrders == 1) {
            $risk += 0.3;
        } elseif ($totalOrders <= 3) {
            $risk += 0.15;
        }

        // Monetary factor (20% weight)
        $totalSpent = $purchaseStats->total_spent ?? 0;
        if ($totalSpent < 100) {
            $risk += 0.2;
        } elseif ($totalSpent < 500) {
            $risk += 0.1;
        }

        // Engagement factor (10% weight)
        $loyaltyEngagement = $this->getLoyaltyEngagement($customer);
        if ($loyaltyEngagement < 0.3) {
            $risk += 0.1;
        }

        return min($risk, 1.0);
    }

    /**
     * Identify specific risk factors
     */
    private function identifyRiskFactors(Contact $customer, $analytics)
    {
        $factors = [];

        if ($analytics['purchase_stats']['total_orders'] <= 1) {
            $factors[] = 'Low purchase frequency';
        }

        $lastPurchase = $customer->sales()->completed()->max('sale_date');
        if ($lastPurchase && now()->diffInDays($lastPurchase) > 90) {
            $factors[] = 'No recent purchases';
        }

        if ($analytics['loyalty_analytics']['engagement_score'] < 0.3) {
            $factors[] = 'Low loyalty engagement';
        }

        if ($customer->balance < 0 && abs($customer->balance) > 1000) {
            $factors[] = 'High outstanding balance';
        }

        return $factors;
    }

    /**
     * Get loyalty engagement score
     */
    private function getLoyaltyEngagement(Contact $customer)
    {
        $totalPointsEarned = $customer->loyaltyPointTransactions()
            ->where('transaction_type', 'earned')
            ->sum('points');

        $totalPointsRedeemed = $customer->loyaltyPointTransactions()
            ->where('transaction_type', 'redeemed')
            ->sum('points');

        if ($totalPointsEarned == 0) {
            return 0;
        }

        $redemptionRate = $totalPointsRedeemed / $totalPointsEarned;
        $engagementScore = min($redemptionRate * 2, 1.0); // Double the redemption rate, max at 1.0

        return round($engagementScore, 2);
    }

    /**
     * Calculate overall satisfaction score
     */
    private function calculateSatisfactionScore($repeatPurchaseRate, $avgTimeBetweenPurchases, $loyaltyEngagement)
    {
        $score = 0;

        // Repeat purchase rate (40% weight)
        $score += ($repeatPurchaseRate / 100) * 0.4;

        // Purchase frequency (30% weight) - lower time between purchases is better
        $frequencyScore = max(0, 1 - ($avgTimeBetweenPurchases / 365)); // Normalize to 0-1
        $score += $frequencyScore * 0.3;

        // Loyalty engagement (30% weight)
        $score += $loyaltyEngagement * 0.3;

        return round($score * 100, 2); // Convert to percentage
    }

    /**
     * Get date range for analytics
     */
    private function getDateRange($period)
    {
        $endDate = now();

        switch ($period) {
            case '1month':
                $startDate = $endDate->copy()->subMonth();
                break;
            case '3months':
                $startDate = $endDate->copy()->subMonths(3);
                break;
            case '6months':
                $startDate = $endDate->copy()->subMonths(6);
                break;
            case '12months':
            default:
                $startDate = $endDate->copy()->subYear();
                break;
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * Get mode value from array
     */
    private function getMode($array)
    {
        $values = array_count_values($array);
        arsort($values);
        return key($values);
    }
}