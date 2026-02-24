<x-mail::message :unsubscribeUrl="$unsubscribeUrl ?? null">
# {{ $subject }}

Hello {{ $recipient->name }},

<x-mail::panel>
{!! nl2br(e($notificationBody)) !!}
</x-mail::panel>

@if (isset($data) && is_array($data))
@php
    $tableData = collect($data)
        ->reject(fn($value, $key) => in_array($key, ['action_url', 'action_text']) || !is_scalar($value))
        ->all();
@endphp
@if (count($tableData) > 0)
| Details | Value |
|:--------|:------|
@foreach ($tableData as $key => $value)
| **{!! str_replace('|', '&#124;', e(ucwords(str_replace('_', ' ', $key)))) !!}** | {!! str_replace('|', '&#124;', e($value)) !!} |
@endforeach
@endif
@endif

@if (isset($data['action_url']) && isset($data['action_text']))
@php
    $actionScheme = parse_url($data['action_url'], PHP_URL_SCHEME);
    $safeAction = in_array(strtolower($actionScheme ?? ''), ['http', 'https', 'mailto'], true);
@endphp
@if ($safeAction)
<x-mail::button :url="$data['action_url']">
{{ $data['action_text'] }}
</x-mail::button>
@endif
@endif

If you have any questions, please don't hesitate to contact us.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
