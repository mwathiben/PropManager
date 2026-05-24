<x-mail::message>
# You're Invited!

Hello{{ $ownerName ? ' '.$ownerName : '' }},

**{{ $landlordName }}** has invited you to set up an owner login for the properties they manage on your behalf.

With your owner login you'll be able to:
- See the properties managed for you
- View occupancy and rent-roll at a glance
- Download your owner statements any time

## Get Started

Click the button below to accept this invitation and set your password.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

**Important:** This invitation will expire on **{{ $expiresAt }}**.

If you have any questions, please contact {{ $landlordName }} directly.

If you weren't expecting this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
