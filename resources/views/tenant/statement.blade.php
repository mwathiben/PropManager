<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('tenant.statement.title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111827; }
        h1 { font-size: 18pt; margin: 0 0 4pt; }
        .meta { color: #6B7280; font-size: 9pt; margin-bottom: 14pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6pt 8pt; border-bottom: 1px solid #E5E7EB; text-align: left; vertical-align: top; }
        th { background: #1F2937; color: white; font-weight: 600; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr.opening td, tr.closing td { background: #F9FAFB; font-weight: 600; }
    </style>
</head>
<body>
    <h1>{{ __('tenant.statement.title') }}</h1>
    <p class="meta">
        {{ $tenant->name }} &middot;
        {{ __('tenant.statement.period_label', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}
    </p>

    <table>
        <thead>
            <tr>
                <th>{{ __('tenant.statement.col_date') }}</th>
                <th>{{ __('tenant.statement.col_description') }}</th>
                <th>{{ __('tenant.statement.col_reference') }}</th>
                <th class="num">{{ __('tenant.statement.col_charge') }}</th>
                <th class="num">{{ __('tenant.statement.col_payment') }}</th>
                <th class="num">{{ __('tenant.statement.col_balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="{{ $row['kind'] }}">
                    <td>{{ $row['date'] }}</td>
                    <td>
                        {{ $row['description'] }}
                        @if (in_array($row['kind'], ['wallet_credit', 'wallet_debit'], true) && !empty($row['amount']))
                            ({{ $row['currency'] }} {{ number_format((float) $row['amount'], 2) }})
                        @endif
                    </td>
                    <td>{{ $row['reference'] ?? '' }}</td>
                    <td class="num">{{ $row['charge'] > 0 ? number_format($row['charge'], 2) : '' }}</td>
                    <td class="num">{{ $row['payment'] > 0 ? number_format($row['payment'], 2) : '' }}</td>
                    <td class="num">{{ number_format($row['running_balance'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
