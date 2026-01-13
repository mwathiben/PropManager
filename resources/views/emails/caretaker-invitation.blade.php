<x-mail::message>
# You're Invited!

Hello,

**{{ $landlordName }}** has invited you to join as a caretaker for **{{ $propertyName }}**.

As a caretaker, you'll be able to:
- Record water meter readings for all units
- View tenant information
- Manage maintenance requests (coming soon)
- Assist with day-to-day property operations

## Get Started

Click the button below to accept this invitation and create your caretaker account.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

**Important:** This invitation will expire on **{{ $expiresAt }}**.

If you have any questions, please contact {{ $landlordName }} directly.

If you weren't expecting this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
