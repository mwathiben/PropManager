<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt - {{ $receipt->receipt_number }}</title>
    <style>
        @php
            $primary = $template->primary_color ?? '#059669';
            $secondary = $template->secondary_color ?? '#10B981';
            $design = $template->design ?? 'classic';

            // Law Firm/Financial colors for professional design
            $navy = '#1e3a5f';
            $charcoal = '#374151';
            $gold = '#b8860b';
        @endphp

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #374151;
            background: #fff;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            @if($design === 'classic')
                border: 2px solid #9CA3AF;
            @elseif($design === 'modern')
                border-radius: 24px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            @elseif($design === 'minimal')
                border: 1px solid #E5E7EB;
                border-radius: 8px;
            @elseif($design === 'professional')
                border: 1px solid {{ $navy }};
                border-top: 4px solid {{ $navy }};
            @endif
        }

        .header {
            padding-bottom: 24px;
            margin-bottom: 24px;
            @if($design === 'classic')
                border-bottom: 2px solid #9CA3AF;
                background: #F3F4F6;
                margin: -40px -40px 24px;
                padding: 30px 40px;
            @elseif($design === 'modern')
                background: linear-gradient(135deg, #F9FAFB 0%, #FFFFFF 50%, #F9FAFB 100%);
                margin: -40px -40px 24px;
                padding: 30px 40px;
                border-radius: 24px 24px 0 0;
            @elseif($design === 'minimal')
                text-align: center;
            @elseif($design === 'professional')
                background: {{ $navy }};
                margin: -40px -40px 24px;
                padding: 30px 40px;
            @endif
        }

        .header-content {
            @if($design !== 'minimal')
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
            @endif
        }

        .logo {
            max-width: 120px;
            max-height: 60px;
            margin-bottom: 12px;
        }

        .business-info h2 {
            font-size: 18px;
            font-weight: 600;
            @if($design === 'professional')
                font-family: Georgia, 'Times New Roman', Times, serif;
                color: #FFFFFF;
                letter-spacing: 0.02em;
            @else
                color: #1F2937;
            @endif
        }

        .business-info p {
            font-size: 11px;
            @if($design === 'professional')
                color: rgba(255, 255, 255, 0.8);
            @else
                color: #6B7280;
            @endif
        }

        .receipt-title {
            @if($design === 'minimal')
                margin-top: 16px;
            @else
                text-align: right;
            @endif
        }

        .receipt-title h1 {
            font-size: 24px;
            font-weight: bold;
            @if($design === 'professional')
                font-family: Georgia, 'Times New Roman', Times, serif;
                color: {{ $gold }};
                letter-spacing: 0.1em;
            @else
                color: {{ $primary }};
            @endif
        }

        .receipt-title p {
            font-size: 11px;
            @if($design === 'professional')
                color: rgba(255, 255, 255, 0.8);
            @else
                color: #6B7280;
            @endif
        }

        .custom-header {
            margin-top: 12px;
            font-size: 11px;
            font-style: italic;
            @if($design === 'professional')
                color: rgba(255, 255, 255, 0.7);
            @else
                color: #6B7280;
            @endif
        }

        .payment-box {
            padding: 20px;
            margin: 24px 0;
            @if($design === 'classic')
                background: {{ $secondary }}15;
                border: 2px solid {{ $secondary }};
            @elseif($design === 'modern')
                background: {{ $secondary }}15;
                border-radius: 16px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            @elseif($design === 'minimal')
                background: {{ $secondary }}15;
                border-radius: 8px;
                text-align: center;
            @elseif($design === 'professional')
                background: #F8FAFC;
                border-left: 4px solid {{ $navy }};
            @endif
        }

        .payment-box-content {
            @if($design !== 'minimal')
                display: flex;
                align-items: center;
                gap: 16px;
            @endif
        }

        .checkmark {
            width: 40px;
            height: 40px;
            @if($design === 'professional')
                color: {{ $navy }};
            @else
                color: {{ $primary }};
            @endif
        }

        .payment-label {
            font-size: 12px;
            font-weight: 500;
            color: #6B7280;
        }

        .payment-amount {
            font-size: 28px;
            font-weight: bold;
            @if($design === 'professional')
                font-family: Georgia, 'Times New Roman', Times, serif;
                color: {{ $navy }};
            @elseif($design === 'minimal')
                color: {{ $primary }};
                font-weight: 300;
                font-size: 32px;
            @else
                color: {{ $primary }};
            @endif
        }

        .section {
            margin: 20px 0;
            padding: 16px 0;
        }

        .section-title {
            margin-bottom: 8px;
            @if($design === 'classic')
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #4B5563;
                border-bottom: 2px solid #D1D5DB;
                padding-bottom: 4px;
            @elseif($design === 'modern')
                font-size: 10px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                color: #9CA3AF;
            @elseif($design === 'minimal')
                font-size: 10px;
                color: #9CA3AF;
                font-weight: 300;
            @elseif($design === 'professional')
                font-family: Georgia, 'Times New Roman', Times, serif;
                font-size: 11px;
                font-weight: 600;
                color: {{ $navy }};
                border-bottom: 1px solid {{ $navy }}40;
                padding-bottom: 4px;
            @endif
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            @if($design === 'minimal')
                text-align: center;
                grid-template-columns: 1fr;
                gap: 12px;
            @endif
        }

        .info-item label {
            display: block;
            font-size: 10px;
            color: #9CA3AF;
            margin-bottom: 2px;
        }

        .info-item span {
            font-weight: 500;
            @if($design === 'professional')
                color: {{ $charcoal }};
            @else
                color: #1F2937;
            @endif
        }

        .tenant-property-section {
            padding: 16px;
            @if($design === 'classic')
                background: #F3F4F6;
                border-top: 2px solid #D1D5DB;
                border-bottom: 2px solid #D1D5DB;
            @elseif($design === 'modern')
                background: linear-gradient(90deg, #F9FAFB 0%, #FFFFFF 100%);
                border-radius: 12px;
                margin: 0 8px;
            @elseif($design === 'minimal')
                border-bottom: 1px solid #F3F4F6;
                text-align: center;
            @elseif($design === 'professional')
                background: #F8FAFC;
                border-left: 2px solid {{ $gold }};
            @endif
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .invoice-table th,
        .invoice-table td {
            padding: 8px 12px;
            text-align: left;
            font-size: 11px;
        }

        .invoice-table th {
            @if($design === 'classic')
                background: #E5E7EB;
                font-weight: bold;
                border: 1px solid #D1D5DB;
            @elseif($design === 'modern')
                background: #F9FAFB;
                font-weight: 500;
                border-radius: 8px 8px 0 0;
            @elseif($design === 'minimal')
                font-weight: 400;
                color: #9CA3AF;
                border-bottom: 1px solid #F3F4F6;
            @elseif($design === 'professional')
                background: {{ $navy }};
                color: #FFFFFF;
                font-weight: 500;
                font-size: 10px;
                letter-spacing: 0.03em;
            @endif
        }

        .invoice-table td {
            @if($design === 'classic')
                border: 1px solid #E5E7EB;
            @elseif($design === 'minimal')
                border-bottom: 1px solid #F9FAFB;
            @elseif($design === 'professional')
                border-bottom: 1px solid #E5E7EB;
                color: {{ $charcoal }};
            @endif
        }

        .balance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .balance-table td {
            padding: 6px 0;
            font-size: 11px;
        }

        .balance-table .label {
            color: #6B7280;
        }

        .balance-table .value {
            text-align: right;
            @if($design === 'professional')
                color: {{ $charcoal }};
            @else
                color: #1F2937;
            @endif
        }

        .balance-table .total-row td {
            padding-top: 12px;
            font-weight: bold;
            font-size: 14px;
            @if($design === 'professional')
                font-size: 16px;
                font-family: Georgia, 'Times New Roman', Times, serif;
                color: {{ $navy }};
            @endif
        }

        .balance-table .paid {
            @if($design === 'professional')
                color: {{ $navy }};
            @else
                color: {{ $primary }};
            @endif
            font-weight: 500;
        }

        .qr-section {
            text-align: center;
            padding: 16px 0;
        }

        .qr-code {
            display: inline-block;
            padding: 8px;
            @if($design === 'modern')
                background: #F9FAFB;
                border-radius: 12px;
            @elseif($design === 'professional')
                background: #F8FAFC;
                border: 1px solid {{ $navy }}20;
            @else
                background: #FFFFFF;
                border-radius: 4px;
            @endif
        }

        .qr-code img {
            width: 100px;
            height: 100px;
        }

        .thank-you-section {
            text-align: center;
            padding: 20px;
            @if($design === 'classic')
                background: {{ $secondary }}10;
                border-top: 2px solid {{ $secondary }};
                border-bottom: 2px solid {{ $secondary }};
            @elseif($design === 'modern')
                background: {{ $secondary }}10;
                border-radius: 16px;
                margin: 16px;
            @elseif($design === 'professional')
                background: #F8FAFC;
                border-top: 1px solid {{ $navy }}20;
                border-bottom: 1px solid {{ $navy }}20;
            @else
                background: {{ $secondary }}10;
            @endif
        }

        .thank-you-message {
            font-size: 14px;
            font-weight: 500;
            @if($design === 'professional')
                font-family: Georgia, 'Times New Roman', Times, serif;
                color: {{ $navy }};
            @else
                color: {{ $primary }};
            @endif
        }

        .footer {
            margin-top: 24px;
            padding-top: 16px;
            text-align: center;
            @if($design === 'classic')
                border-top: 2px solid #9CA3AF;
                background: #F3F4F6;
                margin: 24px -40px -40px;
                padding: 20px 40px;
            @elseif($design === 'modern')
                background: linear-gradient(90deg, #F9FAFB 0%, #FFFFFF 100%);
                border-radius: 0 0 24px 24px;
                margin: 24px -40px -40px;
                padding: 20px 40px;
            @elseif($design === 'minimal')
                border-top: 1px solid #F3F4F6;
            @elseif($design === 'professional')
                background: {{ $navy }};
                margin: 24px -40px -40px;
                padding: 20px 40px;
            @endif
        }

        .footer p {
            font-size: 10px;
            @if($design === 'professional')
                color: rgba(255, 255, 255, 0.8);
            @else
                color: #9CA3AF;
            @endif
        }

        .generated-stamp {
            margin-top: 24px;
            text-align: right;
            font-size: 9px;
            font-style: italic;
            color: #D1D5DB;
        }

        @if($design === 'professional')
        .gold-accent {
            display: block;
            height: 2px;
            background: linear-gradient(90deg, transparent 0%, {{ $gold }} 50%, transparent 100%);
            margin: 16px 0;
        }
        @endif
    </style>
</head>
<body>
    <div class="receipt-container">
        {{-- Header --}}
        <div class="header">
            <div class="header-content">
                <div class="business-info">
                    @if($template->show_logo && $business->logo_path)
                        <img src="{{ Storage::disk('public')->url($business->logo_path) }}" alt="Logo" class="logo">
                    @endif
                    <h2>{{ $business->business_name }}</h2>
                    @if($business->business_address)
                        <p>{{ $business->business_address }}</p>
                    @endif
                    @if($business->business_phone)
                        <p>{{ $business->business_phone }}</p>
                    @endif
                </div>

                <div class="receipt-title">
                    <h1>RECEIPT</h1>
                    @if($template->show_receipt_number)
                        <p>#{{ $receipt->receipt_number }}</p>
                    @endif
                    @if($template->show_payment_date)
                        <p>{{ $payment->payment_date->format('M d, Y') }}</p>
                        <p style="opacity: 0.7">{{ $payment->payment_date->format('h:i A') }}</p>
                    @endif
                </div>
            </div>

            @if($template->custom_header)
                <p class="custom-header">{{ $template->custom_header }}</p>
            @endif
        </div>

        {{-- Gold Accent Line for Professional Design --}}
        @if($template->design === 'professional')
            <span class="gold-accent"></span>
        @endif

        {{-- Payment Confirmation Box --}}
        <div class="payment-box">
            <div class="payment-box-content">
                @if($template->design !== 'minimal')
                    <svg class="checkmark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @endif
                <div>
                    <p class="payment-label">Payment Received</p>
                    <p class="payment-amount">{{ $currency_symbol }} {{ number_format($payment->amount, 2) }}</p>
                </div>
            </div>
        </div>

        {{-- Payment Details --}}
        @if($template->show_payment_method || $template->show_transaction_reference)
            <div class="section">
                <p class="section-title">Payment Details</p>
                <div class="info-grid">
                    @if($template->show_payment_method)
                        <div class="info-item">
                            <label>Method</label>
                            <span>{{ ucwords(str_replace('_', ' ', $payment->payment_method ?? 'N/A')) }}</span>
                        </div>
                    @endif
                    @if($template->show_transaction_reference && $payment->reference)
                        <div class="info-item">
                            <label>Reference</label>
                            <span>{{ $payment->mpesa_transaction_id ?? $payment->reference }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Tenant & Property --}}
        @if(($template->show_tenant_name || $template->show_tenant_email || $template->show_tenant_phone || $template->show_unit_details || $template->show_building_name) && $tenant)
            <div class="tenant-property-section">
                <div class="info-grid">
                    @if($template->show_tenant_name || $template->show_tenant_email || $template->show_tenant_phone)
                        <div>
                            <p class="section-title">Received From</p>
                            @if($template->show_tenant_name)
                                <p style="font-weight: 500; color: {{ $template->design === 'professional' ? '#1e3a5f' : '#1F2937' }}">{{ $tenant->name }}</p>
                            @endif
                            @if($template->show_tenant_email)
                                <p style="font-size: 11px; color: {{ $template->design === 'professional' ? '#374151' : '#6B7280' }}">{{ $tenant->email }}</p>
                            @endif
                            @if($template->show_tenant_phone && $tenant->mobile_number)
                                <p style="font-size: 11px; color: {{ $template->design === 'professional' ? '#374151' : '#6B7280' }}">{{ $tenant->mobile_number }}</p>
                            @endif
                        </div>
                    @endif

                    @if(($template->show_unit_details || $template->show_building_name) && $unit)
                        <div>
                            <p class="section-title">Property</p>
                            @if($template->show_unit_details)
                                <p style="font-weight: 500; color: {{ $template->design === 'professional' ? '#1e3a5f' : '#1F2937' }}">{{ $unit->unit_number }}</p>
                            @endif
                            @if($template->show_building_name && $building)
                                <p style="font-size: 11px; color: {{ $template->design === 'professional' ? '#374151' : '#6B7280' }}">{{ $building->name }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Invoice Details --}}
        @if($template->show_invoice_details && $invoice)
            <div class="section">
                <p class="section-title">For Invoice</p>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                    <span style="color: #6B7280;">Invoice #{{ $invoice->invoice_number }}</span>
                    <span style="font-weight: 500; color: {{ $template->design === 'professional' ? '#1e3a5f' : '#1F2937' }}">{{ $currency_symbol }} {{ number_format($invoice->total_due, 2) }}</span>
                </div>

                @if($template->show_invoice_breakdown && $invoice->items && $invoice->items->count() > 0)
                    <table class="invoice-table" style="margin-top: 12px;">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td>{{ $item->description }}</td>
                                    <td style="text-align: right;">{{ $currency_symbol }} {{ number_format($item->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        {{-- Amount Breakdown / Balance --}}
        @if($template->show_amount_breakdown || $template->show_balance_after_payment)
            <div class="section" style="border-top: 1px solid {{ $template->design === 'professional' ? '#1e3a5f20' : '#E5E7EB' }}; padding-top: 16px;">
                <table class="balance-table">
                    <tbody>
                        @if($template->show_amount_breakdown && $invoice)
                            <tr>
                                <td class="label">Previous Balance</td>
                                <td class="value">{{ $currency_symbol }} {{ number_format($invoice->total_due, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="label">Amount Paid</td>
                                <td class="value paid">-{{ $currency_symbol }} {{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        @endif
                        @if($template->show_balance_after_payment && $invoice)
                            <tr class="total-row">
                                <td class="label">Balance Due</td>
                                <td class="value">{{ $currency_symbol }} {{ number_format(max(0, $invoice->total_due - $invoice->amount_paid), 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif

        {{-- QR Code --}}
        @if($template->show_qr_code && $qr_code)
            <div class="qr-section">
                <div class="qr-code">
                    <img src="data:image/svg+xml;base64,{{ base64_encode($qr_code) }}" alt="QR Code">
                </div>
            </div>
        @endif

        {{-- Thank You Message --}}
        @if($template->show_thank_you_message && $template->thank_you_message)
            <div class="thank-you-section">
                <p class="thank-you-message">{{ $template->thank_you_message }}</p>
            </div>
        @endif

        {{-- Footer --}}
        @if($template->show_footer || $template->custom_footer)
            <div class="footer">
                @if($template->custom_footer)
                    <p>{{ $template->custom_footer }}</p>
                @endif
                @if($template->show_footer)
                    <p style="margin-top: 8px; opacity: 0.7;">This is an official receipt for payment received.</p>
                @endif
            </div>
        @endif

        <p class="generated-stamp">Generated on {{ now()->format('F d, Y \a\t h:i A') }}</p>
    </div>
</body>
</html>
