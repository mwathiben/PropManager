<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payments Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #059669;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #059669;
            font-size: 22px;
        }
        .meta {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-bottom: 15px;
        }
        .summary {
            background-color: #F0FDF4;
            border: 1px solid #BBF7D0;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #059669;
        }
        .summary-label {
            font-size: 10px;
            color: #666;
        }
        .method-breakdown {
            margin-bottom: 20px;
        }
        .method-breakdown h3 {
            font-size: 12px;
            margin-bottom: 10px;
            color: #374151;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
            font-size: 10px;
        }
        th {
            background-color: #F9FAFB;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #E5E7EB;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Payments Report</h1>
        <p>{{ $landlord->name ?? 'Property Manager' }}</p>
    </div>

    <div class="meta">
        @if(isset($filters['method']) && $filters['method'])
            <p><strong>Payment Method:</strong> {{ ucfirst(str_replace('_', ' ', $filters['method'])) }}</p>
        @endif
        @if(isset($filters['date_from']) && $filters['date_from'])
            <p><strong>From:</strong> {{ \Carbon\Carbon::parse($filters['date_from'])->format('M j, Y') }}</p>
        @endif
        @if(isset($filters['date_to']) && $filters['date_to'])
            <p><strong>To:</strong> {{ \Carbon\Carbon::parse($filters['date_to'])->format('M j, Y') }}</p>
        @endif
        <p><strong>Generated:</strong> {{ $generated_at }}</p>
    </div>

    <div class="summary">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="border: none; text-align: center; width: 33%;">
                    <div class="summary-value">{{ $summary['total_count'] }}</div>
                    <div class="summary-label">Total Payments</div>
                </td>
                <td style="border: none; text-align: center; width: 34%;">
                    <div class="summary-value">{{ $currency_symbol }} {{ number_format($summary['total_amount'], 0) }}</div>
                    <div class="summary-label">Total Collected</div>
                </td>
                <td style="border: none; text-align: center; width: 33%;">
                    <div class="summary-value">{{ $currency_symbol }} {{ number_format($summary['average_payment'], 0) }}</div>
                    <div class="summary-label">Average Payment</div>
                </td>
            </tr>
        </table>
    </div>

    @if(count($method_breakdown) > 0)
    <div class="method-breakdown">
        <h3>Payment Method Breakdown</h3>
        <table style="width: 50%;">
            <thead>
                <tr>
                    <th>Method</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Amount ({{ $currency_code }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($method_breakdown as $method => $data)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $method)) }}</td>
                    <td class="text-right">{{ $data['count'] }}</td>
                    <td class="text-right">{{ number_format($data['amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Tenant</th>
                <th>Unit</th>
                <th>Building</th>
                <th class="text-right">Amount</th>
                <th>Method</th>
                <th>Invoice</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>{{ $payment->payment_date?->format('M j, Y') }}</td>
                <td>{{ $payment->reference ?? '-' }}</td>
                <td>{{ $payment->lease->tenant->name ?? 'N/A' }}</td>
                <td>{{ $payment->lease->unit->unit_number ?? 'N/A' }}</td>
                <td>{{ $payment->lease->unit->building->name ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($payment->amount, 2) }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                <td>{{ $payment->invoice->invoice_number ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generated by PropManager - Property Management System</p>
        <p>Total {{ count($payments) }} payments</p>
    </div>
</body>
</html>
