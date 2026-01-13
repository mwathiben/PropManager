<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Expenses Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #DC2626;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #DC2626;
            font-size: 22px;
        }
        .meta {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-bottom: 15px;
        }
        .summary {
            background-color: #FEF2F2;
            border: 1px solid #FECACA;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #DC2626;
        }
        .summary-label {
            font-size: 10px;
            color: #666;
        }
        .category-breakdown {
            margin-bottom: 20px;
        }
        .category-breakdown h3 {
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
        .category-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
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
        <h1>Expenses Report</h1>
        <p>{{ $landlord->name ?? 'Property Manager' }}</p>
    </div>

    <div class="meta">
        @if(isset($filters['category_id']) && $filters['category_id'])
            <p><strong>Category:</strong> {{ $filters['category_name'] ?? 'Selected Category' }}</p>
        @endif
        @if(isset($filters['vendor_id']) && $filters['vendor_id'])
            <p><strong>Vendor:</strong> {{ $filters['vendor_name'] ?? 'Selected Vendor' }}</p>
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
                <td style="border: none; text-align: center; width: 25%;">
                    <div class="summary-value">{{ $summary['total_count'] }}</div>
                    <div class="summary-label">Total Expenses</div>
                </td>
                <td style="border: none; text-align: center; width: 25%;">
                    <div class="summary-value">KES {{ number_format($summary['total_amount'], 0) }}</div>
                    <div class="summary-label">Total Spent</div>
                </td>
                <td style="border: none; text-align: center; width: 25%;">
                    <div class="summary-value">KES {{ number_format($summary['average_expense'], 0) }}</div>
                    <div class="summary-label">Average Expense</div>
                </td>
                <td style="border: none; text-align: center; width: 25%;">
                    <div class="summary-value">{{ $summary['recurring_count'] }}</div>
                    <div class="summary-label">Recurring</div>
                </td>
            </tr>
        </table>
    </div>

    @if(count($category_breakdown) > 0)
    <div class="category-breakdown">
        <h3>Expense by Category</h3>
        <table style="width: 60%;">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Amount (KES)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($category_breakdown as $category)
                <tr>
                    <td>
                        <span class="category-dot" style="background-color: {{ $category['color'] }}"></span>
                        {{ $category['name'] }}
                    </td>
                    <td class="text-right">{{ $category['count'] }}</td>
                    <td class="text-right">{{ number_format($category['amount'], 2) }}</td>
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
                <th>Description</th>
                <th>Category</th>
                <th>Vendor</th>
                <th>Location</th>
                <th class="text-right">Amount</th>
                <th>Method</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $expense)
            <tr>
                <td>{{ $expense->expense_date?->format('M j, Y') }}</td>
                <td>{{ $expense->description }}</td>
                <td>{{ $expense->category?->name ?? 'Uncategorized' }}</td>
                <td>{{ $expense->vendor?->name ?? '-' }}</td>
                <td>{{ $expense->getLocationLabel() }}</td>
                <td class="text-right">{{ number_format($expense->amount, 2) }}</td>
                <td>{{ $expense->payment_method ? ucfirst(str_replace('_', ' ', $expense->payment_method)) : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generated by PropManager - Property Management System</p>
        <p>Total {{ count($expenses) }} expenses</p>
    </div>
</body>
</html>
