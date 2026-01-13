<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }
        .invoice-container {
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
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }
        .info-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #333;
        }
        .info-value {
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft { background: #f3f4f6; color: #6b7280; }
        .status-sent { background: #dbeafe; color: #1d4ed8; }
        .status-partial { background: #fef3c7; color: #d97706; }
        .status-paid { background: #d1fae5; color: #059669; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .line-items-table th,
        .line-items-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .line-items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .line-items-table .text-right {
            text-align: right;
        }
        .totals-section {
            width: 300px;
            margin-left: auto;
            margin-top: 20px;
        }
        .totals-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        .totals-label {
            display: table-cell;
            text-align: right;
            padding-right: 20px;
        }
        .totals-value {
            display: table-cell;
            text-align: right;
            font-weight: bold;
        }
        .totals-row.grand-total {
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
        }
        .totals-row.grand-total .totals-label,
        .totals-row.grand-total .totals-value {
            font-size: 16px;
        }
        .amount-due-box {
            background-color: #f5f5f5;
            border: 2px solid #333;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .amount-due-box .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .amount-due-box .amount {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        .payment-instructions {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
        }
        .payment-instructions h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #333;
        }
        .payment-instructions ul {
            margin: 0;
            padding-left: 20px;
        }
        .payment-instructions li {
            margin-bottom: 5px;
            color: #666;
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
            margin-top: 20px;
            text-align: right;
            font-style: italic;
            color: #999;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <h1>INVOICE</h1>
            <div class="subtitle">{{ $invoice->invoice_number }}</div>
        </div>

        <div class="info-grid">
            <div class="info-column">
                <div class="info-section">
                    <h3>From</h3>
                    <div class="info-row">
                        <span class="info-value">{{ $property->name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-value">{{ $building->name }}</span>
                    </div>
                    @if($property->address)
                    <div class="info-row">
                        <span class="info-value">{{ $property->address }}</span>
                    </div>
                    @endif
                </div>
            </div>
            <div class="info-column">
                <div class="info-section">
                    <h3>Bill To</h3>
                    <div class="info-row">
                        <span class="info-value">{{ $tenant->name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-value">{{ $tenant->email }}</span>
                    </div>
                    @if($tenant->phone)
                    <div class="info-row">
                        <span class="info-value">{{ $tenant->phone }}</span>
                    </div>
                    @endif
                    <div class="info-row">
                        <span class="info-value">Unit {{ $unit->unit_number }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-column">
                <div class="info-row">
                    <span class="info-label">Invoice Date:</span>
                    <span class="info-value">{{ $invoice->created_at->format('F d, Y') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Due Date:</span>
                    <span class="info-value">{{ $invoice->due_date->format('F d, Y') }}</span>
                </div>
            </div>
            <div class="info-column">
                <div class="info-row">
                    <span class="info-label">Billing Period:</span>
                    <span class="info-value">{{ $invoice->billing_period_start->format('M d') }} - {{ $invoice->billing_period_end->format('M d, Y') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="status-badge status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
                </div>
            </div>
        </div>

        <table class="line-items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount (KES)</th>
                </tr>
            </thead>
            <tbody>
                @if($invoice->rent_amount > 0)
                <tr>
                    <td>Monthly Rent</td>
                    <td class="text-right">{{ number_format($invoice->rent_amount, 2) }}</td>
                </tr>
                @endif
                @if($invoice->water_charges > 0)
                <tr>
                    <td>Water Charges</td>
                    <td class="text-right">{{ number_format($invoice->water_charges, 2) }}</td>
                </tr>
                @endif
                @if($invoice->arrears_amount > 0)
                <tr>
                    <td>Previous Arrears</td>
                    <td class="text-right">{{ number_format($invoice->arrears_amount, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <div class="totals-section">
            <div class="totals-row">
                <span class="totals-label">Subtotal:</span>
                <span class="totals-value">KES {{ number_format($invoice->total_due, 2) }}</span>
            </div>
            @if($invoice->amount_paid > 0)
            <div class="totals-row">
                <span class="totals-label">Amount Paid:</span>
                <span class="totals-value" style="color: #059669;">- KES {{ number_format($invoice->amount_paid, 2) }}</span>
            </div>
            @endif
            <div class="totals-row grand-total">
                <span class="totals-label">Balance Due:</span>
                <span class="totals-value">KES {{ number_format($invoice->total_due - $invoice->amount_paid, 2) }}</span>
            </div>
        </div>

        @if($invoice->total_due - $invoice->amount_paid > 0)
        <div class="amount-due-box">
            <div class="label">Amount Due</div>
            <div class="amount">KES {{ number_format($invoice->total_due - $invoice->amount_paid, 2) }}</div>
        </div>

        <div class="payment-instructions">
            <h3>Payment Instructions</h3>
            <ul>
                <li><strong>M-Pesa:</strong> Pay via Lipa Na M-Pesa using Paybill/Till number provided by your landlord</li>
                <li><strong>Bank Transfer:</strong> Transfer to the bank account provided by your landlord</li>
                <li><strong>Online:</strong> Pay securely through the tenant portal</li>
            </ul>
        </div>
        @endif

        <div class="stamp">
            Generated on {{ now()->format('F d, Y \a\t h:i A') }}
        </div>

        <div class="footer">
            <p>This invoice was generated by PropManager</p>
            <p>For inquiries, please contact your property manager</p>
        </div>
    </div>
</body>
</html>
