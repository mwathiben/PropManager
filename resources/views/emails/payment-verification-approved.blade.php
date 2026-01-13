<x-mail::message>
# Welcome to Your New Home!

Dear {{ $tenant->name }},

Great news! Your initial payment has been verified, and you now have full access to your tenant portal.

## Your Unit Details

**Unit:** {{ $unit->unit_number }}<br>
**Building:** {{ $building->name }}<br>

## Payment Summary

**Deposit Paid:** KES {{ number_format($verification->deposit_required, 2) }}<br>
**First Month Rent:** KES {{ number_format($verification->first_rent_required, 2) }}<br>
@if($verification->other_charges > 0)
**Other Charges:** KES {{ number_format($verification->other_charges, 2) }}<br>
@endif
**Total Verified:** KES {{ number_format($verification->total_required, 2) }}

<x-mail::panel>
You can now access all features of the tenant portal including viewing invoices, making payments, submitting maintenance requests, and more.
</x-mail::panel>

<x-mail::button :url="$dashboardUrl">
Go to Dashboard
</x-mail::button>

If you have any questions, please contact your property manager.

Welcome home!<br>
{{ config('app.name') }} Team
</x-mail::message>
