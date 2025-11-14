<table>
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
        @foreach($reportData['monthly_breakdown'] as $data)
        <tr>
            <td>{{ $data['month'] }}</td>
            <td>{{ $data['revenue'] }}</td>
            <td>{{ $data['profit'] }}</td>
            <td>{{ $data['sales_count'] }}</td>
            <td>{{ $data['avg_sale_value'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>