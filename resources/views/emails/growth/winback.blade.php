<x-mail::message>
# {{ __('growth.lifecycle.winback_heading') }}

{{ __('growth.lifecycle.winback_body', ['code' => $discountCode]) }}

<x-mail::button :url="$plansUrl">
{{ __('growth.lifecycle.see_plans_cta') }}
</x-mail::button>

{{ __('growth.lifecycle.signature', ['app' => config('app.name')]) }}
</x-mail::message>
