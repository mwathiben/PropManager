{{ __('maintenance.vendor_onboarding.heading') }}

{{ __('maintenance.vendor_onboarding.greeting', ['name' => $vendor->contact_person ?: $vendor->name]) }}

{{ __('maintenance.vendor_onboarding.body', [
    'landlord' => $vendor->landlord?->name ?? config('app.name'),
]) }}

{{ __('maintenance.vendor_onboarding.cta') }}:
{{ $profileUrl }}

{{ __('maintenance.vendor_onboarding.expiry_note') }}

{{ __('maintenance.vendor_onboarding.signoff', ['app' => config('app.name')]) }}
