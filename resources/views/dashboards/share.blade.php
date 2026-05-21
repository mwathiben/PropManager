<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $dashboardName }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; background: #f9fafb; color: #111827; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; }
        .meta { color: #6b7280; font-size: 0.8rem; margin-bottom: 16px; }
        .card { background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.05); border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .card h2 { font-size: 0.95rem; margin: 0 0 8px; }
        .kpi { font-size: 2rem; font-weight: 600; }
        .muted { color: #6b7280; font-size: 0.8rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: start; padding: 6px 10px; font-size: 0.8rem; border-bottom: 1px solid #f3f4f6; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 0.65rem; color: #6b7280; letter-spacing: .03em; }
        .bar-row { display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: #6b7280; margin-top: 6px; }
        .bar-track { height: 8px; background: #f3f4f6; border-radius: 9999px; margin-top: 2px; }
        .bar-fill { height: 8px; background: #6366f1; border-radius: 9999px; }
        .note { white-space: pre-line; color: #4b5563; font-size: 0.85rem; }
        .empty { color: #6b7280; font-size: 0.9rem; padding: 24px; text-align: center; background: #fff; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>{{ $dashboardName }}</h1>
        <p class="meta">Read-only shared dashboard · access expires {{ $expiresAt->toDayDateTimeString() }}</p>

        @if ($failed)
            <p class="empty">This dashboard is currently unavailable.</p>
        @elseif (count($cards) === 0)
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
                            <div class="bar-row"><span>{{ $point['label'] !== '' ? $point['label'] : '—' }}</span><span>{{ number_format((float) $point['value'], 0) }}</span></div>
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
    </div>
</body>
</html>
