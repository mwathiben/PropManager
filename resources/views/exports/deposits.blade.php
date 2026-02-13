<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Security Deposits Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #6366F1;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #6366F1;
            font-size: 22px;
        }
        .meta {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-bottom: 15px;
        }
        .summary {
            background-color: #EEF2FF;
            border: 1px solid #C7D2FE;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #6366F1;
        }
        .summary-label {
            font-size: 10px;
            color: #666;
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
        .status-held {
            color: #6366F1;
            font-weight: bold;
        }
        .status-refunded {
            color: #059669;
            font-weight: bold;
        }
        .status-forfeited {
            color: #DC2626;
            font-weight: bold;
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
        <h1>Security Deposits Report</h1>
        <p>{{ $landlord->name ?? 'Property Manager' }}</p>
    </div>

    <div class="meta">
        @if(isset($filters['status']) && $filters['status'])
            <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $filters['status'])) }}</p>
        @endif
        <p><strong>Generated:</strong> {{ $generated_at }}</p>
    </div>

    <div class="summary">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="border: none; text-align: center; width: 33%;">
                    <div class="summary-value">{{ $currency_symbol }} {{ number_format($stats['total_held'], 0) }}</div>
                    <div class="summary-label">Total Held ({{ $stats['count_held'] }})</div>
                </td>
                <td style="border: none; text-align: center; width: 33%;">
                    <div class="summary-value" style="color: #059669;">{{ $currency_symbol }} {{ number_format($stats['total_refunded'], 0) }}</div>
                    <div class="summary-label">Total Refunded ({{ $stats['count_refunded'] }})</div>
                </td>
                <td style="border: none; text-align: center; width: 34%;">
                    <div class="summary-value" style="color: #DC2626;">{{ $currency_symbol }} {{ number_format($stats['total_forfeited'], 0) }}</div>
                    <div class="summary-label">Total Forfeited ({{ $stats['count_forfeited'] }})</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tenant</th>
                <th>Unit</th>
                <th>Building</th>
                <th class="text-right">Deposit</th>
                <th class="text-center">Status</th>
                <th class="text-right">Refund</th>
                <th class="text-right">Deductions</th>
                <th>Processed</th>
            </tr>
        </thead>
        <tbody>
            @foreach($deposits as $lease)
            <tr>
                <td>{{ $lease->tenant?->name ?? 'N/A' }}</td>
                <td>{{ $lease->unit?->unit_number ?? 'N/A' }}</td>
                <td>{{ $lease->unit?->building?->name ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($lease->deposit_amount, 2) }}</td>
                <td class="text-center">
                    <span class="status-{{ $lease->deposit_status === 'held' ? 'held' : ($lease->deposit_status === 'forfeited' ? 'forfeited' : 'refunded') }}">
                        {{ ucfirst(str_replace('_', ' ', $lease->deposit_status)) }}
                    </span>
                </td>
                <td class="text-right">{{ $lease->deposit_refund_amount ? number_format($lease->deposit_refund_amount, 2) : '-' }}</td>
                <td class="text-right">{{ $lease->deposit_deductions ? number_format($lease->deposit_deductions, 2) : '-' }}</td>
                <td>{{ $lease->deposit_processed_at?->format('M j, Y') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generated by PropManager - Property Management System</p>
        <p>Total {{ count($deposits) }} deposits</p>
    </div>
</body>
</html>
