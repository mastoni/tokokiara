<!DOCTYPE html>
<html>
<head>
    <title>{{ ucfirst($reportType) }} Report</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        .summary-box { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .summary-box h3 { color: #007bff; margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ ucfirst($reportType) }} Report</h1>
            <p>Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>

        <div class="content">
            <div class="summary-box">
                <h3>Report Summary</h3>
                <p>Your {{ $reportType }} report has been generated and is attached to this email.</p>

                @if(isset($reportData['summary']))
                    <h4>Key Metrics:</h4>
                    <ul>
                        @foreach($reportData['summary'] as $key => $value)
                            <li><strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong>
                                @if(is_numeric($value))
                                    ${{ number_format($value, 2) }}
                                @else
                                    {{ $value }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if(isset($reportData['period']))
                    <p><strong>Report Period:</strong> {{ $reportData['period'] }}</p>
                @endif
            </div>

            <div class="summary-box">
                <h3>Next Steps</h3>
                <ul>
                    <li>Review the attached detailed report</li>
                    <li>Analyze trends and identify opportunities</li>
                    <li>Share insights with your team</li>
                    <li>Take action based on the findings</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated report from TokoKira POS System</p>
            <p>If you have any questions, please contact your system administrator</p>
        </div>
    </div>
</body>
</html>