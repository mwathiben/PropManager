<x-mail::message>
# Credit Note Issued

Dear {{ $tenant->name }},

A credit note has been issued to your account. This credit can be applied to your future invoices.

## Credit Note Details

**Credit Note Number:** {{ $creditNote->credit_number }}<br>
**Issue Date:** {{ $creditNote->created_at->format('F d, Y') }}<br>
**Credit Amount:** {{ $currency_symbol }} {{ number_format($creditNote->amount, 2) }}

## Reason for Credit

**{{ $creditNote->reason_label }}**

@if($creditNote->notes)
*{{ $creditNote->notes }}*
@endif

@if($unit && $building)
## Property Information

**Unit:** {{ $unit->unit_number }}<br>
**Building:** {{ $building->name }}
@endif

@if($invoice)
## Original Invoice Reference

**Invoice Number:** {{ $invoice->invoice_number }}<br>
**Invoice Date:** {{ $invoice->created_at->format('F d, Y') }}<br>
**Invoice Amount:** {{ $currency_symbol }} {{ number_format($invoice->total_due, 2) }}
@endif

<x-mail::panel>
**Your Credit Balance:** {{ $currency_symbol }} {{ number_format($creditNote->remaining_amount, 2) }}

This credit will be automatically applied to your next invoice, or you can request its application to a specific outstanding invoice.
</x-mail::panel>

## What's Next?

- Your credit note has been approved and is ready to use
- The credit will be applied to your account balance
- You will receive confirmation when the credit is applied to an invoice

If you have any questions about this credit note, please don't hesitate to contact your property manager.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
