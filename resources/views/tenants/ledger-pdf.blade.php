<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Account Statement - {{ $tenant->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }
        .container {
            padding: 30px;
        }
        .header {
            border-top: 4px solid #059669;
            padding-top: 15px;
            margin-bottom: 25px;
        }
        .header-content {
            display: table;
            width: 100%;
        }
        .company-info {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }
        .statement-info {
            display: table-cell;
            width: 40%;
            text-align: right;
            vertical-align: top;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #111;
            margin-bottom: 5px;
        }
        .company-details {
            color: #666;
            font-size: 10px;
        }
        .statement-title {
            font-size: 24px;
            font-weight: bold;
            color: #059669;
            margin-bottom: 5px;
        }
        .statement-date {
            font-size: 10px;
            color: #666;
        }
        .tenant-section {
            background-color: #f9fafb;
            padding: 15px;
            margin-bottom: 20px;
        }
        .tenant-content {
            display: table;
            width: 100%;
        }
        .tenant-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .section-label {
            font-size: 9px;
            font-weight: bold;
            color: #9ca3af;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .tenant-name {
            font-weight: bold;
            color: #111;
            margin-bottom: 3px;
        }
        .tenant-details {
            color: #666;
            font-size: 10px;
        }
        .summary-section {
            margin-bottom: 20px;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-box {
            display: table-cell;
            width: 25%;
            padding: 10px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .summary-label {
            font-size: 9px;
            color: #6b7280;
            margin-bottom: 3px;
        }
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #111;
        }
        .summary-value.debit {
            color: #dc2626;
        }
        .summary-value.credit {
            color: #059669;
        }
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .transactions-table th {
            background-color: #f3f4f6;
            padding: 8px 6px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
        }
        .transactions-table th.right {
            text-align: right;
        }
        .transactions-table td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .transactions-table td.right {
            text-align: right;
        }
        .type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .type-invoice {
            background-color: #fef3c7;
            color: #92400e;
        }
        .type-payment {
            background-color: #d1fae5;
            color: #065f46;
        }
        .type-refund {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .debit {
            color: #dc2626;
        }
        .credit {
            color: #059669;
        }
        .mono {
            font-family: monospace;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="company-info">
                    <div class="company-name">{{ $invoiceSetting->business_name ?? $landlord->name ?? 'Property Management' }}</div>
                    <div class="company-details">
                        @if($invoiceSetting->business_address)
                            {{ $invoiceSetting->business_address }}<br>
                        @endif
                        @if($invoiceSetting->business_phone)
                            Tel: {{ $invoiceSetting->business_phone }}<br>
                        @endif
                        @if($invoiceSetting->business_email)
                            {{ $invoiceSetting->business_email }}
                        @endif
                    </div>
                </div>
                <div class="statement-info">
                    <div class="statement-title">STATEMENT</div>
                    <div class="statement-date">
                        Generated: {{ $generatedAt->format('d M Y') }}<br>
                        @if($dateFrom || $dateTo)
                            Period: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'Start' }}
                            - {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : 'Present' }}
                        @else
                            Period: All Time
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="tenant-section">
            <div class="tenant-content">
                <div class="tenant-column">
                    <div class="section-label">Account Holder</div>
                    <div class="tenant-name">{{ $tenant->name }}</div>
                    <div class="tenant-details">
                        {{ $tenant->email }}<br>
                        {{ $tenant->mobile_number }}
                    </div>
                </div>
                <div class="tenant-column">
                    @if($activeLease)
                        <div class="section-label">Property</div>
                        <div class="tenant-details">
                            Unit {{ $activeLease->unit->unit_number }}<br>
                            {{ $activeLease->unit->building->name }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="summary-section">
            <div class="summary-grid">
                <div class="summary-box">
                    <div class="summary-label">Total Invoiced</div>
                    <div class="summary-value">{{ $currency_symbol }} {{ number_format($summary['total_invoiced'], 2) }}</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Total Paid</div>
                    <div class="summary-value credit">{{ $currency_symbol }} {{ number_format($summary['total_paid'], 2) }}</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Refunds</div>
                    <div class="summary-value debit">{{ $currency_symbol }} {{ number_format($summary['total_refunds'], 2) }}</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Balance {{ $summary['current_balance'] > 0 ? 'Due' : '' }}</div>
                    <div class="summary-value {{ $summary['current_balance'] > 0 ? 'debit' : 'credit' }}">
                        {{ $currency_symbol }} {{ number_format(abs($summary['current_balance']), 2) }}
                    </div>
                </div>
            </div>
        </div>

        <table class="transactions-table">
            <thead>
                <tr>
                    <th style="width: 12%">Date</th>
                    <th style="width: 12%">Type</th>
                    <th style="width: 30%">Description</th>
                    <th style="width: 14%">Reference</th>
                    <th style="width: 11%" class="right">Debit</th>
                    <th style="width: 11%" class="right">Credit</th>
                    <th style="width: 10%" class="right">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $txn)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($txn['date'])->format('d M Y') }}</td>
                        <td>
                            <span class="type-badge type-{{ $txn['type'] }}">
                                {{ ucfirst($txn['type']) }}
                            </span>
                        </td>
                        <td>{{ $txn['description'] }}</td>
                        <td class="mono">{{ $txn['reference'] }}</td>
                        <td class="right">
                            @if($txn['debit'] > 0)
                                <span class="debit">{{ number_format($txn['debit'], 2) }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="right">
                            @if($txn['credit'] > 0)
                                <span class="credit">{{ number_format($txn['credit'], 2) }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="right">
                            <span class="{{ $txn['running_balance'] > 0 ? 'debit' : 'credit' }}">
                                {{ number_format(abs($txn['running_balance']), 2) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #9ca3af;">
                            No transactions found for this period.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            This statement was generated on {{ $generatedAt->format('d M Y \a\t H:i') }}.
            For queries, please contact {{ $invoiceSetting->business_email ?? $landlord->email ?? 'the property manager' }}.
        </div>
    </div>
</body>
</html>
