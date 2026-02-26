<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Financial Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #10B981;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            color: #10B981;
            font-size: 22px;
        }

        .meta {
            text-align: right;
            font-size: 9px;
            color: #666;
            margin-bottom: 15px;
        }

        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1F2937;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #E5E7EB;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th,
        td {
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
            font-size: 9px;
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

        .total-row {
            font-weight: bold;
            background-color: #F3F4F6;
        }

        .positive {
            color: #059669;
        }

        .negative {
            color: #DC2626;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
        }

        .badge-success {
            background-color: #D1FAE5;
            color: #059669;
        }

        .badge-warning {
            background-color: #FEF3C7;
            color: #D97706;
        }

        .badge-danger {
            background-color: #FEE2E2;
            color: #DC2626;
        }

        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 10px;
            border: 1px solid #E5E7EB;
        }

        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #1F2937;
        }

        .summary-label {
            font-size: 8px;
            color: #6B7280;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #E5E7EB;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Financial Report</h1>
        <p>{{ $landlord->name ?? 'Property Manager' }}</p>
        <p>Last {{ $period }} Months</p>
    </div>

    <div class="meta">
        <p><strong>Generated:</strong> {{ $generated_at }}</p>
    </div>

    @php
        $totalInvoiced = collect($data['revenue'])->sum('invoiced');
        $totalCollected = collect($data['revenue'])->sum('collected');
        $totalExpenses = collect($data['revenue'])->sum('expenses');
        $netIncome = $totalCollected - $totalExpenses;
    @endphp

    <div class="summary-grid">
        <div class="summary-item">
            <div class="summary-value">{{ $currency_symbol ?? '' }} {{ number_format($totalInvoiced, 0) }}</div>
            <div class="summary-label">Total Invoiced</div>
        </div>
        <div class="summary-item">
            <div class="summary-value" style="color: #10B981;">{{ $currency_symbol ?? '' }}
                {{ number_format($totalCollected, 0) }}</div>
            <div class="summary-label">Total Collected</div>
        </div>
        <div class="summary-item">
            <div class="summary-value" style="color: #DC2626;">{{ $currency_symbol ?? '' }}
                {{ number_format($totalExpenses, 0) }}</div>
            <div class="summary-label">Total Expenses</div>
        </div>
        <div class="summary-item">
            <div class="summary-value" style="color: {{ $netIncome >= 0 ? '#10B981' : '#DC2626' }};">
                {{ $currency_symbol ?? '' }} {{ number_format($netIncome, 0) }}</div>
            <div class="summary-label">Net Income</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Revenue & Expenses by Month</div>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-right">Invoiced</th>
                    <th class="text-right">Collected</th>
                    <th class="text-right">Expenses</th>
                    <th class="text-right">Net</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['revenue'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right">{{ $currency_symbol ?? '' }} {{ number_format($row['invoiced'], 0) }}
                        </td>
                        <td class="text-right">{{ $currency_symbol ?? '' }} {{ number_format($row['collected'], 0) }}
                        </td>
                        <td class="text-right">{{ $currency_symbol ?? '' }} {{ number_format($row['expenses'], 0) }}
                        </td>
                        <td class="text-right {{ $row['net'] >= 0 ? 'positive' : 'negative' }}">
                            {{ $currency_symbol ?? '' }} {{ number_format($row['net'], 0) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Collection Rate by Month</div>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-right">Invoiced</th>
                    <th class="text-right">Collected</th>
                    <th class="text-center">Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['collection_rate'] as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-right">{{ $currency_symbol ?? '' }} {{ number_format($row['invoiced'], 0) }}
                        </td>
                        <td class="text-right">{{ $currency_symbol ?? '' }} {{ number_format($row['collected'], 0) }}
                        </td>
                        <td class="text-center">
                            <span
                                class="badge {{ $row['rate'] >= 80 ? 'badge-success' : ($row['rate'] >= 60 ? 'badge-warning' : 'badge-danger') }}">
                                {{ $row['rate'] }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Occupancy by Building</div>
        <table>
            <thead>
                <tr>
                    <th>Building</th>
                    <th class="text-center">Total Units</th>
                    <th class="text-center">Occupied</th>
                    <th class="text-center">Vacant</th>
                    <th class="text-center">Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['occupancy']['buildings'] ?? [] as $building)
                    <tr>
                        <td>{{ $building['building'] }}</td>
                        <td class="text-center">{{ $building['total_units'] }}</td>
                        <td class="text-center">{{ $building['occupied'] }}</td>
                        <td class="text-center">{{ $building['vacant'] }}</td>
                        <td class="text-center">
                            <span
                                class="badge {{ $building['occupancy_rate'] >= 80 ? 'badge-success' : ($building['occupancy_rate'] >= 50 ? 'badge-warning' : 'badge-danger') }}">
                                {{ $building['occupancy_rate'] }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
                @if (isset($data['occupancy']['totals']))
                    <tr class="total-row">
                        <td>Total</td>
                        <td class="text-center">{{ $data['occupancy']['totals']['total_units'] }}</td>
                        <td class="text-center">{{ $data['occupancy']['totals']['occupied'] }}</td>
                        <td class="text-center">{{ $data['occupancy']['totals']['vacant'] }}</td>
                        <td class="text-center">{{ $data['occupancy']['totals']['occupancy_rate'] }}%</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Arrears Aging</div>
        <table>
            <thead>
                <tr>
                    <th>Aging Bucket</th>
                    <th class="text-center">Invoices</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $buckets = [
                        'current' => 'Current (Not Overdue)',
                        '1-30' => '1-30 Days Overdue',
                        '31-60' => '31-60 Days Overdue',
                        '61-90' => '61-90 Days Overdue',
                        '90+' => '90+ Days Overdue',
                    ];
                    $totalCount = 0;
                    $totalAmount = 0;
                @endphp
                @foreach ($buckets as $key => $label)
                    @php
                        $count = $data['arrears_aging'][$key]['count'] ?? 0;
                        $amount = $data['arrears_aging'][$key]['amount'] ?? 0;
                        $totalCount += $count;
                        $totalAmount += $amount;
                    @endphp
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="text-center">{{ $count }}</td>
                        <td class="text-right">{{ $currency_symbol ?? '' }} {{ number_format($amount, 0) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total</td>
                    <td class="text-center">{{ $totalCount }}</td>
                    <td class="text-right">{{ $currency_symbol ?? '' }} {{ number_format($totalAmount, 0) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if (!empty($data['expenses_by_category']['categories']))
        <div class="section">
            <div class="section-title">Expenses by Category</div>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="text-center">Count</th>
                        <th class="text-right">Amount</th>
                        <th class="text-center">%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['expenses_by_category']['categories'] as $category)
                        <tr>
                            <td>{{ $category['category'] }}</td>
                            <td class="text-center">{{ $category['count'] }}</td>
                            <td class="text-right">{{ $currency_symbol ?? '' }}
                                {{ number_format($category['amount'], 0) }}</td>
                            <td class="text-center">{{ $category['percentage'] }}%</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Total</td>
                        <td class="text-center"></td>
                        <td class="text-right">{{ $currency_symbol ?? '' }}
                            {{ number_format($data['expenses_by_category']['total'], 0) }}</td>
                        <td class="text-center">100%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        <p>Generated by {{ config('app.name') }} - Property Management System</p>
    </div>
</body>

</html>
