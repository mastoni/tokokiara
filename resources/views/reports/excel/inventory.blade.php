<table>
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
        @foreach($reportData['products'] as $product)
        <tr>
            <td>{{ $product['name'] }}</td>
            <td>{{ $product['sku'] }}</td>
            <td>{{ $product['barcode'] }}</td>
            <td>{{ $product['category'] }}</td>
            <td>{{ $product['brand'] }}</td>
            <td>{{ $product['quantity'] }}</td>
            <td>{{ $product['cost_price'] }}</td>
            <td>{{ $product['sale_price'] }}</td>
            <td>{{ $product['cost_value'] }}</td>
            <td>{{ $product['sale_value'] }}</td>
            <td>{{ $product['potential_profit'] }}</td>
            <td>{{ $product['profit_margin'] }}</td>
            <td>{{ $product['stock_status'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>