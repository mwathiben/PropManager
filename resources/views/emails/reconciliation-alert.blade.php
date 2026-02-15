<x-mail::message>
# Payment Reconciliation Alert

**{{ $discrepancyCount }}** discrepancies were found during {{ $provider }} reconciliation.

## Period

**From:** {{ $periodFrom }}<br>
**To:** {{ $periodTo }}

## Summary

| Metric | Count |
|--------|-------|
| Local Payments | {{ $localCount }} |
| Remote Transactions | {{ $remoteCount }} |
| Matched | {{ $matchedCount }} |
| **Discrepancies** | **{{ $discrepancyCount }}** |

## Discrepancy Breakdown

@if($missingLocally > 0)
- **Missing Locally:** {{ $missingLocally }} (paid remotely but not recorded locally)
@endif
@if($missingRemotely > 0)
- **Missing Remotely:** {{ $missingRemotely }} (recorded locally but not found remotely)
@endif
@if($amountMismatches > 0)
- **Amount Mismatch:** {{ $amountMismatches }} (different amounts between local and remote)
@endif

<x-mail::panel>
Please review these discrepancies in your Finances dashboard under the Reconciliation tab.
</x-mail::panel>

<x-mail::button :url="url('/finances?tab=reconciliation')">
View Reconciliation
</x-mail::button>

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
