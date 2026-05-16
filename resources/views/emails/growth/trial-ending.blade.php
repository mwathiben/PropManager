<x-mail::message>
# {{ __('growth.lifecycle.trial_ending_heading') }}

{{ __('growth.lifecycle.trial_ending_body', ['days' => $daysRemaining]) }}

<x-mail::button :url="$upgradeUrl">
{{ __('growth.lifecycle.upgrade_cta') }}
</x-mail::button>

{{ __('growth.lifecycle.signature', ['app' => config('app.name')]) }}
</x-mail::message>
