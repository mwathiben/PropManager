<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.55; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .hash { font-size: 9px; color: #888; margin-bottom: 18px; word-break: break-all; }
        .body { white-space: pre-wrap; }
        .footer { margin-top: 28px; font-size: 9px; color: #888; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <h1>{{ $agreement->title }}</h1>
    <div class="hash">Document fingerprint (SHA-256): {{ $agreement->content_hash }}</div>
    <div class="body">{{ $agreement->rendered_body }}</div>
    <div class="footer">PropManager &middot; This document is bound by the SHA-256 fingerprint above.</div>
</body>
</html>
