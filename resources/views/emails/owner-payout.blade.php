<x-mail::message>
# {{ __('owners.payouts.title') }}

{{ $ownerName }},

{{ __('owners.payouts.email_intro') }}

<x-mail::panel>
**{{ __('owners.payouts.amount') }}:** {{ $currencySymbol }} {{ number_format((float) ($payout['amount'] ?? 0), 2) }}
{{ __('owners.payouts.paid_on') }}: {{ $payout['paid_on'] ?? '' }} · {{ __('owners.payouts.method') }}: {{ $payout['method'] ?? '' }}
</x-mail::panel>

{{ __('owners.payouts.email_outro') }}

{{ __('emails.payment.thanks') ?? 'Thanks' }},<br>
{{ $landlordName }}
</x-mail::message>
