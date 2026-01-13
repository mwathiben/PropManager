<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #4F46E5;
            font-size: 24px;
        }
        .meta {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            background-color: #F3F4F6;
            padding: 8px;
            margin: 0 0 15px 0;
            font-size: 14px;
            border-left: 4px solid #4F46E5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        th {
            background-color: #F9FAFB;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-green {
            color: #059669;
        }
        .text-red {
            color: #DC2626;
        }
        .total-row {
            font-weight: bold;
            background-color: #F9FAFB;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #E5E7EB;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Financial Report</h1>
        <p>{{ $landlord->name }}</p>
    </div>

    <div class="meta">
        <p><strong>Period:</strong> {{ ucfirst($data['period']) }}</p>
        <p><strong>Date Range:</strong> {{ \Carbon\Carbon::parse($data['date_range']['start'])->format('M j, Y') }} - {{ \Carbon\Carbon::parse($data['date_range']['end'])->format('M j, Y') }}</p>
        <p><strong>Generated:</strong> {{ $generated_at }}</p>
    </div>

    <div class="section">
        <h2>Financial Summary</h2>
        <table>
            <tr>
                <th>Metric</th>
                <th class="text-right">Amount (KES)</th>
            </tr>
            <tr>
                <td>Expected Rent</td>
                <td class="text-right">{{ number_format($data['summary']['expected_rent'], 2) }}</td>
            </tr>
            <tr>
                <td>Collected Rent</td>
                <td class="text-right text-green">{{ number_format($data['summary']['collected_rent'], 2) }}</td>
            </tr>
            <tr>
                <td>Water Charges</td>
                <td class="text-right">{{ number_format($data['summary']['water_charges'], 2) }}</td>
            </tr>
            <tr>
                <td>Outstanding Amount</td>
                <td class="text-right text-red">{{ number_format($data['summary']['outstanding'], 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Total Revenue</td>
                <td class="text-right">{{ number_format($data['summary']['total_revenue'], 2) }}</td>
            </tr>
        </table>

        <p><strong>Collection Rate:</strong> <span class="text-green">{{ $data['summary']['collection_percentage'] }}%</span></p>
    </div>

    <div class="section">
        <h2>Revenue Breakdown</h2>
        <table>
            <tr>
                <th>Category</th>
                <th class="text-right">Amount (KES)</th>
                <th class="text-right">Percentage</th>
            </tr>
            @foreach($data['summary']['revenue_breakdown'] as $category => $amount)
            <tr>
                <td>{{ ucfirst($category) }}</td>
                <td class="text-right">{{ number_format($amount, 2) }}</td>
                <td class="text-right">{{ $data['summary']['total_revenue'] > 0 ? round(($amount / $data['summary']['total_revenue']) * 100, 1) : 0 }}%</td>
            </tr>
            @endforeach
        </table>
    </div>

    @if(count($data['revenue_trend']) > 0)
    <div class="section">
        <h2>Revenue Trend</h2>
        <table>
            <tr>
                <th>Date</th>
                <th class="text-right">Amount (KES)</th>
                <th class="text-right">Transactions</th>
            </tr>
            @foreach($data['revenue_trend'] as $trend)
            <tr>
                <td>{{ $trend['date'] }}</td>
                <td class="text-right">{{ number_format($trend['amount'], 2) }}</td>
                <td class="text-right">{{ $trend['count'] }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <div class="section">
        <h2>Collection Performance</h2>
        <table>
            <tr>
                <th>Metric</th>
                <th class="text-right">Value</th>
            </tr>
            <tr>
                <td>Total Billed</td>
                <td class="text-right">KES {{ number_format($data['collection_rate']['total_billed'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Collected</td>
                <td class="text-right text-green">KES {{ number_format($data['collection_rate']['total_collected'], 2) }}</td>
            </tr>
            <tr>
                <td>Collection Rate</td>
                <td class="text-right">{{ $data['collection_rate']['collection_rate'] }}%</td>
            </tr>
            <tr>
                <td>Paid Invoices</td>
                <td class="text-right">{{ $data['collection_rate']['paid_count'] }}</td>
            </tr>
            <tr>
                <td>Partial Payments</td>
                <td class="text-right">{{ $data['collection_rate']['partial_count'] }}</td>
            </tr>
            <tr>
                <td>Overdue Invoices</td>
                <td class="text-right text-red">{{ $data['collection_rate']['overdue_count'] }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Generated by PropManager - Property Management System</p>
        <p>This is a computer-generated document. No signature required.</p>
    </div>
</body>
</html>
