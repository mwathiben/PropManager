<x-mail::message>
# {{ __('growth.lifecycle.dunning_heading') }}

{{ __('growth.lifecycle.dunning_body', ['days' => $daysSincePastDue]) }}

<x-mail::button :url="$updateCardUrl">
{{ __('growth.lifecycle.update_card_cta') }}
</x-mail::button>

{{ __('growth.lifecycle.signature', ['app' => config('app.name')]) }}
</x-mail::message>
