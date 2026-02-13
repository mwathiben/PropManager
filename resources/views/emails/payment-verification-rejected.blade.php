<x-mail::message>
# Payment Verification Issue

Dear {{ $tenant->name }},

Unfortunately, we were unable to verify your payment submission. Please review the details below and resubmit your proof of payment.

## Reason for Rejection

<x-mail::panel>
{{ $rejectionReason }}
</x-mail::panel>

## Required Payment

**Deposit Required:** {{ $currency_symbol }} {{ number_format($verification->deposit_required, 2) }}<br>
**First Month Rent:** {{ $currency_symbol }} {{ number_format($verification->first_rent_required, 2) }}<br>
@if($verification->other_charges > 0)
**Other Charges:** {{ $currency_symbol }} {{ number_format($verification->other_charges, 2) }} ({{ $verification->other_charges_description }})<br>
@endif
**Total Required:** {{ $currency_symbol }} {{ number_format($verification->total_required, 2) }}

## What to Do Next

1. Review the rejection reason above
2. Ensure you have the correct payment documents
3. Upload clear, legible copies of your payment proof
4. Submit for verification again

<x-mail::button :url="$resubmitUrl">
Resubmit Payment Proof
</x-mail::button>

If you believe this rejection was made in error, please contact your property manager directly.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
