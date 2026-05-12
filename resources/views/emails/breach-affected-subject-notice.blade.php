<x-mail::message>
# Important Security Notice

Dear {{ $subject->name }},

We are writing to inform you of a security incident that may have affected information you provided to {{ $controllerName }}. Under the Kenya Data Protection Act 2019 (Section 43) and the EU General Data Protection Regulation (Article 34), we are required to notify you because this incident is likely to result in a risk to your rights and freedoms.

## What happened

{{ $incident->description }}

**Date the incident occurred (or was discovered):** {{ $incident->reported_at?->format('F d, Y') ?? 'date unknown' }}

## What information was involved

The following categories of personal data may have been affected:

@foreach($incident->affected_data_types ?? [] as $category)
- {{ str_replace('_', ' ', ucfirst($category)) }}
@endforeach

@if(empty($incident->affected_data_types))
- (categories under investigation; we will follow up with an update)
@endif

## What we are doing

{{ $incident->mitigation_measures }}

## What you can do

- Change your {{ $controllerName }} password and any password reused on other services.
- Enable two-factor authentication on your account if you have not done so.
- Be alert to phishing emails or calls that reference this incident.
- If you used the same credentials elsewhere, change them on those services.

<x-mail::panel>
Questions about this notice? Contact us at **{{ $supportEmail }}**. You may also contact Kenya's Office of the Data Protection Commissioner (ODPC) at **{{ $odpcEmail }}**.
</x-mail::panel>

We take your privacy seriously and regret any inconvenience this incident has caused.

{{ $controllerName }} Compliance Team
</x-mail::message>
