<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportName }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; background: #f9fafb; color: #111827; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; }
        .meta { color: #6b7280; font-size: 0.8rem; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.05); border-radius: 8px; overflow: hidden; }
        th, td { text-align: start; padding: 8px 12px; font-size: 0.85rem; border-bottom: 1px solid #f3f4f6; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 0.7rem; color: #6b7280; letter-spacing: .03em; }
        .empty { color: #6b7280; font-size: 0.9rem; padding: 24px; text-align: center; background: #fff; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>{{ $reportName }}</h1>
        <p class="meta">Read-only shared report · access expires {{ $expiresAt->toDayDateTimeString() }}</p>

        @if ($failed)
            <p class="empty">This report is currently unavailable.</p>
        @elseif (count($rows) === 0)
            <p class="empty">No data.</p>
        @else
            <table>
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $column)
                                <td>{{ $row[$column] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
