# Web Server Configuration

Phase-15 PERF-8 + PERF-10: operator-side configuration for production web servers (nginx / Apache / Caddy) that PropManager assumes but cannot enforce from PHP. This document is the source of truth — when bringing up a new environment, match it.

## Compression (PERF-10)

PropManager assumes responses are gzip- or brotli-compressed by the web server. Without compression, 200KB JSON dashboards ship uncompressed to mobile clients on 3G — slow form submits and timeouts.

### nginx

```nginx
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_proxied any;
gzip_comp_level 6;
gzip_types
    application/json
    application/javascript
    application/x-javascript
    application/xml
    application/xml+rss
    application/atom+xml
    image/svg+xml
    text/css
    text/javascript
    text/plain
    text/xml;

# Brotli (preferred when available, requires ngx_brotli module)
brotli on;
brotli_static on;
brotli_types application/json application/javascript text/css text/plain;
```

### Apache

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/json application/javascript text/css text/plain
</IfModule>

<IfModule mod_brotli.c>
    AddOutputFilterByType BROTLI_COMPRESS application/json application/javascript text/css
</IfModule>
```

### Caddy

Caddy compresses automatically. Verify with:

```bash
curl -H 'Accept-Encoding: gzip,br' -I https://yourdomain/api/health
# Expect: Content-Encoding: br
```

## Cache headers for static assets (PERF-8)

Vite emits content-hashed filenames (e.g. `app-DfRwc.js`) in `public/build/`. These are immutability candidates — every deploy ships a new hash, so the browser can cache the old one forever. Without long-cache headers, the browser re-downloads on every visit.

### nginx

```nginx
location /build/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    add_header X-Content-Type-Options "nosniff";
    access_log off;
}
```

### Apache

```apache
<Directory "/var/www/propmanager/public/build">
    <FilesMatch "\.(js|css|woff2?|png|jpe?g|gif|svg)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
</Directory>
```

### Caddy

```caddy
@build_assets path /build/*
header @build_assets Cache-Control "public, max-age=31536000, immutable"
```

## Phase-15 PERF-10 follow-up: in-app fallback middleware

If the web server config cannot be modified (managed hosting, etc.), an optional Middleware `StampBuildCacheHeaders` could be added that sets `Cache-Control` on `/build/*` responses. The web-server-side config is preferable because it avoids PHP touching the response for every asset request.

## Verification checklist

Run this from a deploy-side smoke test to verify the operator config:

```bash
# Compression
curl -sI -H 'Accept-Encoding: gzip,br' "https://${HOST}/api/health" \
  | grep -i content-encoding \
  || echo "MISSING: response not compressed"

# Build asset cache
curl -sI "https://${HOST}/build/app.js" \
  | grep -i 'cache-control' \
  | grep -q 'immutable' \
  || echo "MISSING: /build/ not stamped with immutable cache header"

# HSTS (Phase-5 SECRETS-1)
curl -sI "https://${HOST}/" \
  | grep -i 'strict-transport-security' \
  || echo "MISSING: HSTS not enabled"
```

## Cross-references

- Phase-11 DEPLOY: `scripts/deploy.sh` for the on-host deploy flow
- Phase-12 BACKUP: `docs/runbooks/disaster-recovery.md` for storage-side config
- Phase-14 OBSERV-1: `/api/metrics` endpoint — ensure web server allows the scraper IP allowlist
