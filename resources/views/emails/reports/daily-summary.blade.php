<!DOCTYPE html>
<html>
<head>
    <title>Daily Sales Summary</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0; }
        .summary-card { background: white; padding: 15px; border-radius: 5px; text-align: center; }
        .summary-card h3 { margin: 0 0 10px 0; color: #007bff; }
        .summary-card .value { font-size: 24px; font-weight: bold; color: #333; }
        .growth { color: #28a745; }
        .growth.negative { color: #dc3545; }
        .alerts { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Daily Sales Summary</h1>
            <p>{{ $store->name }} - {{ $summary['date'] }}</p>
        </div>

        <div class="content">
            <h2>Today's Performance</h2>
            <div class="summary-grid">
                <div class="summary-card">
                    <h3>Sales</h3>
                    <div class="value">{{ $summary['performance']['today']['sales'] }}</div>
                    <div class="growth {{ $summary['performance']['growth']['sales'] < 0 ? 'negative' : '' }}">
                        {{ $summary['performance']['growth']['sales'] > 0 ? '+' : '' }}{{ $summary['performance']['growth']['sales'] }}% vs yesterday
                    </div>
                </div>

                <div class="summary-card">
                    <h3>Revenue</h3>
                    <div class="value">${{ number_format($summary['performance']['today']['revenue'], 2) }}</div>
                    <div class="growth {{ $summary['performance']['growth']['revenue'] < 0 ? 'negative' : '' }}">
                        {{ $summary['performance']['growth']['revenue'] > 0 ? '+' : '' }}{{ $summary['performance']['growth']['revenue'] }}% vs yesterday
                    </div>
                </div>

                <div class="summary-card">
                    <h3>Profit</h3>
                    <div class="value">${{ number_format($summary['performance']['today']['profit'], 2) }}</div>
                    <div class="growth {{ $summary['performance']['growth']['profit'] < 0 ? 'negative' : '' }}">
                        {{ $summary['performance']['growth']['profit'] > 0 ? '+' : '' }}{{ $summary['performance']['growth']['profit'] }}% vs yesterday
                    </div>
                </div>
            </div>

            @if($summary['alerts']['low_stock_count'] > 0)
            <div class="alerts">
                <h3>⚠️ Inventory Alert</h3>
                <p>You have <strong>{{ $summary['alerts']['low_stock_count'] }}</strong> products with low stock. Please review your inventory.</p>
            </div>
            @endif

            @if(count($summary['alerts']['top_products']) > 0)
            <h3>🏆 Top Products Today</h3>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background: #007bff; color: white;">
                        <th style="padding: 10px; text-align: left;">Product</th>
                        <th style="padding: 10px; text-align: center;">Quantity Sold</th>
                        <th style="padding: 10px; text-align: right;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary['alerts']['top_products'] as $product)
                    <tr style="background: {{ $loop->even ? '#f9f9f9' : 'white' }};">
                        <td style="padding: 10px;">{{ $product['name'] }}</td>
                        <td style="padding: 10px; text-align: center;">{{ $product['quantity'] }}</td>
                        <td style="padding: 10px; text-align: right;">${{ $product['revenue'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div class="footer">
            <p>This is an automated report from TokoKira POS System</p>
            <p>Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>