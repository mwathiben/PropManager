<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoices Report</title>
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
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 0 10px;
            border-right: 1px solid #BBF7D0;
        }
        .summary-item:last-child {
            border-right: none;
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
        .status-paid {
            color: #059669;
            font-weight: bold;
        }
        .status-partial {
            color: #D97706;
            font-weight: bold;
        }
        .status-overdue {
            color: #DC2626;
            font-weight: bold;
        }
        .status-sent, .status-draft {
            color: #6B7280;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #E5E7EB;
            padding-top: 10px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoices Report</h1>
        <p>{{ $landlord->name ?? 'Property Manager' }}</p>
    </div>

    <div class="meta">
        @if(isset($filters['status']) && $filters['status'])
            <p><strong>Status:</strong> {{ ucfirst($filters['status']) }}</p>
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
                <td style="border: none; text-align: center; width: 20%;">
                    <div class="summary-value">{{ $summary['total_count'] }}</div>
                    <div class="summary-label">Total Invoices</div>
                </td>
                <td style="border: none; text-align: center; width: 20%;">
                    <div class="summary-value">{{ $currency_symbol }} {{ number_format($summary['total_due'], 0) }}</div>
                    <div class="summary-label">Total Due</div>
                </td>
                <td style="border: none; text-align: center; width: 20%;">
                    <div class="summary-value">{{ $currency_symbol }} {{ number_format($summary['total_paid'], 0) }}</div>
                    <div class="summary-label">Total Paid</div>
                </td>
                <td style="border: none; text-align: center; width: 20%;">
                    <div class="summary-value">{{ $currency_symbol }} {{ number_format($summary['total_balance'], 0) }}</div>
                    <div class="summary-label">Outstanding</div>
                </td>
                <td style="border: none; text-align: center; width: 20%;">
                    <div class="summary-value">{{ $summary['collection_rate'] }}%</div>
                    <div class="summary-label">Collection Rate</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Due Date</th>
                <th>Tenant</th>
                <th>Unit</th>
                <th class="text-right">Total Due</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Balance</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
            <tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ $invoice->created_at?->format('M j, Y') }}</td>
                <td>{{ $invoice->due_date?->format('M j, Y') }}</td>
                <td>{{ $invoice->lease->tenant->name ?? 'N/A' }}</td>
                <td>{{ $invoice->lease->unit->unit_number ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($invoice->total_due, 2) }}</td>
                <td class="text-right">{{ number_format($invoice->amount_paid, 2) }}</td>
                <td class="text-right">{{ number_format($invoice->total_due - $invoice->amount_paid, 2) }}</td>
                <td class="text-center status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generated by PropManager - Property Management System</p>
        <p>Total {{ count($invoices) }} invoices</p>
    </div>
</body>
</html>
