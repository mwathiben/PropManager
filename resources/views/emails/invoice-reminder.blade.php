<x-mail::message>
@if($isOverdue)
# Payment Overdue
@else
# Payment Reminder
@endif

Hello {{ $tenant->name }},

@if($isOverdue)
This is a reminder that your invoice **{{ $invoiceNumber }}** is **{{ $daysOverdue }} day(s) overdue**.
@else
This is a friendly reminder that your invoice **{{ $invoiceNumber }}** is due for payment.
@endif

## Property Details

- **Property:** {{ $propertyName }}
- **Building:** {{ $buildingName }}
- **Unit:** {{ $unitNumber }}

## Payment Summary

<x-mail::table>
| Description | Amount |
|:------------|-------:|
| Total Invoice Amount | KES {{ $totalDue }} |
@if($invoice->amount_paid > 0)
| Amount Paid | KES {{ $amountPaid }} |
@endif
| **Balance Due** | **KES {{ $balance }}** |
</x-mail::table>

<x-mail::panel>
@if($isOverdue)
**Due Date:** {{ $dueDate }} ({{ $daysOverdue }} days overdue)

Please settle this balance immediately to avoid any further action.
@else
**Due Date:** {{ $dueDate }}

Please ensure payment is made by the due date to avoid late fees.
@endif
</x-mail::panel>

<x-mail::button :url="$invoiceUrl">
Pay Now
</x-mail::button>

If you have already made this payment, please disregard this notice.

If you have any questions, please contact your landlord.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
