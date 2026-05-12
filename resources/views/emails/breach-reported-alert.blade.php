<x-mail::message>
# Data Breach Reported — Incident #{{ $incident->id }}

A data breach has been recorded in PropManager. **Kenya DPA Section 43 and GDPR Article 33 require notification to the ODPC within 72 hours.**

## Incident Summary

**Severity:** {{ strtoupper($incident->severity) }}<br>
**Type:** {{ $incident->type }}<br>
**Reported at:** {{ $incident->reported_at?->format('F d, Y H:i:s') ?? 'unknown' }}<br>
**Estimated affected subjects:** {{ $incident->estimated_affected_users }}<br>
**Notification deadline:** {{ $incident->notification_deadline?->format('F d, Y H:i:s') ?? 'unset' }} ({{ $hoursToDeadline }} hours remaining)<br>
**Affected data categories:** {{ implode(', ', $incident->affected_data_types ?? []) ?: 'unspecified' }}

## Description

{{ $incident->description }}

## Mitigation Measures

{{ $incident->mitigation_measures ?: '(no mitigation recorded yet)' }}

<x-mail::panel>
Required regulator notification to **{{ $odpcEmail }}** within 72 hours. Once sent, run:

```
php artisan dpa:mark-regulator-notified --incident={{ $incident->id }}
```

Affected-subject notification (Article 34 / Section 43(2)) is handled separately — see the operator runbook at `docs/runbooks/breach-response.md`.
</x-mail::panel>

Thanks,<br>
{{ config('app.name') }} Compliance Channel
</x-mail::message>
