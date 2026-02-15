<x-mail::message>
# Security Deposit {{ $type === 'forfeited' ? 'Forfeiture Notice' : 'Refund Notification' }}

Dear {{ $tenant->name }},

@if($type === 'forfeited')
We regret to inform you that your security deposit for **{{ $unit->unit_number }}** ({{ $unit->building->name }}) has been forfeited.

**Deposit Amount:** {{ $currency_symbol }} {{ number_format($depositAmount, 2) }}

**Reason for Forfeiture:**
{{ $deductionReason ?? 'No reason provided' }}

If you believe this is in error or wish to discuss this matter, please contact your property manager.
@elseif($type === 'partial_refund')
Your security deposit for **{{ $unit->unit_number }}** ({{ $unit->building->name }}) has been processed with deductions.

<x-mail::table>
| Description | Amount ({{ $currency_symbol }}) |
|:------------|-------------:|
| Original Deposit | {{ number_format($depositAmount, 2) }} |
| Deductions | ({{ number_format($deductions, 2) }}) |
| **Refund Amount** | **{{ number_format($refundAmount, 2) }}** |
</x-mail::table>

@if($deductionReason)
**Reason for Deductions:**
{{ $deductionReason }}
@endif

Your refund will be processed within 7-14 business days.
@else
Your security deposit for **{{ $unit->unit_number }}** ({{ $unit->building->name }}) has been fully refunded.

**Refund Amount:** {{ $currency_symbol }} {{ number_format($refundAmount, 2) }}

Your refund will be processed within 7-14 business days.
@endif

If you have any questions, please don't hesitate to contact us.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
