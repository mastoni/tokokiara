<table>
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
        @foreach($reportData['sales'] as $sale)
        <tr>
            <td>{{ $sale['invoice_number'] }}</td>
            <td>{{ $sale['date'] }}</td>
            <td>{{ $sale['customer'] }}</td>
            <td>{{ $sale['store'] }}</td>
            <td>{{ $sale['amount'] }}</td>
            <td>{{ $sale['profit'] }}</td>
            <td>{{ $sale['status'] }}</td>
            <td>{{ $sale['payment_status'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>