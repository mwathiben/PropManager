# Mailpit — SMTP Capture for Email Testing

Mailpit intercepts all outgoing SMTP traffic and exposes it via a web UI and REST API.
Tests verify email rendering, headers, links, and delivery without touching a real mail server.

- **SMTP**: `127.0.0.1:1025`
- **Web UI / API**: `http://localhost:8025`

## Current Setup (Laragon)

Mailpit is bundled with the Laragon dev environment and auto-starts:

```
C:\laragon\bin\mailpit\1.22.3\mailpit.exe
```

Verify it's running:

```bash
curl -s http://localhost:8025/api/v1/messages
```

Expected: JSON response with `"total":0` (or current message count).

## Fresh Installation

### Windows

Download the latest release binary:

1. Go to <https://github.com/axllent/mailpit/releases/latest>
2. Download `mailpit-windows-amd64.zip`
3. Extract `mailpit.exe` to this directory (`tools/mailpit/`) or `C:\laragon\bin\mailpit\`
4. The `.gitignore` already excludes `tools/mailpit/mailpit*` — the binary will not be committed

### macOS

```bash
brew install mailpit
```

### Linux

```bash
sudo sh < <(curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh)
```

## Running

Start with default ports (SMTP 1025, HTTP 8025):

```bash
mailpit
```

Custom ports (if defaults conflict):

```bash
mailpit --smtp-bind-addr 127.0.0.1:2025 --listen 127.0.0.1:9025
```

## Test Integration

Tests configure SMTP to point at Mailpit via runtime `config()` overrides.
The `OverridesMailConfig` trait (`tests/Traits/OverridesMailConfig.php`) handles this:

```php
config([
    'mail.default' => 'smtp',
    'mail.mailers.smtp.host' => '127.0.0.1',
    'mail.mailers.smtp.port' => 1025,
    'mail.mailers.smtp.encryption' => null,
]);
```

**Do not modify `.env`, `.env.testing`, or `.env.dusk.local` for SMTP configuration.**
All test SMTP routing is handled by the trait at runtime. See E2E-MAIL-015 for rationale.

## API Reference

Base URL: `http://localhost:8025/api/v1`

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `/messages` | GET | List all captured messages |
| `/message/{id}` | GET | Full message (HTML, Text, headers, attachments) |
| `/message/latest` | GET | Most recent message |
| `/message/{id}/headers` | GET | Message headers as key-value JSON |
| `/search?query=to:user@example.com` | GET | Search by recipient, subject, sender |
| `/messages` | DELETE | Delete all messages (empty body) |
| `/view/{id}.html` | GET | Rendered HTML (for browser/iframe display) |

Search query syntax supports `to:`, `from:`, `subject:`, `body:`, and `tag:` operators.

Full interactive API docs: <http://localhost:8025/api/v1/> (Swagger UI, requires Mailpit running).

## Troubleshooting

### Port conflict on Windows

Windows Hyper-V reserves dynamic port ranges that may include 1025 or 8025.
Check reserved ranges:

```bash
netsh interface ipv4 show excludedportrange protocol=tcp
```

If either port is reserved, start Mailpit on alternative ports:

```bash
mailpit --smtp-bind-addr 127.0.0.1:2025 --listen 127.0.0.1:9025
```

Then update `OverridesMailConfig` trait to match the new SMTP port.

### Connection refused

Mailpit must be running before tests execute. Laragon auto-starts it, but if running
tests outside Laragon, start Mailpit manually first.

### Quick send test

```bash
php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['mail.default'=>'smtp','mail.mailers.smtp.host'=>'127.0.0.1','mail.mailers.smtp.port'=>1025,'mail.mailers.smtp.encryption'=>null]);
Illuminate\Support\Facades\Mail::raw('Mailpit test', function(\$m){ \$m->to('test@example.com')->subject('E2E Test'); });
echo 'Sent — check http://localhost:8025';
"
```

## Security

- Mailpit binds to `127.0.0.1` only — not accessible from the network
- No authentication required for local development
- Captured emails are stored in memory (ephemeral) — cleared on restart
- Never deploy Mailpit to production environments
- Test data uses factory-generated PII, not real user data
