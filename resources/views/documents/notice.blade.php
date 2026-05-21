<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.6; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; }
        .meta { margin-bottom: 16px; }
        .meta div { margin-bottom: 2px; }
        .label { color: #6b7280; }
        .body { margin-top: 16px; white-space: pre-line; }
        .footer { margin-top: 40px; color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ __('document.notice.heading_'.$noticeType) }}</div>
    </div>

    <div class="meta">
        <div><span class="label">{{ __('document.notice.to') }}:</span> {{ $tenant?->name ?? '—' }}</div>
        <div><span class="label">{{ __('document.notice.property') }}:</span> {{ $property?->name ?? '—' }}</div>
        <div><span class="label">{{ __('document.notice.unit') }}:</span> {{ $unit?->unit_number ?? '—' }}</div>
        <div><span class="label">{{ __('document.notice.date') }}:</span> {{ $generatedAt->format('d M Y') }}</div>
        @if($effectiveDate)
            <div><span class="label">{{ __('document.notice.effective_date') }}:</span> {{ \Illuminate\Support\Carbon::parse($effectiveDate)->format('d M Y') }}</div>
        @endif
    </div>

    @if($reason)
        <div class="body">{{ $reason }}</div>
    @endif

    <div class="footer">{{ $building?->name }} · {{ $generatedAt->format('Y-m-d H:i') }}</div>
</body>
</html>
