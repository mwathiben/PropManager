{{ __('onboarding.nudge.heading') }}

{{ __('onboarding.nudge.greeting', ['name' => $session->user->name ?? '']) }}

{{ __('onboarding.nudge.body', ['step' => $stepLabel]) }}

{{ __('onboarding.nudge.cta') }}:
{{ $resumeUrl }}

{{ __('onboarding.nudge.expiry_note') }}

{{ __('onboarding.nudge.signoff', ['app' => config('app.name')]) }}
