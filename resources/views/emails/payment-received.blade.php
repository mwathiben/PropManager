<x-mail::message>
# {{ __('emails.payment.title') }}

{{ __('emails.payment.greeting', ['name' => $tenant->name]) }},

{{ __('emails.payment.success_message') }}

## {{ __('emails.payment.details_title') }}

**{{ __('emails.payment.amount_paid') }}:** {{ $currency_symbol }} {{ number_format($payment->amount, 2) }}<br>
**{{ __('emails.payment.payment_date') }}:** {{ $payment->payment_date->format('F d, Y') }}<br>
**{{ __('emails.payment.payment_method') }}:** {{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}<br>
**{{ __('emails.payment.receipt_number') }}:** {{ $payment->reference }}

## {{ __('emails.payment.invoice_info_title') }}

**{{ __('emails.payment.invoice_number') }}:** {{ $invoice->invoice_number }}<br>
**{{ __('emails.payment.billing_period') }}:** {{ $invoice->billing_period_start->format('F Y') }}<br>
**{{ __('emails.payment.unit') }}:** {{ $unit->unit_number }} - {{ $unit->building->name }}

## {{ __('emails.payment.summary_title') }}

**{{ __('emails.payment.total_due') }}:** {{ $currency_symbol }} {{ number_format($invoice->total_due, 2) }}<br>
**{{ __('emails.payment.amount_paid_this') }}:** {{ $currency_symbol }} {{ number_format($payment->amount, 2) }}<br>
**{{ __('emails.payment.total_paid_to_date') }}:** {{ $currency_symbol }} {{ number_format($invoice->amount_paid, 2) }}<br>
**{{ __('emails.payment.balance_remaining') }}:** {{ $currency_symbol }} {{ number_format($invoice->total_due - $invoice->amount_paid, 2) }}

@if($invoice->status === 'paid')
<x-mail::panel>
**{{ __('emails.payment.fully_paid') }}**
</x-mail::panel>
@endif

<x-mail::button :url="route('payments.downloadReceipt', $payment)">
{{ __('emails.payment.download_receipt') }}
</x-mail::button>

{{ __('emails.payment.questions') }}

{{ __('emails.payment.thanks') }},<br>
{{ __('emails.payment.team', ['app' => config('app.name')]) }}
</x-mail::message>
