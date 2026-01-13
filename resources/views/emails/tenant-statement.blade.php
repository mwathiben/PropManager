<x-mail::message>
# Your Account Statement

Dear {{ $tenant->name }},

Please find attached your account statement{{ $dateFrom || $dateTo ? ' for the requested period' : '' }}.

## Account Summary

@if($dateFrom || $dateTo)
**Period:** {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'Start' }} - {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : 'Present' }}
@else
**Period:** All transactions
@endif

**Total Invoiced:** KES {{ number_format($summary['total_invoiced'], 2) }}<br>
**Total Paid:** KES {{ number_format($summary['total_paid'], 2) }}<br>
**Refunds:** KES {{ number_format($summary['total_refunds'], 2) }}<br>

<x-mail::panel>
@if($summary['current_balance'] > 0)
**Current Balance Due:** KES {{ number_format($summary['current_balance'], 2) }}
@elseif($summary['current_balance'] < 0)
**Account Credit:** KES {{ number_format(abs($summary['current_balance']), 2) }}
@else
**Balance:** Your account is fully paid
@endif
</x-mail::panel>

The detailed statement is attached to this email as a PDF document.

If you have any questions about your account or any of the transactions, please don't hesitate to contact us.

Thanks,<br>
{{ $landlord->name ?? config('app.name') }}
</x-mail::message>
