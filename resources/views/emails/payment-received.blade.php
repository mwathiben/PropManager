<x-mail::message>
# Payment Received

Dear {{ $tenant->name }},

We have successfully received your payment. Thank you for your prompt payment!

## Payment Details

**Amount Paid:** KES {{ number_format($payment->amount, 2) }}<br>
**Payment Date:** {{ $payment->payment_date->format('F d, Y') }}<br>
**Payment Method:** {{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}<br>
**Receipt Number:** {{ $payment->reference }}

## Invoice Information

**Invoice Number:** {{ $invoice->invoice_number }}<br>
**Billing Period:** {{ $invoice->billing_period_start->format('F Y') }}<br>
**Unit:** {{ $unit->unit_number }} - {{ $unit->building->name }}

## Invoice Summary

**Total Due:** KES {{ number_format($invoice->total_due, 2) }}<br>
**Amount Paid (This Payment):** KES {{ number_format($payment->amount, 2) }}<br>
**Total Paid to Date:** KES {{ number_format($invoice->amount_paid, 2) }}<br>
**Balance Remaining:** KES {{ number_format($invoice->total_due - $invoice->amount_paid, 2) }}

@if($invoice->status === 'paid')
<x-mail::panel>
**Congratulations!** Your invoice has been fully paid. Thank you!
</x-mail::panel>
@endif

<x-mail::button :url="route('payments.downloadReceipt', $payment)">
Download Receipt
</x-mail::button>

If you have any questions about this payment, please don't hesitate to contact your property manager.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
