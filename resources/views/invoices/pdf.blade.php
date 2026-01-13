<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
        }
        .container {
            padding: 40px;
        }
        .header {
            border-top: 4px solid {{ $template?->primary_color ?? '#4F46E5' }};
            padding-top: 20px;
            margin-bottom: 30px;
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
        .invoice-info {
            display: table-cell;
            width: 40%;
            text-align: right;
            vertical-align: top;
        }
        .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #111;
            margin-bottom: 5px;
        }
        .company-details {
            color: #666;
            font-size: 11px;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: {{ $template?->primary_color ?? '#4F46E5' }};
            margin-bottom: 10px;
        }
        .invoice-number {
            font-size: 11px;
            color: #666;
        }
        .due-date {
            color: #dc2626;
            font-weight: bold;
        }
        .bill-to-section {
            background-color: #f9fafb;
            padding: 20px;
            margin-bottom: 30px;
        }
        .bill-to-content {
            display: table;
            width: 100%;
        }
        .bill-to-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .section-label {
            font-size: 10px;
            font-weight: bold;
            color: #9ca3af;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .tenant-name {
            font-weight: bold;
            color: #111;
            margin-bottom: 3px;
        }
        .tenant-details {
            color: #666;
            font-size: 11px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            padding: 12px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            color: #6b7280;
        }
        .items-table th.text-right {
            text-align: right;
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .items-table td.text-right {
            text-align: right;
        }
        .items-table .total-row td {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #e5e7eb;
            border-bottom: none;
        }
        .items-table .total-amount {
            color: {{ $template?->primary_color ?? '#4F46E5' }};
        }
        .late-warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 11px;
            color: #92400e;
        }
        .bank-details {
            background-color: #f9fafb;
            padding: 20px;
            margin-bottom: 20px;
        }
        .bank-details-title {
            font-size: 10px;
            font-weight: bold;
            color: #9ca3af;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .bank-details-content {
            font-size: 11px;
            color: #666;
        }
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            margin-top: 30px;
        }
        .footer-text {
            font-size: 11px;
            color: #9ca3af;
            text-align: center;
        }
        .custom-text {
            font-size: 11px;
            color: #666;
            font-style: italic;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="company-info">
                    @if($template?->show_logo && $logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo" class="logo">
                    @endif
                    <div class="company-name">{{ $settings?->business_name ?? 'Business Name' }}</div>
                    <div class="company-details">
                        @if($settings?->business_address)
                            {{ $settings->business_address }}<br>
                        @endif
                        @if($settings?->business_phone)
                            {{ $settings->business_phone }}<br>
                        @endif
                        @if($settings?->business_email)
                            {{ $settings->business_email }}<br>
                        @endif
                        @if($template?->show_tax_number && $settings?->tax_number)
                            Tax No: {{ $settings->tax_number }}
                        @endif
                    </div>
                </div>
                <div class="invoice-info">
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-number">
                        #{{ $invoice->invoice_number }}<br>
                        Date: {{ $invoice->created_at->format('M d, Y') }}<br>
                        @if($template?->show_due_date)
                            <span class="due-date">Due: {{ $invoice->due_date->format('M d, Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @if($template?->custom_header)
                <div class="custom-text">{{ $template->custom_header }}</div>
            @endif
        </div>

        <div class="bill-to-section">
            <div class="bill-to-content">
                <div class="bill-to-column">
                    <div class="section-label">Bill To</div>
                    <div class="tenant-name">{{ $tenant['name'] }}</div>
                    <div class="tenant-details">
                        {{ $tenant['email'] }}<br>
                        @if($tenant['phone'])
                            {{ $tenant['phone'] }}<br>
                        @endif
                        @if($template?->show_tenant_id && $tenant['national_id'])
                            ID: {{ $tenant['national_id'] }}
                        @endif
                    </div>
                </div>
                @if($template?->show_unit_details)
                    <div class="bill-to-column">
                        <div class="section-label">Property</div>
                        <div class="tenant-name">{{ $unit['name'] }}</div>
                        <div class="tenant-details">
                            {{ $unit['building'] }}<br>
                            {{ $unit['property'] }}<br>
                            @if($template?->show_lease_reference)
                                Lease: {{ $lease['reference'] }}
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td class="text-right">KES {{ number_format($item['total'], 2) }}</td>
                    </tr>
                @endforeach
                @if($wallet_applied > 0)
                    <tr>
                        <td>Wallet Credit Applied</td>
                        <td class="text-right">- KES {{ number_format($wallet_applied, 2) }}</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td>Total Due</td>
                    <td class="text-right total-amount">KES {{ number_format($total_due, 2) }}</td>
                </tr>
                @if($amount_paid > 0)
                    <tr>
                        <td>Amount Paid</td>
                        <td class="text-right">- KES {{ number_format($amount_paid, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>Balance Due</td>
                        <td class="text-right total-amount">KES {{ number_format($balance_due, 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @if($template?->show_late_warning && $settings?->late_penalty_percentage > 0)
            <div class="late-warning">
                A late fee of {{ $settings->late_penalty_percentage }}% will be applied after {{ $settings->grace_period_days ?? 0 }} days past the due date.
            </div>
        @endif

        @if($template?->show_bank_details && $settings?->bank_name)
            <div class="bank-details">
                <div class="bank-details-title">Payment Details</div>
                <div class="bank-details-content">
                    Bank: {{ $settings->bank_name }}<br>
                    Account Name: {{ $settings->bank_account_name }}<br>
                    Account Number: {{ $settings->bank_account_number }}<br>
                    @if($settings->bank_branch)
                        Branch: {{ $settings->bank_branch }}<br>
                    @endif
                    @if($settings->bank_swift_code)
                        SWIFT: {{ $settings->bank_swift_code }}
                    @endif
                </div>
            </div>
        @endif

        @if($template?->show_payment_instructions && $settings?->terms_and_conditions)
            <div class="custom-text">
                {{ $settings->terms_and_conditions }}
            </div>
        @endif

        <div class="footer">
            @if($template?->custom_footer)
                <div class="custom-text" style="text-align: center;">{{ $template->custom_footer }}</div>
            @endif
            @if($template?->show_footer && $settings?->footer_note)
                <div class="footer-text">{{ $settings->footer_note }}</div>
            @endif
        </div>
    </div>
</body>
</html>
