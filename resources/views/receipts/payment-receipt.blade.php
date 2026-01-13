<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt - {{ $receipt->receipt_number ?? $payment->reference }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #333;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            color: #333;
        }
        .header .subtitle {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .info-section {
            margin-bottom: 25px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #333;
        }
        .info-value {
            color: #666;
        }
        .amount-box {
            background-color: #f5f5f5;
            border: 2px solid #333;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .amount-box .label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        .amount-box .amount {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .details-table th,
        .details-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .details-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .thank-you {
            margin: 30px 0;
            padding: 15px;
            background-color: #e8f5e9;
            border-radius: 8px;
            text-align: center;
            color: #2e7d32;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #333;
            text-align: center;
            color: #666;
            font-size: 11px;
        }
        .stamp {
            margin-top: 40px;
            text-align: right;
            font-style: italic;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            @if(($settings->receipt_show_logo ?? true) && ($settings->logo_path ?? null))
                <img src="{{ Storage::disk('public')->url($settings->logo_path) }}" alt="Logo" class="logo">
            @endif
            <h1>PAYMENT RECEIPT</h1>
            @if($settings->receipt_header_text ?? null)
                <div class="subtitle">{{ $settings->receipt_header_text }}</div>
            @else
                <div class="subtitle">Official Receipt for Rent Payment</div>
            @endif
        </div>

        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Receipt Number:</span>
                <span class="info-value">{{ $receipt->receipt_number ?? $payment->reference }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Date:</span>
                <span class="info-value">{{ $payment->payment_date->format('F d, Y') }}</span>
            </div>
            @if($settings->receipt_show_payment_method ?? true)
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span class="info-value">{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</span>
            </div>
            @endif
        </div>

        <div class="amount-box">
            <div class="label">Amount Paid</div>
            <div class="amount">KES {{ number_format($payment->amount, 2) }}</div>
        </div>

        @if(($settings->receipt_show_tenant_details ?? true) && $invoice)
        <div class="info-section">
            <h3 style="margin-top: 30px; margin-bottom: 15px;">Tenant Information</h3>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value">{{ $invoice->lease->tenant->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ $invoice->lease->tenant->email }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Unit:</span>
                <span class="info-value">{{ $invoice->lease->unit->unit_number }} - {{ $invoice->lease->unit->building->name }}</span>
            </div>
        </div>
        @endif

        @if(($settings->receipt_show_invoice_details ?? true) && $invoice)
        <div class="info-section">
            <h3 style="margin-top: 30px; margin-bottom: 15px;">Invoice Details</h3>
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Billing Period</th>
                        <th>Total Due</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $invoice->invoice_number }}</td>
                        <td>{{ $invoice->billing_period_start->format('M Y') }}</td>
                        <td>KES {{ number_format($invoice->total_due, 2) }}</td>
                        <td>KES {{ number_format($invoice->amount_paid, 2) }}</td>
                        <td>KES {{ number_format($invoice->total_due - $invoice->amount_paid, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif

        @if($payment->notes)
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Notes:</span>
                <span class="info-value">{{ $payment->notes }}</span>
            </div>
        </div>
        @endif

        @if($settings->receipt_thank_you_message ?? null)
        <div class="thank-you">
            {{ $settings->receipt_thank_you_message }}
        </div>
        @endif

        <div class="stamp">
            Generated on {{ now()->format('F d, Y \a\t h:i A') }}
        </div>

        <div class="footer">
            @if($settings->receipt_footer_text ?? null)
                {!! nl2br(e($settings->receipt_footer_text)) !!}
            @else
                <p>This is an official receipt generated by PropManager</p>
                <p>For inquiries, please contact your property manager</p>
            @endif
        </div>
    </div>
</body>
</html>
