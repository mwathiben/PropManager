<x-mail::message>
# {{ __('onboarding.nudge.heading') }}

{{ __('onboarding.nudge.greeting', ['name' => $session->user->name ?? '']) }}

{{ __('onboarding.nudge.body', ['step' => $stepLabel]) }}

<x-mail::button :url="$resumeUrl">
{{ __('onboarding.nudge.cta') }}
</x-mail::button>

{{ __('onboarding.nudge.expiry_note') }}

{{ __('onboarding.nudge.signoff', ['app' => config('app.name')]) }}
</x-mail::message>
