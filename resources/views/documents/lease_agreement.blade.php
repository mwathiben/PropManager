<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.6; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; }
        h2 { font-size: 14px; margin: 18px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
        .row { margin-bottom: 3px; }
        .label { color: #6b7280; display: inline-block; width: 160px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
        th { color: #6b7280; }
        .footer { margin-top: 40px; color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ __('lease_doc.agreement.title') }}</div>
    </div>

    <h2>{{ __('lease_doc.agreement.parties') }}</h2>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.landlord') }}:</span> {{ $lease->landlord?->name ?? '—' }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.tenant') }}:</span> {{ $tenant?->name ?? '—' }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.property') }}:</span> {{ $property?->name ?? '—' }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.unit') }}:</span> {{ $unit?->unit_number ?? '—' }}</div>

    <h2>{{ __('lease_doc.agreement.terms') }}</h2>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.start_date') }}:</span> {{ optional($lease->start_date)->format('d M Y') ?? '—' }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.end_date') }}:</span> {{ optional($lease->end_date)->format('d M Y') ?? '—' }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.rent') }}:</span> KES {{ number_format((float) $lease->rent_amount, 2) }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.deposit') }}:</span> KES {{ number_format((float) $lease->deposit_amount, 2) }}</div>
    <div class="row"><span class="label">{{ __('lease_doc.agreement.service_charge') }}:</span> KES {{ number_format((float) $lease->service_charge, 2) }}</div>

    @if($coTenants->isNotEmpty())
        <h2>{{ __('lease_doc.agreement.co_tenants') }}</h2>
        <table>
            <thead><tr><th>{{ __('lease.co_tenant.name') }}</th><th>{{ __('lease.co_tenant.relationship') }}</th><th>{{ __('lease.co_tenant.responsible_for_rent') }}</th></tr></thead>
            <tbody>
            @foreach($coTenants as $coTenant)
                <tr>
                    <td>{{ $coTenant->name }}</td>
                    <td>{{ $coTenant->relationship ?? '—' }}</td>
                    <td>{{ $coTenant->is_responsible_for_rent ? '✓' : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($guarantors->isNotEmpty())
        <h2>{{ __('lease_doc.agreement.guarantors') }}</h2>
        <table>
            <thead><tr><th>{{ __('lease.guarantor.name') }}</th><th>{{ __('lease.guarantor.relationship') }}</th><th>{{ __('lease.guarantor.guaranteed_amount') }}</th></tr></thead>
            <tbody>
            @foreach($guarantors as $guarantor)
                <tr>
                    <td>{{ $guarantor->name }}</td>
                    <td>{{ $guarantor->relationship ?? '—' }}</td>
                    <td>{{ $guarantor->guaranteed_amount ? 'KES '.number_format((float) $guarantor->guaranteed_amount, 2) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">{{ __('lease_doc.agreement.generated_on') }} {{ $generatedAt->format('Y-m-d H:i') }}</div>
</body>
</html>
