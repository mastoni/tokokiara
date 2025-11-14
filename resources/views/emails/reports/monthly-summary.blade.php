<!DOCTYPE html>
<html>
<head>
    <title>Monthly Business Report</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .section { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 15px 0; }
        .summary-card { padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .summary-card h4 { margin: 0 0 8px 0; color: #666; font-size: 12px; text-transform: uppercase; }
        .summary-card .value { font-size: 18px; font-weight: bold; color: #333; }
        .profit-positive { color: #28a745; }
        .profit-negative { color: #dc3545; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Monthly Business Report</h1>
            <p>{{ $store->name }} - {{ $salesReport['period'] }}</p>
        </div>

        <div class="content">
            <div class="section">
                <h2>Financial Summary</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <h4>Total Revenue</h4>
                        <div class="value">${{ number_format($financialReport['summary']['total_revenue'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Total Expenses</h4>
                        <div class="value">${{ number_format($financialReport['summary']['total_expenses'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Net Profit</h4>
                        <div class="value {{ $financialReport['summary']['net_profit'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                            ${{ number_format($financialReport['summary']['net_profit'], 2) }}
                        </div>
                    </div>
                    <div class="summary-card">
                        <h4>Gross Profit Margin</h4>
                        <div class="value">{{ $financialReport['summary']['gross_profit_margin'] }}%</div>
                    </div>
                    <div class="summary-card">
                        <h4>Net Profit Margin</h4>
                        <div class="value {{ $financialReport['summary']['net_profit_margin'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                            {{ $financialReport['summary']['net_profit_margin'] }}%
                        </div>
                    </div>
                    <div class="summary-card">
                        <h4>Transactions</h4>
                        <div class="value">{{ $financialReport['summary']['transaction_count'] }}</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>Sales Performance</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <h4>Total Sales</h4>
                        <div class="value">{{ $salesReport['summary']['total_sales'] }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Gross Profit</h4>
                        <div class="value">${{ number_format($salesReport['summary']['total_profit'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Average Order Value</h4>
                        <div class="value">${{ number_format($salesReport['summary']['average_order_value'], 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>Customer Analytics</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <h4>Total Customers</h4>
                        <div class="value">{{ $customerReport['summary']['total_customers'] }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Total Customer Revenue</h4>
                        <div class="value">${{ number_format($customerReport['summary']['total_revenue'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Avg Revenue Per Customer</h4>
                        <div class="value">${{ number_format($customerReport['summary']['average_revenue_per_customer'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated monthly report from TokoKira POS System</p>
            <p>Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>