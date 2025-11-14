<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Report' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #007bff;
            font-size: 24px;
        }
        .header .subtitle {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .summary {
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card .value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .data-table th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status-completed { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-cancelled { color: #dc3545; font-weight: bold; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 11px;
        }
        @media print {
            .footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        @if(isset($period))
            <div class="subtitle">Report Period: {{ $period }}</div>
        @endif
        <div class="subtitle">Generated on: {{ $generated_at }}</div>
    </div>

    @if(isset($summary))
    <div class="summary">
        <h2>Summary</h2>
        <div class="summary-grid">
            @foreach($summary as $key => $value)
                <div class="summary-card">
                    <h3>{{ ucwords(str_replace('_', ' ', $key)) }}</h3>
                    <div class="value">{{ is_numeric($value) ? number_format($value, 2) : $value }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    @if(isset($sales))
    <h2>Sales Details</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Store</th>
                <th>Amount</th>
                <th>Profit</th>
                <th>Status</th>
                <th>Payment Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
            <tr>
                <td>{{ $sale['invoice_number'] }}</td>
                <td>{{ $sale['date'] }}</td>
                <td>{{ $sale['customer'] }}</td>
                <td>{{ $sale['store'] }}</td>
                <td>${{ $sale['amount'] }}</td>
                <td>${{ $sale['profit'] }}</td>
                <td class="status-{{ $sale['status'] }}">{{ $sale['status'] }}</td>
                <td class="status-{{ $sale['payment_status'] }}">{{ $sale['payment_status'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(isset($products))
    <h2>Inventory Details</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>SKU</th>
                <th>Barcode</th>
                <th>Category</th>
                <th>Brand</th>
                <th>Quantity</th>
                <th>Cost Price</th>
                <th>Sale Price</th>
                <th>Cost Value</th>
                <th>Sale Value</th>
                <th>Potential Profit</th>
                <th>Profit Margin %</th>
                <th>Stock Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
            <tr>
                <td>{{ $product['name'] }}</td>
                <td>{{ $product['sku'] }}</td>
                <td>{{ $product['barcode'] }}</td>
                <td>{{ $product['category'] }}</td>
                <td>{{ $product['brand'] }}</td>
                <td>{{ $product['quantity'] }}</td>
                <td>${{ $product['cost_price'] }}</td>
                <td>${{ $product['sale_price'] }}</td>
                <td>${{ $product['cost_value'] }}</td>
                <td>${{ $product['sale_value'] }}</td>
                <td>${{ $product['potential_profit'] }}</td>
                <td>{{ $product['profit_margin'] }}%</td>
                <td>{{ $product['stock_status'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(isset($customers))
    <h2>Customer Details</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Company</th>
                <th>Loyalty Points</th>
                <th>Balance</th>
                <th>Total Spent</th>
                <th>Total Orders</th>
                <th>Avg Order Value</th>
                <th>First Purchase</th>
                <th>Last Purchase</th>
            </tr>
        </thead>
        <tbody>
            @foreach($customers as $customer)
            <tr>
                <td>{{ $customer['name'] }}</td>
                <td>{{ $customer['email'] }}</td>
                <td>{{ $customer['phone'] }}</td>
                <td>{{ $customer['company'] ?? '-' }}</td>
                <td>{{ $customer['loyalty_points'] }}</td>
                <td>${{ $customer['balance'] }}</td>
                <td>${{ $customer['total_spent'] }}</td>
                <td>{{ $customer['total_orders'] }}</td>
                <td>${{ $customer['avg_order_value'] }}</td>
                <td>{{ $customer['first_purchase'] ?? '-' }}</td>
                <td>{{ $customer['last_purchase'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(isset($monthly_breakdown))
    <h2>Monthly Breakdown</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Month</th>
                <th>Revenue</th>
                <th>Profit</th>
                <th>Sales Count</th>
                <th>Avg Sale Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthly_breakdown as $data)
            <tr>
                <td>{{ $data['month'] }}</td>
                <td>${{ $data['revenue'] }}</td>
                <td>${{ $data['profit'] }}</td>
                <td>{{ $data['sales_count'] }}</td>
                <td>${{ $data['avg_sale_value'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <p>This report was generated on {{ $generated_at }} by TokoKira POS System</p>
        <p>Page {{ isset($page) ? $page : 1 }} of {{ isset($total_pages) ? $total_pages : 1 }}</p>
    </div>
</body>
</html>