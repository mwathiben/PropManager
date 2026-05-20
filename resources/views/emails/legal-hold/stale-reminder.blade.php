<x-mail::message>
# {{ __('legal_holds.stale.heading') }}

{{ __('legal_holds.stale.greeting', ['name' => $landlord->name]) }}

{{ __('legal_holds.stale.body', ['count' => count($holds)]) }}

<x-mail::table>
| {{ __('legal_holds.stale.col_subject') }} | {{ __('legal_holds.stale.col_reason') }} | {{ __('legal_holds.stale.col_days') }} |
| :--- | :--- | ---: |
@foreach ($holds as $hold)
| {{ $hold['type'] }} #{{ $hold['id'] }} | {{ \Illuminate\Support\Str::limit($hold['reason'], 60) }} | {{ $hold['days_held'] }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('legal-holds.index', ['status' => 'active'])">
{{ __('legal_holds.stale.cta') }}
</x-mail::button>

{{ __('legal_holds.stale.footer') }}

{{ __('legal_holds.stale.signoff', ['app' => config('app.name')]) }}
</x-mail::message>
