<x-mail::message>
# {{ $stage === 'overdue' ? 'BREACH SLA OVERDUE' : 'BREACH SLA IMMINENT' }} — Incident #{{ $incident->id }}

@if($stage === 'overdue')
The 72-hour regulator-notification window for this incident **has elapsed without an `odpc_notified_at` timestamp being recorded.** This is a Kenya DPA Section 43 / GDPR Article 33 reporting failure.
@else
The 72-hour regulator-notification window for this incident **will elapse in less than {{ abs($hoursDelta) }} hours.** Notify the ODPC now.
@endif

## Incident Summary

**Severity:** {{ strtoupper($incident->severity) }}<br>
**Type:** {{ $incident->type }}<br>
**Reported at:** {{ $incident->reported_at?->format('F d, Y H:i:s') ?? 'unknown' }}<br>
**Deadline:** {{ $incident->notification_deadline?->format('F d, Y H:i:s') ?? 'unset' }}<br>
**Hours past deadline:** {{ $hoursDelta < 0 ? abs($hoursDelta) : 0 }}<br>
**Estimated affected subjects:** {{ $incident->estimated_affected_users }}<br>
**Status:** {{ $incident->status }}

## Description

{{ $incident->description }}

<x-mail::panel>
Send the notification to **{{ $odpcEmail }}** immediately. Once sent, run:

```
php artisan dpa:mark-regulator-notified --incident={{ $incident->id }}
```

This stops the hourly escalation loop. Section 43(2) affected-subject notification is a separate obligation — see `docs/runbooks/breach-response.md`.
</x-mail::panel>

Thanks,<br>
{{ config('app.name') }} Compliance Channel
</x-mail::message>
