<x-mail::message>
# {{ __('emails.signature_request.greeting', ['name' => $signerName]) }}

{{ __('emails.signature_request.intro', ['title' => $agreementTitle]) }}

<x-mail::button :url="$signUrl">
{{ __('emails.signature_request.button') }}
</x-mail::button>

{{ __('emails.signature_request.note') }}

{{ __('emails.signature_request.closing') }}<br>
{{ config('app.name') }}
</x-mail::message>
