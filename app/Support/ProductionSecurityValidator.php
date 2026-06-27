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

        $this->abortOnCriticalMisconfig();
        $this->logProductionWarnings();
        $this->logCrossBorderTransferWarnings();
    }

    private function abortOnCriticalMisconfig(): void
    {
        $critical = $this->collectCriticalProductionMisconfig();
        if ($critical === []) {
            return;
        }

        $first = $critical[0];
        foreach ($critical as $msg) {
            Log::channel('security')->error('[SECURITY CONFIG CRITICAL] '.$msg);
        }

        throw new \RuntimeException(
            'PRODUCTION REFUSE-TO-BOOT: '.$first.
            (count($critical) > 1 ? ' (and '.(count($critical) - 1).' more — see security log)' : '')
        );
    }

    private function logProductionWarnings(): void
    {
        foreach ($this->collectProductionWarnings() as $msg) {
            Log::channel('security')->error('[SECURITY CONFIG WARNING] '.$msg);
        }
    }

    private function logCrossBorderTransferWarnings(): void
    {
        foreach ($this->collectCrossBorderTransferWarnings() as $msg) {
            Log::channel('security')->error('[KENYA DPA SECTION 48] '.$msg);
        }
    }

    /**
     * Misconfigs that MUST fail boot.
     *
     * @return array<int, string>
     */
    protected function collectCriticalProductionMisconfig(): array
    {
        $errors = [];

        $this->checkAppKeyMisconfig($errors);
        $this->checkDebugMisconfig($errors);
        $this->checkSessionMisconfig($errors);
        $this->checkMailerMisconfig($errors);

        return $errors;
    }

    /** @param array<int, string> $errors */
    private function checkAppKeyMisconfig(array &$errors): void
    {
        if (empty(config('app.key'))) {
            $errors[] = 'APP_KEY is empty. Encrypted columns (payment configs, KYC, 2FA secrets) are unreadable.';
        }
    }

    /** @param array<int, string> $errors */
    private function checkDebugMisconfig(array &$errors): void
    {
        if (config('app.debug') && config('app.env') !== 'local') {
            $errors[] = 'APP_DEBUG=true with APP_ENV='.config('app.env').'. Stack traces leak secrets to clients.';
        }
    }

    /** @param array<int, string> $errors */
    private function checkSessionMisconfig(array &$errors): void
    {
        if (! config('session.encrypt')) {
            $errors[] = 'SESSION_ENCRYPT is disabled. Session payloads must be encrypted in production.';
        }

        if (! config('session.secure')) {
            $errors[] = 'SESSION_SECURE_COOKIE is disabled. Cookies must be HTTPS-only in production.';
        }
    }

    /** @param array<int, string> $errors */
    private function checkMailerMisconfig(array &$errors): void
    {
        $mailer = config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            $errors[] = 'MAIL_MAILER='.$mailer.'. Transactional email is discarded; configure smtp/mailgun/ses.';
        }
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

        $this->warnOnHstsDisabled($warnings);
        $this->warnOnReverbPlaceholders($warnings);
        $this->warnOnSentryConfig($warnings);
        $this->warnOnLogLevel($warnings);
        $this->warnOnKenyaDpa($warnings);
        $this->warnOnBcryptRounds($warnings);
        $this->warnOnFilesystemDisk($warnings);
        $this->warnOnScaleReadiness($warnings);

        return $warnings;
    }

    /** @param array<int, string> $warnings */
    private function warnOnHstsDisabled(array &$warnings): void
    {
        if (! config('security.headers.hsts_enabled')) {
            $warnings[] = 'HSTS is disabled. Enable it for HTTPS-only enforcement.';
        }
    }

    /** @param array<int, string> $warnings */
    private function warnOnReverbPlaceholders(array &$warnings): void
    {
        $reverbSecret = (string) config('reverb.apps.apps.0.secret', '');
        $reverbKey = (string) config('reverb.apps.apps.0.key', '');
        $placeholderValues = ['your-secret-key-here', 'propmanager-key'];

        foreach ([$reverbSecret, $reverbKey] as $value) {
            if (in_array($value, $placeholderValues, true)) {
                $warnings[] = 'REVERB credentials still hold a placeholder value. Override REVERB_APP_KEY / REVERB_APP_SECRET.';
                break;
            }
        }
    }

    /** @param array<int, string> $warnings */
    private function warnOnSentryConfig(array &$warnings): void
    {
        if (empty(config('sentry.dsn'))) {
            $warnings[] = 'SENTRY_LARAVEL_DSN is empty. Error tracking is a silent no-op; OBS-1 has no production effect.';

            return;
        }

        // Phase-14 OBSERV-6: distributed tracing turn-on.
        $tracesRate = (float) config('sentry.traces_sample_rate', 0.0);
        if ($tracesRate <= 0.0) {
            $warnings[] = 'SENTRY_TRACES_SAMPLE_RATE=0 in production. No distributed-tracing data captured; latency regressions are invisible. Set to 0.1 (10%) as a starting point.';
        }
    }

    /** @param array<int, string> $warnings */
    private function warnOnLogLevel(array &$warnings): void
    {
        $logLevel = strtolower((string) config('logging.channels.single.level', 'debug'));
        if (in_array($logLevel, ['debug', 'info'], true)) {
            $warnings[] = 'LOG_LEVEL='.$logLevel.'. Production should be at warning or higher; debug logs SQL + bound PII.';
        }
    }

    /** @param array<int, string> $warnings */
    private function warnOnKenyaDpa(array &$warnings): void
    {
        if (config('security.kenya_dpa.enabled', true) && empty(config('security.kenya_dpa.registration'))) {
            $warnings[] = 'KENYA_DPA_ENABLED=true but KENYA_DPA_REGISTRATION is empty. Required by Kenya Data Protection Act for live use.';
        }
    }

    /** @param array<int, string> $warnings */
    private function warnOnBcryptRounds(array &$warnings): void
    {
        $rounds = (int) config('hashing.bcrypt.rounds', 10);
        if ($rounds < 12) {
            $warnings[] = 'BCRYPT_ROUNDS='.$rounds.' is below the production minimum of 12.';
        }
    }

    /** @param array<int, string> $warnings */
    private function warnOnFilesystemDisk(array &$warnings): void
    {
        $defaultDisk = (string) config('filesystems.default', 'local');
        if ($defaultDisk === 'local') {
            $warnings[] = 'FILESYSTEM_DISK=local in production. Uploads (KYC, lease docs, payment proofs) live on a single host\'s filesystem and disappear on restart.';
        }
    }

    /** @param array<int, string> $warnings */
    private function warnOnScaleReadiness(array &$warnings): void
    {
        $sessionDriver = (string) config('session.driver', 'file');
        if (in_array($sessionDriver, ['file', 'array'], true)) {
            $warnings[] = 'SESSION_DRIVER='.$sessionDriver.' in production. Sessions are per-host — they break behind a load balancer (PERF-SCALE-1). Use redis or database.';
        }

        $cacheStore = (string) config('cache.default', 'file');
        if (in_array($cacheStore, ['file', 'array'], true)) {
            $warnings[] = 'CACHE_STORE='.$cacheStore.' in production. The cache is per-host — hit-rate collapses behind a load balancer and invalidation cannot reach other instances (PERF-SCALE-1). Use redis.';
        }
    }

    /**
     * Phase-13 DPA-2: cross-border-transfer warnings. Returns one
     * string per detected transfer to a non-adequate-protection
     * destination.
     *
     * @return array<int, string>
     */
    protected function collectCrossBorderTransferWarnings(): array
    {
        if (! $this->app->environment('production')) {
            return [];
        }

        $warnings = [];
        $dpa = $this->app->make(\App\Services\KenyaDpaService::class);

        $this->warnOnBackupDiskTransfer($warnings, $dpa);
        $this->warnOnDefaultDiskTransfer($warnings, $dpa);
        $this->warnOnSentryDsnTransfer($warnings, $dpa);

        return $warnings;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function warnOnBackupDiskTransfer(array &$warnings, \App\Services\KenyaDpaService $dpa): void
    {
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
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function warnOnDefaultDiskTransfer(array &$warnings, \App\Services\KenyaDpaService $dpa): void
    {
        $defaultDisk = (string) config('filesystems.default', 'local');
        if ($defaultDisk === 'local') {
            return;
        }

        $region = (string) config("filesystems.disks.{$defaultDisk}.region", '');
        $country = $this->awsRegionToCountryCode($region);
        if ($country !== null) {
            $check = $dpa->canTransferCrossBorder($country);
            if (! $check['allowed']) {
                $warnings[] = "Default uploads disk '{$defaultDisk}' region={$region} resolves to country={$country} which lacks DPA Section 48 adequate-protection.";
            }
        }
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function warnOnSentryDsnTransfer(array &$warnings, \App\Services\KenyaDpaService $dpa): void
    {
        $sentryDsn = (string) config('sentry.dsn', '');
        if ($sentryDsn === '') {
            return;
        }

        $host = parse_url($sentryDsn, PHP_URL_HOST) ?: '';
        $sentryCountry = $this->sentryHostToCountryCode($host);
        if ($sentryCountry !== null) {
            $check = $dpa->canTransferCrossBorder($sentryCountry);
            if (! $check['allowed']) {
                $warnings[] = "Sentry DSN host={$host} resolves to country={$sentryCountry} which lacks DPA Section 48 adequate-protection. Switch to a Sentry EU project or self-hosted DSN.";
            }
        }
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

        if ($this->isSentryEuHost($host)) {
            return 'EU';
        }

        if (str_ends_with($host, '.sentry.io') || $host === 'sentry.io') {
            return 'US';
        }

        return null;
    }

    private function isSentryEuHost(string $host): bool
    {
        return str_contains($host, '.de.sentry.io')
            || str_starts_with($host, 'de.sentry.io')
            || str_contains($host, 'de.ingest.sentry.io');
    }
}
