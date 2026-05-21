<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Maintenance Photos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #EA580C;
            padding-bottom: 8px;
        }

        .header h1 {
            margin: 0;
            color: #EA580C;
            font-size: 22px;
        }

        .meta {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-bottom: 16px;
        }

        .photo {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .photo img {
            max-width: 320px;
            max-height: 240px;
            border: 1px solid #e5e7eb;
        }

        .caption {
            font-size: 10px;
            color: #555;
            margin-top: 4px;
        }

        .caption .ref {
            font-weight: bold;
            color: #111;
        }

        .empty {
            text-align: center;
            color: #999;
            margin-top: 40px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Maintenance Photos</h1>
        <div>{{ $landlord->name ?? '' }}</div>
    </div>

    <div class="meta">Generated {{ $generated_at }} · {{ $images->count() }} photo(s)</div>

    @forelse ($images as $image)
        <div class="photo">
            <img src="{{ $image['data_uri'] }}" alt="Maintenance photo">
            <div class="caption">
                <span class="ref">{{ $image['ticket_ref'] }}</span>
                @if ($image['building'])
                    · {{ $image['building'] }}@if ($image['unit']) / {{ $image['unit'] }}@endif
                @endif
                @if ($image['category']) · {{ $image['category'] }} @endif
                · {{ $image['date'] }}
                @if ($image['annotation_count'] > 0)
                    · {{ $image['annotation_count'] }} annotation(s)
                @endif
            </div>
        </div>
    @empty
        <div class="empty">No photos match the selected filters.</div>
    @endforelse
</body>

</html>
