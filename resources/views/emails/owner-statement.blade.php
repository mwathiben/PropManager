<x-mail::message>
# {{ __('owners.title') }}

{{ $statement['owner']['name'] }},

Your statement for **{{ $statement['period']['start'] }} – {{ $statement['period']['end'] }}** is attached.

<x-mail::panel>
**Net to you:** {{ $currencySymbol }} {{ number_format($statement['net'], 2) }}
(collected {{ $currencySymbol }} {{ number_format($statement['collected'], 2) }} less expenses {{ $currencySymbol }} {{ number_format($statement['total_expenses'], 2) }})
</x-mail::panel>

The full breakdown by property is in the attached PDF.

{{ __('emails.payment.thanks') ?? 'Thanks' }},<br>
{{ $landlordName }}
</x-mail::message>
