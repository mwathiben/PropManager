<x-mail::message :unsubscribeUrl="$unsubscribeUrl ?? null">
# Tenant Overpayment Notice

A tenant has made an overpayment that has been credited to their wallet balance.

## Tenant Details

**Tenant:** {{ $tenant->name }}<br>
**Email:** {{ $tenant->email }}<br>
**Unit:** {{ $unit->unit_number }} - {{ $unit->building->name }}

## Overpayment Details

**Total Payment Amount:** {{ $currency_symbol }} {{ number_format($payment->amount, 2) }}<br>
**Overpayment Amount:** {{ $currency_symbol }} {{ number_format($overpaymentAmount, 2) }}<br>
**Payment Date:** {{ $payment->payment_date->format('F d, Y') }}<br>
**Payment Method:** {{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}

<x-mail::panel>
**New Wallet Balance:** {{ $currency_symbol }} {{ number_format($newWalletBalance, 2) }}

This credit balance will be automatically applied to the tenant's next invoice.
</x-mail::panel>

## What You Can Do

- **View tenant details** to see their full payment history
- **Issue a refund** if the overpayment was made in error
- **Leave the credit** to be applied to future invoices

<x-mail::button :url="route('tenants.show', $tenant)">
View Tenant Profile
</x-mail::button>

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
