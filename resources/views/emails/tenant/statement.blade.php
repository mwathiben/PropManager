<x-mail::message>
# {{ __('tenant.statement.email_heading') }}

{{ __('tenant.statement.email_intro', ['name' => $tenant->name]) }}

**{{ __('tenant.statement.period_label', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}**

<x-mail::table>
| {{ __('tenant.statement.col_date') }} | {{ __('tenant.statement.col_description') }} | {{ __('tenant.statement.col_charge') }} | {{ __('tenant.statement.col_payment') }} | {{ __('tenant.statement.col_balance') }} |
|:-----|:-------------|---:|---:|---:|
@foreach ($rows as $row)
| {{ $row['date'] }} | {{ $row['description'] }} | {{ $row['charge'] > 0 ? number_format($row['charge'], 2) : '' }} | {{ $row['payment'] > 0 ? number_format($row['payment'], 2) : '' }} | {{ number_format($row['running_balance'], 2) }} |
@endforeach
</x-mail::table>

{{ __('tenant.statement.email_footer') }}
</x-mail::message>
