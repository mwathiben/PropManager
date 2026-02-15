<x-mail::message>
# Invoice {{ $invoiceNumber }}

Hello {{ $tenant->name }},

Your invoice for **{{ $billingPeriod }}** has been generated and is now due.

## Property Details

- **Property:** {{ $propertyName }}
- **Building:** {{ $buildingName }}
- **Unit:** {{ $unitNumber }}

## Invoice Summary

<x-mail::table>
| Description | Amount |
|:------------|-------:|
| Rent | {{ $currency_symbol }} {{ $rentDue }} |
@if($invoice->water_due > 0)
| Water Charges | {{ $currency_symbol }} {{ $waterDue }} |
@endif
@if($invoice->arrears > 0)
| Previous Arrears | {{ $currency_symbol }} {{ $arrears }} |
@endif
| **Total Due** | **{{ $currency_symbol }} {{ $totalDue }}** |
</x-mail::table>

<x-mail::panel>
**Due Date:** {{ $dueDate }}

Please ensure payment is made by the due date to avoid late fees.
</x-mail::panel>

<x-mail::button :url="$invoiceUrl">
View Invoice & Pay
</x-mail::button>

## Payment Methods

You can pay your invoice using any of the following methods:

- **M-Pesa (Mobile Money)** - Quick and convenient
- **Bank Transfer** - Direct deposit to landlord's account
- **Online Payment** - Pay securely via card

If you have already made this payment, please disregard this notice.

If you have any questions about this invoice, please contact your landlord.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
