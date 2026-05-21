<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.6; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; }
        .row { margin-bottom: 3px; }
        .label { color: #6b7280; display: inline-block; width: 180px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 6px; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; }
        .footer { margin-top: 40px; color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ __('lease_doc.renewal.title') }}</div>
    </div>

    <div class="row"><span class="label">{{ __('lease_doc.agreement.tenant') }}:</span> {{ $tenant?->name ?? '—' }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.property') }}:</span> {{ $property?->name ?? '—' }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.unit') }}:</span> {{ $unit?->unit_number ?? '—' }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.renewal.date') }}:</span> {{ $generatedAt->format('d M Y') }}</div>

    <table>
        <thead>
            <tr><th></th><th>{{ __('lease_doc.renewal.current') }}</th><th>{{ __('lease_doc.renewal.proposed') }}</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('lease_doc.agreement.rent') }}</td>
                <td>KES {{ number_format($currentRent, 2) }}</td>
                <td>KES {{ number_format($proposedRent, 2) }}</td>
            </tr>
            <tr>
                <td>{{ __('lease_doc.agreement.end_date') }}</td>
                <td>{{ optional($lease->end_date)->format('d M Y') ?? '—' }}</td>
                <td>{{ optional($proposedEndDate)->format('d M Y') ?? '—' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">{{ __('lease_doc.agreement.generated_on') }} {{ $generatedAt->format('Y-m-d H:i') }}</div>
</body>
</html>
