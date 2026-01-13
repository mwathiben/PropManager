<x-mail::message>
# {{ $isExistingUser ? 'New Lease Invitation' : "You're Invited!" }}

Hello{{ $invitation->tenant_name ? ' ' . $invitation->tenant_name : '' }},

**{{ $landlordName }}** has invited you to lease a unit at **{{ $propertyName }}**.

## Property Details

- **Property:** {{ $propertyName }}
- **Building:** {{ $buildingName }}
- **Unit:** {{ $unitNumber }}

## Lease Terms

- **Monthly Rent:** KES {{ $rentAmount }}
- **Security Deposit:** KES {{ $depositAmount }}
- **Lease Start Date:** {{ $startDate }}

@if($isExistingUser)
Since you already have an account, simply click the button below to review and accept this lease.
@else
Click the button below to create your tenant account and accept this lease.
@endif

<x-mail::button :url="$acceptUrl">
{{ $isExistingUser ? 'Review & Accept Lease' : 'Accept Invitation' }}
</x-mail::button>

**Important:** This invitation will expire on **{{ $expiresAt }}**.

If you have any questions about the property or lease terms, please contact {{ $landlordName }} directly.

If you weren't expecting this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
