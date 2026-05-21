<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $dashboardName }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .meta { color: #6b7280; font-size: 9px; margin-bottom: 12px; }
        .card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; margin-bottom: 10px; page-break-inside: avoid; }
        .card h2 { font-size: 12px; margin: 0 0 6px; }
        .kpi { font-size: 22px; font-weight: bold; }
        .muted { color: #6b7280; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 4px 6px; font-size: 9px; border-bottom: 1px solid #f3f4f6; }
        th { background: #f9fafb; text-transform: uppercase; color: #6b7280; }
        .note { white-space: pre-line; color: #4b5563; }
        .bar-row { font-size: 9px; color: #6b7280; margin-top: 4px; }
        .bar-track { height: 7px; background: #f3f4f6; }
        .bar-fill { height: 7px; background: #6366f1; }
        .empty { color: #6b7280; text-align: center; padding: 24px; }
    </style>
</head>
<body>
    <h1>{{ $dashboardName }}</h1>
    <p class="meta">Generated {{ $generatedAt->toDayDateTimeString() }}</p>

    @if (count($cards) === 0)
        <p class="empty">No cards on this dashboard.</p>
    @else
        @foreach ($cards as $card)
            <div class="card">
                @if (!empty($card['title']))
                    <h2>{{ $card['title'] }}</h2>
                @endif

                @if ($card['type'] === 'saved_report')
                    @php($rows = $card['rows'] ?? [])
                    @if (count($rows) === 0)
                        <p class="muted">No rows.</p>
                    @else
                        @php($columns = array_keys($rows[0]))
                        <table>
                            <thead><tr>@foreach ($columns as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>@foreach ($columns as $c)<td>{{ $row[$c] ?? '' }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                @elseif ($card['type'] === 'metric')
                    <p class="kpi">{{ $card['average'] !== null ? number_format((float) $card['average'], 2) : '—' }}{{ $card['unit'] ? ' '.$card['unit'] : '' }}</p>
                    <p class="muted">Average across {{ $card['count'] }} row(s)</p>
                @elseif ($card['type'] === 'kpi')
                    <p class="kpi">{{ $card['value'] !== null ? number_format((float) $card['value'], 2) : '—' }}{{ $card['unit'] ? ' '.$card['unit'] : '' }}</p>
                    <p class="muted">{{ ucfirst($card['agg']) }} across {{ $card['count'] }} row(s)</p>
                @elseif ($card['type'] === 'chart')
                    @php($points = $card['points'] ?? [])
                    @php($max = collect($points)->max('value') ?: 1)
                    @forelse ($points as $point)
                        <div class="bar-row">{{ $point['label'] !== '' ? $point['label'] : '—' }} — {{ number_format((float) $point['value'], 0) }}</div>
                        <div class="bar-track"><div class="bar-fill" style="width: {{ (int) round(($point['value'] / $max) * 100) }}%"></div></div>
                    @empty
                        <p class="muted">No data points.</p>
                    @endforelse
                @elseif ($card['type'] === 'text')
                    <p class="note">{{ $card['body'] }}</p>
                @endif
            </div>
        @endforeach
    @endif
</body>
</html>
