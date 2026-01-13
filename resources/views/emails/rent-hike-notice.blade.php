<x-mail::message>
# Rent Adjustment Notice

Hello {{ $tenant->name }},

This is to inform you of an upcoming change to your monthly rent.

## Property Details

- **Property:** {{ $propertyName }}
- **Building:** {{ $buildingName }}
- **Unit:** {{ $unitNumber }}

## Rent Adjustment Details

<x-mail::table>
| Description | Amount |
|:------------|-------:|
| Current Rent | KES {{ $oldAmount }} |
| New Rent | KES {{ $newAmount }} |
</x-mail::table>

<x-mail::panel>
**Effective Date:** {{ $effectiveDate }}

The new rent amount will take effect starting from your {{ $effectiveDate }} billing cycle.
</x-mail::panel>

@if($reason)
## Reason for Adjustment

{{ $reason }}
@endif

<x-mail::button :url="$dashboardUrl">
View Lease Details
</x-mail::button>

If you have any questions or concerns about this adjustment, please don't hesitate to contact your landlord.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
