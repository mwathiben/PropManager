<x-mail::message>
# {{ __('vendor_portal.declined_email.heading') }}

{{ __('vendor_portal.declined_email.body', ['vendor' => $vendor->name, 'ticket' => $ticket->title]) }}

@if ($reason)
**{{ __('vendor_portal.declined_email.reason_label') }}**

{{ $reason }}
@endif

{{ __('vendor_portal.declined_email.cta') }}

{{ __('vendor_portal.declined_email.signoff', ['app' => config('app.name')]) }}
</x-mail::message>
