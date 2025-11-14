<table>
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
        @foreach($reportData['customers'] as $customer)
        <tr>
            <td>{{ $customer['name'] }}</td>
            <td>{{ $customer['email'] }}</td>
            <td>{{ $customer['phone'] }}</td>
            <td>{{ $customer['company'] ?? '-' }}</td>
            <td>{{ $customer['loyalty_points'] }}</td>
            <td>{{ $customer['balance'] }}</td>
            <td>{{ $customer['total_spent'] }}</td>
            <td>{{ $customer['total_orders'] }}</td>
            <td>{{ $customer['avg_order_value'] }}</td>
            <td>{{ $customer['first_purchase'] ?? '-' }}</td>
            <td>{{ $customer['last_purchase'] ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>