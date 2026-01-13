<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt - {{ $payment->reference }}</title>
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
            <h1>PAYMENT RECEIPT</h1>
            <div class="subtitle">Official Receipt for Rent Payment</div>
        </div>

        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Receipt Number:</span>
                <span class="info-value">{{ $payment->reference }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Date:</span>
                <span class="info-value">{{ $payment->payment_date->format('F d, Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span class="info-value">{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</span>
            </div>
        </div>

        <div class="amount-box">
            <div class="label">Amount Paid</div>
            <div class="amount">KES {{ number_format($payment->amount, 2) }}</div>
        </div>

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

        @if($payment->notes)
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Notes:</span>
                <span class="info-value">{{ $payment->notes }}</span>
            </div>
        </div>
        @endif

        <div class="stamp">
            Generated on {{ now()->format('F d, Y \a\t h:i A') }}
        </div>

        <div class="footer">
            <p>This is an official receipt generated by PropManager</p>
            <p>For inquiries, please contact your property manager</p>
        </div>
    </div>
</body>
</html>
