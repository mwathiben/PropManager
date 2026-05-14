<!DOCTYPE html>
{{--
    Phase-22 PERF-SCALE-2: maintenance-mode page (HTTP 503).

    Rendered by Laravel for `php artisan down`. DELIBERATELY
    self-contained — no Vite assets, no Inertia, no external fonts:
    maintenance mode is exactly when the app/asset pipeline may be
    mid-deploy, so this page must render with zero dependencies.

    Inertia XHR visits that hit a 503 are handled client-side by the
    Inertia adapter (it surfaces the response); a plain browser load
    gets this page directly. Either way the user sees a calm, branded
    "we'll be right back" instead of a stack trace or a blank 503.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>PropManager — Down for maintenance</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f9fafb;
            color: #111827;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            max-width: 28rem;
            text-align: center;
        }
        .badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #4f46e5;
            margin-bottom: 0.75rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        p {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #4b5563;
        }
        .meta {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="badge">PropManager</span>
        <h1>We'll be right back</h1>
        <p>
            PropManager is briefly down for scheduled maintenance. Your data is
            safe — rent records, payments and documents are untouched. Please
            check back in a few minutes.
        </p>
        <p class="meta">If this persists, contact your account administrator.</p>
    </main>
</body>
</html>
