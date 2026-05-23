<x-mail::message>
# You're Invited!

Hello,

**{{ $landlordName }}** has invited you to set up your water account@if($identifier) for water line **{{ $identifier }}**@endif.

With your water account you'll be able to:
- See your water consumption history
- View your water charges and statements
- Pay your water bill online

## Get Started

Click the button below to accept this invitation and create your account.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

**Important:** This invitation will expire on **{{ $expiresAt }}**.

If you have any questions, please contact {{ $landlordName }} directly.

If you weren't expecting this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
