<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Subscription Receipt - {{ $payment->reference }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            color: #333;
            margin: 0;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 {
            font-size: 24px;
            color: #4f46e5;
            margin: 0;
        }
        .header p {
            color: #666;
            margin: 5px 0 0;
        }
        .receipt-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .receipt-title {
            font-size: 20px;
            font-weight: bold;
            color: #111;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4f46e5;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            padding: 8px 0;
            color: #666;
            width: 40%;
        }
        .info-value {
            display: table-cell;
            padding: 8px 0;
            color: #111;
            font-weight: 500;
        }
        .amount-section {
            background: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
        }
        .amount-row.total {
            font-size: 18px;
            font-weight: bold;
            border-top: 2px solid #e5e7eb;
            margin-top: 10px;
            padding-top: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-successful { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #666;
            font-size: 12px;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <p>Property Management Made Simple</p>
    </div>

    <div class="receipt-box">
        <div class="receipt-title">Subscription Receipt</div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Receipt Number</div>
                <div class="info-value">{{ $payment->reference }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date</div>
                <div class="info-value">{{ $payment->paid_at?->format('F j, Y') ?? $payment->created_at->format('F j, Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="status-badge status-{{ $payment->status }}">{{ $payment->status_label }}</span>
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Customer</div>
                <div class="info-value">{{ $payment->user->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email</div>
                <div class="info-value">{{ $payment->user->email }}</div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Plan</div>
                <div class="info-value">{{ $payment->subscription->plan->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Billing Cycle</div>
                <div class="info-value">{{ ucfirst($payment->subscription->billing_cycle ?? 'Monthly') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Payment Method</div>
                <div class="info-value">{{ $payment->payment_method_label }}</div>
            </div>
        </div>

        <div class="amount-section">
            <div class="amount-row">
                <span>Subscription Fee</span>
                <span>{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</span>
            </div>
            <div class="amount-row total">
                <span>Total Paid</span>
                <span>{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for your subscription!</p>
        <p>If you have any questions, please contact us at support@propmanager.com</p>
        <p>This receipt was generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>
</body>
</html>
