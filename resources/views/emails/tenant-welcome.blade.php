<x-mail::message :unsubscribeUrl="$unsubscribeUrl ?? null">
# Welcome to {{ $propertyName }}!

Hello {{ $tenant->name }},

Your lease is now active! We're excited to have you as a tenant.

## Your Lease Details

- **Property:** {{ $propertyName }}
- **Building:** {{ $buildingName }}
- **Unit:** {{ $unitNumber }}
- **Monthly Rent:** {{ $currency_symbol }} {{ $rentAmount }}
- **Security Deposit:** {{ $currency_symbol }} {{ $depositAmount }}
- **Lease Start Date:** {{ $startDate }}

## Your Landlord

- **Name:** {{ $landlordName }}
@if($landlordEmail)
- **Email:** {{ $landlordEmail }}
@endif
@if($landlordPhone)
- **Phone:** {{ $landlordPhone }}
@endif

## Access Your Tenant Portal

Log in to your tenant portal to:
- View your lease details
- Pay rent and view payment history
- Submit maintenance requests
- Communicate with your landlord

<x-mail::button :url="$dashboardUrl">
Go to Tenant Portal
</x-mail::button>

If you have any questions or concerns, don't hesitate to reach out to your landlord.

Welcome home!

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
