<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    @php
        $design = $template?->design ?? 'classic';
        $primary = $template?->primary_color ?? '#4F46E5';
        $secondary = $template?->secondary_color ?? '#6366F1';

        // Law Firm/Financial colors for professional design
        $navy = '#1e3a5f';
        $charcoal = '#374151';
        $gold = '#b8860b';
    @endphp
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

        /* Header Styles */
        .header {
            padding-top: 20px;
            margin-bottom: 30px;
            @if($design === 'professional')
                background-color: {{ $navy }};
                margin: -40px -40px 30px -40px;
                padding: 30px 40px;
            @else
                border-top: 4px solid {{ $primary }};
            @endif
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
            margin-bottom: 5px;
            @if($design === 'professional')
                font-family: Georgia, 'Times New Roman', serif;
                color: #ffffff;
                letter-spacing: 0.02em;
            @else
                color: #111;
            @endif
        }
        .company-details {
            font-size: 11px;
            @if($design === 'professional')
                color: rgba(255, 255, 255, 0.8);
            @else
                color: #666;
            @endif
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            @if($design === 'professional')
                font-family: Georgia, 'Times New Roman', serif;
                color: {{ $gold }};
                letter-spacing: 0.1em;
            @else
                color: {{ $primary }};
            @endif
        }
        .invoice-number {
            font-size: 11px;
            @if($design === 'professional')
                color: rgba(255, 255, 255, 0.8);
            @else
                color: #666;
            @endif
        }
        .due-date {
            @if($design === 'professional')
                color: {{ $gold }};
            @else
                color: #dc2626;
            @endif
            font-weight: bold;
        }

        /* Gold Accent Line */
        @if($design === 'professional')
        .gold-accent {
            height: 2px;
            background: linear-gradient(90deg, transparent 0%, {{ $gold }} 50%, transparent 100%);
            margin: 0 0 24px 0;
        }
        @endif

        /* Bill To Section */
        .bill-to-section {
            padding: 20px;
            margin-bottom: 30px;
            @if($design === 'professional')
                background-color: #f8fafc;
                border-left: 4px solid {{ $navy }};
            @else
                background-color: #f9fafb;
            @endif
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
            text-transform: uppercase;
            margin-bottom: 5px;
            @if($design === 'professional')
                font-family: Georgia, 'Times New Roman', serif;
                color: {{ $navy }};
                letter-spacing: 0.05em;
            @else
                color: #9ca3af;
            @endif
        }
        .tenant-name {
            font-weight: bold;
            margin-bottom: 3px;
            @if($design === 'professional')
                color: {{ $navy }};
            @else
                color: #111;
            @endif
        }
        .tenant-details {
            font-size: 11px;
            @if($design === 'professional')
                color: {{ $charcoal }};
            @else
                color: #666;
            @endif
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            padding: 12px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            @if($design === 'professional')
                background-color: {{ $navy }};
                color: #ffffff;
                border-bottom: none;
                letter-spacing: 0.03em;
            @else
                background-color: #f9fafb;
                border-bottom: 2px solid #e5e7eb;
                color: #6b7280;
            @endif
        }
        .items-table th.text-right {
            text-align: right;
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            @if($design === 'professional')
                color: {{ $charcoal }};
            @endif
        }
        .items-table td.text-right {
            text-align: right;
        }
        .items-table .total-row td {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #e5e7eb;
            border-bottom: none;
            @if($design === 'professional')
                font-family: Georgia, 'Times New Roman', serif;
                font-size: 16px;
            @endif
        }
        .items-table .total-amount {
            @if($design === 'professional')
                color: {{ $navy }};
            @else
                color: {{ $primary }};
            @endif
        }

        /* Late Warning */
        .late-warning {
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 11px;
            @if($design === 'professional')
                background-color: #f8fafc;
                border-left: 4px solid {{ $gold }};
                color: {{ $charcoal }};
            @else
                background-color: #fef3c7;
                border-left: 4px solid #f59e0b;
                color: #92400e;
            @endif
        }

        /* Bank Details */
        .bank-details {
            padding: 20px;
            margin-bottom: 20px;
            @if($design === 'professional')
                background-color: #f8fafc;
                border-left: 2px solid {{ $gold }};
            @else
                background-color: #f9fafb;
            @endif
        }
        .bank-details-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            @if($design === 'professional')
                font-family: Georgia, 'Times New Roman', serif;
                color: {{ $navy }};
            @else
                color: #9ca3af;
            @endif
        }
        .bank-details-content {
            font-size: 11px;
            @if($design === 'professional')
                color: {{ $charcoal }};
            @else
                color: #666;
            @endif
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            @if($design === 'professional')
                background-color: {{ $navy }};
                margin: 30px -40px -40px -40px;
                padding: 20px 40px;
            @else
                border-top: 1px solid #e5e7eb;
                padding-top: 20px;
            @endif
        }
        .footer-text {
            font-size: 11px;
            text-align: center;
            @if($design === 'professional')
                color: rgba(255, 255, 255, 0.8);
            @else
                color: #9ca3af;
            @endif
        }
        .custom-text {
            font-size: 11px;
            font-style: italic;
            margin: 10px 0;
            @if($design === 'professional')
                color: {{ $charcoal }};
            @else
                color: #666;
            @endif
        }
        .custom-text-footer {
            font-size: 11px;
            font-style: italic;
            margin: 10px 0;
            text-align: center;
            @if($design === 'professional')
                color: rgba(255, 255, 255, 0.7);
            @else
                color: #666;
            @endif
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
                <div class="custom-text" @if($design === 'professional') style="color: rgba(255,255,255,0.7);" @endif>{{ $template->custom_header }}</div>
            @endif
        </div>

        @if($design === 'professional')
            <div class="gold-accent"></div>
        @endif

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
                            @if($template?->show_lease_reference && $lease)
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
                        <td class="text-right">{{ $currency_symbol }} {{ number_format($item['total'], 2) }}</td>
                    </tr>
                @endforeach
                @if($wallet_applied > 0)
                    <tr>
                        <td>Wallet Credit Applied</td>
                        <td class="text-right">- {{ $currency_symbol }} {{ number_format($wallet_applied, 2) }}</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td>Total Due</td>
                    <td class="text-right total-amount">{{ $currency_symbol }} {{ number_format($total_due, 2) }}</td>
                </tr>
                @if($amount_paid > 0)
                    <tr>
                        <td>Amount Paid</td>
                        <td class="text-right">- {{ $currency_symbol }} {{ number_format($amount_paid, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>Balance Due</td>
                        <td class="text-right total-amount">{{ $currency_symbol }} {{ number_format($balance_due, 2) }}</td>
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
                <div class="custom-text-footer">{{ $template->custom_footer }}</div>
            @endif
            @if($template?->show_footer && $settings?->footer_note)
                <div class="footer-text">{{ $settings->footer_note }}</div>
            @endif
        </div>
    </div>
</body>
</html>
