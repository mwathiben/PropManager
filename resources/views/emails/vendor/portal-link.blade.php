<x-mail::message>
# {{ __('vendor_portal.email.heading') }}

{{ __('vendor_portal.email.greeting', ['name' => $vendor->contact_person ?: $vendor->name]) }}

{{ __('vendor_portal.email.body') }}

<x-mail::button :url="$url">
{{ __('vendor_portal.email.cta') }}
</x-mail::button>

{{ __('vendor_portal.email.expiry') }}

{{ __('vendor_portal.email.signoff', ['app' => config('app.name')]) }}
</x-mail::message>
