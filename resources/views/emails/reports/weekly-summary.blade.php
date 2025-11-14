<!DOCTYPE html>
<html>
<head>
    <title>Weekly Performance Report</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .section { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 15px 0; }
        .summary-card { padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .summary-card h4 { margin: 0 0 8px 0; color: #666; font-size: 12px; text-transform: uppercase; }
        .summary-card .value { font-size: 18px; font-weight: bold; color: #333; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Weekly Performance Report</h1>
            <p>{{ $store->name }} - Last 7 Days</p>
        </div>

        <div class="content">
            <div class="section">
                <h2>Sales Overview</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <h4>Total Sales</h4>
                        <div class="value">{{ $salesReport['summary']['total_sales'] }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Total Revenue</h4>
                        <div class="value">${{ number_format($salesReport['summary']['total_revenue'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Total Profit</h4>
                        <div class="value">${{ number_format($salesReport['summary']['total_profit'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Average Order Value</h4>
                        <div class="value">${{ number_format($salesReport['summary']['average_order_value'], 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>Inventory Status</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <h4>Total Products</h4>
                        <div class="value">{{ $inventoryReport['summary']['total_products'] }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Total Stock Value</h4>
                        <div class="value">${{ number_format($inventoryReport['summary']['total_sale_value'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Low Stock Items</h4>
                        <div class="value">{{ $inventoryReport['summary']['low_stock_count'] }}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Out of Stock Items</h4>
                        <div class="value">{{ $inventoryReport['summary']['out_of_stock_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated weekly report from TokoKira POS System</p>
            <p>Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>