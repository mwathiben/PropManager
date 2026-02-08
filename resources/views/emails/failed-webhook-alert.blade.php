<x-mail::message>
# Webhook Processing Failed

A {{ $provider }} webhook could not be processed and has been saved to the dead letter queue.

## Details

**Provider:** {{ ucfirst($provider) }}<br>
**Event Type:** {{ $eventType }}<br>
**Error:** {{ $errorReason }}<br>
**Classification:** {{ ucfirst($errorClass) }}<br>
**Occurred At:** {{ $createdAt->format('F d, Y H:i:s') }}

@if($isRetryable)
<x-mail::panel>
This error is classified as **transient** and will be automatically retried.
</x-mail::panel>
@else
<x-mail::panel>
This error is classified as **{{ $errorClass }}** and requires manual review.
</x-mail::panel>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
