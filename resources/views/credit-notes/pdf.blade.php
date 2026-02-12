<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Credit Note - {{ $creditNote->credit_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Georgia, 'Times New Roman', Times, serif;
            font-size: 12px;
            line-height: 1.5;
            color: #374151;
            background: #fff;
        }

        .credit-note-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            border: 3px double #059669;
            position: relative;
        }

        .credit-note-container::before,
        .credit-note-container::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid #059669;
        }

        .credit-note-container::before {
            top: 8px;
            left: 8px;
            border-right: none;
            border-bottom: none;
        }

        .credit-note-container::after {
            bottom: 8px;
            right: 8px;
            border-left: none;
            border-top: none;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 24px;
            margin-bottom: 24px;
            background: linear-gradient(180deg, #FEFDFB 0%, #F0FDF4 100%);
            border-bottom: 2px solid #059669;
            margin: -40px -40px 24px;
            padding: 30px 40px;
        }

        .logo {
            max-width: 120px;
            max-height: 60px;
            margin-bottom: 12px;
        }

        .business-info h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1F2937;
            font-variant: small-caps;
            letter-spacing: 0.05em;
        }

        .business-info p {
            font-size: 11px;
            color: #6B7280;
        }

        .document-title {
            text-align: right;
        }

        .document-title h1 {
            font-size: 28px;
            font-weight: bold;
            color: #059669;
            letter-spacing: 0.15em;
            font-variant: small-caps;
            border-bottom: 2px solid #059669;
            padding-bottom: 4px;
        }

        .document-title p {
            font-size: 11px;
            color: #6B7280;
        }

        .credit-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 12px;
            background: #059669;
            color: white;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-radius: 4px;
        }

        .status-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 3px 10px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 4px;
        }

        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-approved { background: #D1FAE5; color: #065F46; }
        .status-applied { background: #DBEAFE; color: #1E40AF; }
        .status-voided { background: #FEE2E2; color: #991B1B; }

        .amount-section {
            text-align: center;
            padding: 30px;
            margin: 24px 0;
            background: linear-gradient(135deg, #F0FDF4 0%, #ECFDF5 100%);
            border: 2px solid #BBF7D0;
            border-radius: 12px;
        }

        .amount-label {
            font-size: 14px;
            font-weight: 500;
            color: #6B7280;
            margin-bottom: 8px;
        }

        .amount-value {
            font-size: 36px;
            font-weight: bold;
            color: #059669;
        }

        .amount-note {
            font-size: 11px;
            color: #6B7280;
            margin-top: 8px;
        }

        .section {
            margin: 24px 0;
            padding: 16px 0;
        }

        .section-title {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #059669;
            margin-bottom: 12px;
            padding-bottom: 4px;
            border-bottom: 2px solid #D1FAE5;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-block {
            padding: 12px;
            background: #F9FAFB;
            border-radius: 8px;
        }

        .info-block h4 {
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9CA3AF;
            margin-bottom: 4px;
        }

        .info-block p {
            font-weight: 500;
            color: #1F2937;
        }

        .info-block .sub {
            font-size: 11px;
            font-weight: 400;
            color: #6B7280;
        }

        .reason-section {
            padding: 20px;
            background: #FEF3C7;
            border-left: 4px solid #F59E0B;
            border-radius: 0 8px 8px 0;
            margin: 24px 0;
        }

        .reason-title {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #92400E;
            margin-bottom: 8px;
        }

        .reason-value {
            font-size: 14px;
            font-weight: 600;
            color: #92400E;
        }

        .reason-notes {
            margin-top: 12px;
            font-size: 11px;
            color: #78350F;
            font-style: italic;
        }

        .invoice-reference {
            padding: 16px;
            background: #F3F4F6;
            border-radius: 8px;
            margin: 16px 0;
        }

        .invoice-reference h4 {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6B7280;
            margin-bottom: 8px;
        }

        .invoice-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            padding: 4px 0;
        }

        .invoice-row .label { color: #6B7280; }
        .invoice-row .value { font-weight: 500; color: #1F2937; }

        .approval-section {
            padding: 16px;
            background: #D1FAE5;
            border-radius: 8px;
            margin: 24px 0;
        }

        .approval-section h4 {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #065F46;
            margin-bottom: 8px;
        }

        .approval-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            padding: 4px 0;
        }

        .approval-row .label { color: #065F46; }
        .approval-row .value { font-weight: 500; color: #047857; }

        .application-section {
            padding: 16px;
            background: #DBEAFE;
            border-radius: 8px;
            margin: 24px 0;
        }

        .application-section h4 {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #1E40AF;
            margin-bottom: 8px;
        }

        .application-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            padding: 4px 0;
        }

        .application-row .label { color: #1E40AF; }
        .application-row .value { font-weight: 500; color: #1D4ED8; }

        .balance-summary {
            margin: 24px 0;
            padding: 16px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
        }

        .balance-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 11px;
        }

        .balance-row .label { color: #6B7280; }
        .balance-row .value { font-weight: 500; color: #1F2937; }

        .balance-row.total {
            padding-top: 12px;
            margin-top: 8px;
            border-top: 2px solid #E5E7EB;
            font-size: 14px;
        }

        .balance-row.total .label { font-weight: 600; color: #1F2937; }
        .balance-row.total .value { font-weight: bold; color: #059669; }

        .qr-section {
            text-align: center;
            padding: 20px 0;
        }

        .qr-code {
            display: inline-block;
            padding: 8px;
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 8px;
        }

        .qr-code img {
            width: 100px;
            height: 100px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #059669;
            background: linear-gradient(180deg, #F0FDF4 0%, #FEFDFB 100%);
            margin: 30px -40px -40px;
            padding: 20px 40px;
            text-align: center;
        }

        .footer p {
            font-size: 10px;
            color: #9CA3AF;
        }

        .footer .generated {
            margin-top: 12px;
            font-size: 9px;
            font-style: italic;
            color: #D1D5DB;
        }

        .terms {
            margin-top: 24px;
            padding: 16px;
            background: #F9FAFB;
            border-radius: 8px;
            font-size: 10px;
            color: #6B7280;
        }

        .terms h5 {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #4B5563;
            margin-bottom: 8px;
        }

        .terms ul {
            margin-left: 16px;
        }

        .terms li {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="credit-note-container">
        {{-- Header --}}
        <div class="header">
            <div class="business-info">
                @if($business->logo_path)
                    <img src="{{ Storage::disk('public')->url($business->logo_path) }}" alt="Logo" class="logo">
                @endif
                <h2>{{ $business->business_name }}</h2>
                @if($business->business_address)
                    <p>{{ $business->business_address }}</p>
                @endif
                @if($business->business_phone)
                    <p>{{ $business->business_phone }}</p>
                @endif
                @if($business->business_email)
                    <p>{{ $business->business_email }}</p>
                @endif
            </div>

            <div class="document-title">
                <h1>CREDIT NOTE</h1>
                <p>#{{ $creditNote->credit_number }}</p>
                <p>{{ $creditNote->created_at->format('M d, Y') }}</p>
                <span class="credit-badge">Credit Adjustment</span>
                <br>
                <span class="status-badge status-{{ $creditNote->status }}">
                    {{ ucfirst($creditNote->status) }}
                </span>
            </div>
        </div>

        {{-- Credit Amount --}}
        <div class="amount-section">
            <p class="amount-label">Credit Amount</p>
            <p class="amount-value">{{ $currency_symbol }} {{ number_format($creditNote->amount, 2) }}</p>
            @if($creditNote->applied_amount > 0)
                <p class="amount-note">
                    Applied: {{ $currency_symbol }} {{ number_format($creditNote->applied_amount, 2) }} |
                    Remaining: {{ $currency_symbol }} {{ number_format($creditNote->remaining_amount, 2) }}
                </p>
            @endif
        </div>

        {{-- Tenant & Property Information --}}
        <div class="section">
            <p class="section-title">Credit Issued To</p>
            <div class="info-grid">
                @if($tenant)
                    <div class="info-block">
                        <h4>Tenant</h4>
                        <p>{{ $tenant->name }}</p>
                        <p class="sub">{{ $tenant->email }}</p>
                        @if($tenant->mobile_number)
                            <p class="sub">{{ $tenant->mobile_number }}</p>
                        @endif
                    </div>
                @endif

                @if($unit)
                    <div class="info-block">
                        <h4>Property</h4>
                        <p>{{ $unit->unit_number }}</p>
                        @if($building)
                            <p class="sub">{{ $building->name }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Reason for Credit --}}
        <div class="reason-section">
            <p class="reason-title">Reason for Credit</p>
            <p class="reason-value">{{ $creditNote->reason_label }}</p>
            @if($creditNote->notes)
                <p class="reason-notes">{{ $creditNote->notes }}</p>
            @endif
        </div>

        {{-- Original Invoice Reference --}}
        @if($invoice)
            <div class="invoice-reference">
                <h4>Original Invoice Reference</h4>
                <div class="invoice-row">
                    <span class="label">Invoice Number</span>
                    <span class="value">{{ $invoice->invoice_number }}</span>
                </div>
                <div class="invoice-row">
                    <span class="label">Invoice Date</span>
                    <span class="value">{{ $invoice->created_at->format('M d, Y') }}</span>
                </div>
                <div class="invoice-row">
                    <span class="label">Invoice Amount</span>
                    <span class="value">{{ $currency_symbol }} {{ number_format($invoice->total_due, 2) }}</span>
                </div>
            </div>
        @endif

        {{-- Approval Details --}}
        @if($creditNote->isApproved() || $creditNote->isApplied())
            <div class="approval-section">
                <h4>Approval Details</h4>
                @if($approver)
                    <div class="approval-row">
                        <span class="label">Approved By</span>
                        <span class="value">{{ $approver->name }}</span>
                    </div>
                @endif
                @if($creditNote->approved_at)
                    <div class="approval-row">
                        <span class="label">Approved Date</span>
                        <span class="value">{{ $creditNote->approved_at->format('M d, Y \a\t h:i A') }}</span>
                    </div>
                @endif
            </div>
        @endif

        {{-- Application Details --}}
        @if($creditNote->isApplied() && $appliedToInvoice)
            <div class="application-section">
                <h4>Application Details</h4>
                <div class="application-row">
                    <span class="label">Applied To Invoice</span>
                    <span class="value">{{ $appliedToInvoice->invoice_number }}</span>
                </div>
                <div class="application-row">
                    <span class="label">Amount Applied</span>
                    <span class="value">{{ $currency_symbol }} {{ number_format($creditNote->applied_amount, 2) }}</span>
                </div>
                @if($creditNote->applied_at)
                    <div class="application-row">
                        <span class="label">Applied Date</span>
                        <span class="value">{{ $creditNote->applied_at->format('M d, Y \a\t h:i A') }}</span>
                    </div>
                @endif
            </div>
        @endif

        {{-- Balance Summary --}}
        <div class="balance-summary">
            <div class="balance-row">
                <span class="label">Credit Note Amount</span>
                <span class="value">{{ $currency_symbol }} {{ number_format($creditNote->amount, 2) }}</span>
            </div>
            @if($creditNote->applied_amount > 0)
                <div class="balance-row">
                    <span class="label">Amount Applied</span>
                    <span class="value">-{{ $currency_symbol }} {{ number_format($creditNote->applied_amount, 2) }}</span>
                </div>
            @endif
            <div class="balance-row total">
                <span class="label">Available Credit</span>
                <span class="value">{{ $currency_symbol }} {{ number_format($creditNote->remaining_amount, 2) }}</span>
            </div>
        </div>

        {{-- QR Code --}}
        @if($qr_code)
            <div class="qr-section">
                <div class="qr-code">
                    <img src="{{ $qr_code }}" alt="QR Code">
                </div>
            </div>
        @endif

        {{-- Terms --}}
        <div class="terms">
            <h5>Terms & Conditions</h5>
            <ul>
                <li>This credit note is valid for application against future invoices.</li>
                <li>Credit notes cannot be redeemed for cash.</li>
                <li>This credit note must be used within the tenancy period.</li>
                <li>Contact your property manager for any queries regarding this credit note.</li>
            </ul>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>This is an official credit note issued by {{ $business->business_name }}</p>
            <p class="generated">Generated on {{ now()->format('F d, Y \a\t h:i A') }}</p>
        </div>
    </div>
</body>
</html>
