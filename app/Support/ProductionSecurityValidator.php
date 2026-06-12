<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

/**
 * Production-config security validator, extracted from AppServiceProvider
 * (M2 decomposition). Two tiers: CRITICAL misconfig throws \RuntimeException
 * so a misconfigured production refuses to boot; WARNING logs to the
 * 'security' channel (→ Sentry). Also flags Kenya-DPA section-48
 * cross-border-transfer risks. AppServiceProvider::boot() calls
 * validateProductionSecurity(). Verbatim move (the container is injected
 * for the $this->app->environment()/make() calls).
 */
class ProductionSecurityValidator
{
    public function __construct(private readonly Application $app) {}

    /**
     * Phase-11 DEPLOY-2 / SECRETS-{1,2,3,6,7}: production-config
     * validator now splits into two tiers:
     *
     *  - CRITICAL: throw RuntimeException so the app refuses to boot.
     *    A misconfigured prod that ships will leak secrets, send mail
     *    nowhere, or run unencrypted sessions. Failing-closed is the
     *    only acceptable answer.
     *
     *  - WARNING: log to the security channel. After OBS-1 the security
     *    channel flows to Sentry, so warnings escalate to ops alerts.
     *
     * The phase-5 OBS-1 / CRYPTO-11 / CRYPTO-12 hooks are all guarded
     * here so a deploy that forgets to flip a flag fails fast.
     */
    public function validateProductionSecurity(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $critical = $this->collectCriticalProductionMisconfig();
        if ($critical !== []) {
            $first = $critical[0];
            // Surface all failures to logs first so the dump in the
            // exception is the tip of the iceberg, not the only signal.
            foreach ($critical as $msg) {
                Log::channel('security')->error('[SECURITY CONFIG CRITICAL] '.$msg);
            }

            throw new \RuntimeException(
                'PRODUCTION REFUSE-TO-BOOT: '.$first.
                (count($critical) > 1 ? ' (and '.(count($critical) - 1).' more — see security log)' : '')
            );
        }

        foreach ($this->collectProductionWarnings() as $msg) {
            Log::channel('security')->error('[SECURITY CONFIG WARNING] '.$msg);
        }

        // Phase-13 DPA-2: cross-border-transfer validation at boot.
        // Section 48 of the Kenya DPA / Article 44 of GDPR require
        // adequate-protection safeguards for any transfer of personal
        // data outside the destination country. The KenyaDpaService::
        // canTransferCrossBorder helper has existed with zero callers;
        // this loop wires the call sites that matter most at boot.
        foreach ($this->collectCrossBorderTransferWarnings() as $msg) {
            Log::channel('security')->error('[KENYA DPA SECTION 48] '.$msg);
        }
    }

    /**
     * Misconfigs that MUST fail boot. Any one of these in production
     * means real harm — silent data loss, plaintext session cookies on
     * the wire, etc.
     *
     * @return array<int, string>
     */
    protected function collectCriticalProductionMisconfig(): array
    {
        $errors = [];

        // SECRETS-1: APP_KEY empty -> Crypt::* throws on every encrypted
        // column read/write -> entire app effectively dead. Fail closed.
        if (empty(config('app.key'))) {
            $errors[] = 'APP_KEY is empty. Encrypted columns (payment configs, KYC, 2FA secrets) are unreadable.';
        }

        // SECRETS-2: APP_DEBUG=true outside local exposes stack traces
        // (with env values) to the browser. The previous validator only
        // caught environment('production'); staging/uat with debug=true
        // is also harmful.
        if (config('app.debug') && config('app.env') !== 'local') {
            $errors[] = 'APP_DEBUG=true with APP_ENV='.config('app.env').'. Stack traces leak secrets to clients.';
        }

        // DEPLOY-2 / SECRETS-6: unencrypted or insecure cookies = session
        // hijack vector. Both must be on in production.
        if (! config('session.encrypt')) {
            $errors[] = 'SESSION_ENCRYPT is disabled. Session payloads must be encrypted in production.';
        }
        if (! config('session.secure')) {
            $errors[] = 'SESSION_SECURE_COOKIE is disabled. Cookies must be HTTPS-only in production.';
        }

        // SECRETS-7: MAIL_MAILER=log silently drops every transactional
        // email (rent reminders, payment receipts, KYC). Failure is
        // invisible until tenant complaints.
        $mailer = config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            $errors[] = 'MAIL_MAILER='.$mailer.'. Transactional email is discarded; configure smtp/mailgun/ses.';
        }

        return $errors;
    }

    /**
     * Non-fatal but ops-actionable misconfigs. Logged at error level so
     * the OBS-1 Sentry channel mapping catches them.
     *
     * @return array<int, string>
     */
    protected function collectProductionWarnings(): array
    {
        $warnings = [];

        // Existing HSTS check kept as warning — sites behind a CDN can
        // reasonably defer HSTS to the edge.
        if (! config('security.headers.hsts_enabled')) {
            $warnings[] = 'HSTS is disabled. Enable it for HTTPS-only enforcement.';
        }

        // CRYPTO-12 (already shipped): refuse the historic Reverb placeholder.
        $reverbSecret = (string) config('reverb.apps.apps.0.secret', '');
        $reverbKey = (string) config('reverb.apps.apps.0.key', '');
        $placeholderValues = ['your-secret-key-here', 'propmanager-key'];
        foreach ([$reverbSecret, $reverbKey] as $value) {
            if (in_array($value, $placeholderValues, true)) {
                $warnings[] = 'REVERB credentials still hold a placeholder value. Override REVERB_APP_KEY / REVERB_APP_SECRET.';
                break;
            }
        }

        // DEPLOY-3 / SECRETS-3: Phase-5 OBS-1 wired Sentry but the SDK
        // no-ops when SENTRY_LARAVEL_DSN is empty. Surface that.
        if (empty(config('sentry.dsn'))) {
            $warnings[] = 'SENTRY_LARAVEL_DSN is empty. Error tracking is a silent no-op; OBS-1 has no production effect.';
        }

        // Phase-14 OBSERV-6: distributed tracing turn-on. Without
        // traces, slow-but-not-error is invisible. 0.0 = no traces;
        // typical production: 0.05-0.2.
        $tracesRate = (float) config('sentry.traces_sample_rate', 0.0);
        if (! empty(config('sentry.dsn')) && $tracesRate <= 0.0) {
            $warnings[] = 'SENTRY_TRACES_SAMPLE_RATE=0 in production. No distributed-tracing data captured; latency regressions are invisible. Set to 0.1 (10%) as a starting point.';
        }

        // SECRETS-5: debug-level logging in production writes SQL +
        // bound PII to log files — Kenya DPA hazard. info-level is
        // similarly verbose. Warn so ops flips to warning+ explicitly.
        $logLevel = strtolower((string) config('logging.channels.single.level', 'debug'));
        if (in_array($logLevel, ['debug', 'info'], true)) {
            $warnings[] = 'LOG_LEVEL='.$logLevel.'. Production should be at warning or higher; debug logs SQL + bound PII.';
        }

        // SECRETS-10: Kenya DPA requires a registered Data Controller
        // number for any personal-data-processing entity. KENYA_DPA_ENABLED
        // defaults to true; the registration number must be set before
        // accepting tenants. Warn so the gap is visible during launch.
        if (config('security.kenya_dpa.enabled', true) && empty(config('security.kenya_dpa.registration'))) {
            $warnings[] = 'KENYA_DPA_ENABLED=true but KENYA_DPA_REGISTRATION is empty. Required by Kenya Data Protection Act for live use.';
        }

        // SECRETS-11: BCRYPT_ROUNDS=10 is bcrypt's library default and
        // too weak for production. We require >=12. A regressed env
        // value silently weakens password hashing for every new user.
        $rounds = (int) config('hashing.bcrypt.rounds', 10);
        if ($rounds < 12) {
            $warnings[] = 'BCRYPT_ROUNDS='.$rounds.' is below the production minimum of 12.';
        }

        // BACKUP-5: FILESYSTEM_DISK=local in production means uploads
        // live on a single host's filesystem — gone on container
        // restart or instance loss. Force operators to choose a
        // durable disk (s3, do_spaces) or explicitly opt in.
        $defaultDisk = (string) config('filesystems.default', 'local');
        if ($defaultDisk === 'local') {
            $warnings[] = 'FILESYSTEM_DISK=local in production. Uploads (KYC, lease docs, payment proofs) live on a single host\'s filesystem and disappear on restart.';
        }

        // Phase-22 PERF-SCALE-1: horizontal-scale readiness. Behind a
        // load balancer with >1 app instance, file/array session +
        // cache stores are per-host — a user's session vanishes when
        // they hit a different instance, and cache hit-rate collapses.
        // Production must externalise both (redis / database).
        $sessionDriver = (string) config('session.driver', 'file');
        if (in_array($sessionDriver, ['file', 'array'], true)) {
            $warnings[] = 'SESSION_DRIVER='.$sessionDriver.' in production. Sessions are per-host — they break behind a load balancer (PERF-SCALE-1). Use redis or database.';
        }

        $cacheStore = (string) config('cache.default', 'file');
        if (in_array($cacheStore, ['file', 'array'], true)) {
            $warnings[] = 'CACHE_STORE='.$cacheStore.' in production. The cache is per-host — hit-rate collapses behind a load balancer and invalidation cannot reach other instances (PERF-SCALE-1). Use redis.';
        }

        return $warnings;
    }

    /**
     * Phase-13 DPA-2: cross-border-transfer warnings. Returns one
     * string per detected transfer to a non-adequate-protection
     * destination. Strings are routed through the KENYA DPA SECTION
     * 48 log prefix so Sentry's OBS-1 channel captures them but they
     * do not block boot (warning-level, not critical).
     *
     * Three boundaries are inspected: S3 backup destination region,
     * uploads disk region (when an S3-like disk is the default),
     * and the Sentry DSN host.
     *
     * @return array<int, string>
     */
    protected function collectCrossBorderTransferWarnings(): array
    {
        // Outside production we don't run this — local dev points at
        // localhost everywhere and the noise would drown signal.
        if (! $this->app->environment('production')) {
            return [];
        }

        $warnings = [];
        $dpa = $this->app->make(\App\Services\KenyaDpaService::class);

        // (a) S3 region for the laravel-backup destination.
        $backupDisks = (array) config('backup.backup.destination.disks', []);
        foreach ($backupDisks as $diskName) {
            $diskRegion = (string) config("filesystems.disks.{$diskName}.region", '');
            $country = $this->awsRegionToCountryCode($diskRegion);
            if ($country !== null) {
                $check = $dpa->canTransferCrossBorder($country);
                if (! $check['allowed']) {
                    $warnings[] = "Backup disk '{$diskName}' region={$diskRegion} resolves to country={$country} which lacks DPA Section 48 adequate-protection. Add SCCs, BCRs, or explicit consent before going live.";
                }
            }
        }

        // (b) Default uploads disk (FILESYSTEM_DISK) when it's an
        // S3-like region-bearing disk.
        $defaultDisk = (string) config('filesystems.default', 'local');
        if ($defaultDisk !== 'local') {
            $region = (string) config("filesystems.disks.{$defaultDisk}.region", '');
            $country = $this->awsRegionToCountryCode($region);
            if ($country !== null) {
                $check = $dpa->canTransferCrossBorder($country);
                if (! $check['allowed']) {
                    $warnings[] = "Default uploads disk '{$defaultDisk}' region={$region} resolves to country={$country} which lacks DPA Section 48 adequate-protection.";
                }
            }
        }

        // (c) Sentry DSN host. EU-region Sentry SaaS hosts contain
        // 'de.' or '.eu' in the ingest host; everything else from
        // sentry.io is the US ingestor (non-adequate). Self-hosted
        // DSNs are operator-managed and we don't warn on those.
        $sentryDsn = (string) config('sentry.dsn', '');
        if ($sentryDsn !== '') {
            $host = parse_url($sentryDsn, PHP_URL_HOST) ?: '';
            $sentryCountry = $this->sentryHostToCountryCode($host);
            if ($sentryCountry !== null) {
                $check = $dpa->canTransferCrossBorder($sentryCountry);
                if (! $check['allowed']) {
                    $warnings[] = "Sentry DSN host={$host} resolves to country={$sentryCountry} which lacks DPA Section 48 adequate-protection. Switch to a Sentry EU project or self-hosted DSN.";
                }
            }
        }

        return $warnings;
    }

    /**
     * Map an AWS region code to an ISO 3166-1 country code, leaving
     * unknown regions as null (no warning). Conservative: missing
     * regions are silent (we'd rather under-warn than spam ops with
     * false positives for self-hosted S3 alternatives).
     */
    protected function awsRegionToCountryCode(string $region): ?string
    {
        return match (true) {
            $region === '' => null,
            str_starts_with($region, 'us-') => 'US',
            str_starts_with($region, 'ca-') => 'CA',
            str_starts_with($region, 'eu-') => 'EU',
            str_starts_with($region, 'af-south-') => 'ZA',
            str_starts_with($region, 'ap-northeast-1') => 'JP',
            str_starts_with($region, 'ap-northeast-2') => 'KR',
            str_starts_with($region, 'ap-northeast-3') => 'JP',
            str_starts_with($region, 'ap-south-') => 'IN',
            str_starts_with($region, 'ap-southeast-3') => 'ID',
            str_starts_with($region, 'ap-southeast-') => 'SG',
            str_starts_with($region, 'me-') => 'AE',
            str_starts_with($region, 'sa-') => 'BR',
            default => null,
        };
    }

    /**
     * Heuristic mapping of Sentry SaaS DSN host to country code. Self-
     * hosted DSNs are intentionally unmapped (we don't know their
     * locale). The EU subdomain (`de.`, `ingest.de.`) is reliable
     * because that's the Sentry EU SaaS naming convention.
     */
    protected function sentryHostToCountryCode(string $host): ?string
    {
        $host = strtolower($host);
        if ($host === '') {
            return null;
        }
        if (str_contains($host, '.de.sentry.io') || str_starts_with($host, 'de.sentry.io')
            || str_contains($host, 'de.ingest.sentry.io')) {
            return 'EU';
        }
        if (str_ends_with($host, '.sentry.io') || $host === 'sentry.io') {
            return 'US';
        }

        return null;
    }
}
