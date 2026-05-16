<x-mail::message>
# {{ __('growth.lifecycle.activation_nudge_heading') }}

{{ __('growth.lifecycle.activation_nudge_body') }}

<x-mail::button :url="$resumeUrl">
{{ __('growth.lifecycle.resume_cta') }}
</x-mail::button>

{{ __('growth.lifecycle.signature', ['app' => config('app.name')]) }}
</x-mail::message>
