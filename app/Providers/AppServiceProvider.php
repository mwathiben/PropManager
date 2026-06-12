<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\SmsServiceInterface;
use App\Models\Building;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use App\Observers\BuildingObserver;
use App\Observers\ExpenseObserver;
use App\Observers\InvoiceObserver;
use App\Observers\LateFeeObserver;
use App\Observers\LateFeePolicyObserver;
use App\Observers\LeaseObserver;
use App\Observers\PaymentObserver;
use App\Observers\PropertyObserver;
use App\Observers\RefundObserver;
use App\Observers\TicketObserver;
use App\Observers\UnitObserver;
use App\Observers\UserObserver;
use App\Observers\WaterReadingObserver;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Repositories\Contracts\NotificationDefaultsRepositoryInterface;
use App\Repositories\NotificationConfigRepository;
use App\Repositories\NotificationDefaultsRepository;
use App\Rules\PasswordPolicy;
use App\Services\AfricasTalkingService;
use App\Services\MetricsService;
use App\Services\PaymentGatewayManager;
use App\Services\SecurityLogger;
use App\Support\NPlusOneBaseline;
use App\Support\RateLimiterConfigurator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope is a local-only dev tool (require-dev). Register its
        // providers only in local AND only when the package is actually
        // installed, so `composer install --no-dev` (production + the
        // PERF-9 config:cache gate) never tries to autoload a missing
        // Laravel\Telescope\* class. See laravel.com/docs/telescope#local-only-installation
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }

        // Register SecurityLogger as a singleton
        $this->app->singleton(SecurityLogger::class, function ($app) {
            return new SecurityLogger($app['request']);
        });

        // OBS-11: Redis-backed counters for the payment / webhook /
        // notification hot paths. Singleton because it holds no state
        // beyond a connection name; safe to share across requests.
        $this->app->singleton(MetricsService::class, fn () => new MetricsService(
            config('metrics.connection', 'cache')
        ));

        // Phase-74 CARD-REGISTRY: the dashboard card-renderer registry. New
        // card types are added by appending a renderer here — never by editing
        // the security-sensitive DashboardService render path. Each renderer
        // re-validates landlord ownership of its referenced report/metric.
        $this->app->singleton(\App\Services\Reports\DashboardCardRegistry::class, fn ($app) => new \App\Services\Reports\DashboardCardRegistry([
            $app->make(\App\Services\Reports\Cards\SavedReportCardRenderer::class),
            $app->make(\App\Services\Reports\Cards\MetricCardRenderer::class),
            $app->make(\App\Services\Reports\Cards\KpiCardRenderer::class),
            $app->make(\App\Services\Reports\Cards\ChartCardRenderer::class),
            $app->make(\App\Services\Reports\Cards\TextCardRenderer::class),
        ]));

        // Phase-67 ATTACHMENT-SCAN-1: bind the configured attachment
        // scanner (null / clamav / fake) for MessageAttachmentService.
        $this->app->bind(
            \App\Services\Inbox\Scanning\AttachmentScannerInterface::class,
            fn () => \App\Services\Inbox\Scanning\AttachmentScannerFactory::make(),
        );

        // Phase-45 EMERGENCY-CONTACT-SMS-1: SMS driver binding.
        // Default is Stub so CI + dev never hit the network. Switch
        // via SMS_DRIVER=africastalking in production.
        $this->app->bind(\App\Services\Sms\Contracts\SmsDriver::class, function ($app) {
            $driver = config('sms.driver', 'stub');
            if ($driver === 'africastalking') {
                return new \App\Services\Sms\AfricasTalkingSmsDriver(
                    config('sms.africastalking.username'),
                    config('sms.africastalking.api_key'),
                    config('sms.africastalking.sender_id'),
                    config('sms.africastalking.endpoint', 'https://api.africastalking.com/version1/messaging'),
                );
            }

            return new \App\Services\Sms\StubSmsDriver;
        });

        // Register notification config repository
        $this->app->bind(
            NotificationConfigRepositoryInterface::class,
            NotificationConfigRepository::class
        );

        // Register notification defaults repository
        $this->app->bind(
            NotificationDefaultsRepositoryInterface::class,
            NotificationDefaultsRepository::class
        );

        // Register SMS service (Africa's Talking adapter)
        $this->app->bind(SmsServiceInterface::class, AfricasTalkingService::class);

        // Phase-39 VENDOR-ANALYTICS-1: bind AnalyticsForwarderInterface
        // to the configured vendor implementation. PostHog is the only
        // implementation today; future vendors (Mixpanel/Heap/Amplitude)
        // pick up here when their adapter ships.
        $this->app->singleton(
            \App\Services\Vendors\AnalyticsForwarderInterface::class,
            function () {
                if (config('vendors.posthog.enabled') && config('vendors.posthog.api_key')) {
                    return new \App\Services\Vendors\PostHogForwarder(
                        apiKey: (string) config('vendors.posthog.api_key'),
                        host: (string) config('vendors.posthog.host'),
                    );
                }

                // Null-object forwarder so callers don't have to null-check.
                return new class implements \App\Services\Vendors\AnalyticsForwarderInterface
                {
                    public function vendor(): string
                    {
                        return 'noop';
                    }

                    public function flush(array $events): array
                    {
                        return ['accepted' => 0, 'rejected' => 0, 'retryable' => 0, 'vendor' => 'noop'];
                    }
                };
            },
        );

        // Register payment gateway manager as singleton
        $this->app->singleton(PaymentGatewayManager::class);

        // Bind interface to default gateway
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayManager::class)->defaultGateway();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Phase-16 RESIL-7: house-style HTTP preset. Any new outbound
        // call site can do `Http::resilient()->get(...)` and inherit
        // the 5s connect / 15s overall timeout + 2-retry policy. Pre-
        // fix the default was 30s no-retry; ad-hoc usages routinely
        // forgot to set ->timeout().
        Http::macro('resilient', fn () => Http::connectTimeout(5)->timeout(15)->retry(2, 200, throw: false));

        // Phase-57 READ-REPLICAS-1: ->readOnly() marker macro on the Eloquent
        // Builder. By default Laravel routes SELECTs to the read pool when
        // database.php has a read/write split configured. sticky=true (our
        // current setting) pins everything to primary after any write in the
        // request — which is the right default for correctness but means
        // heavy aggregates inside write-touched requests don't get the
        // replica benefit.
        //
        // This macro is a no-op today (Laravel has no per-query sticky
        // override) but tags the query for ops visibility + future-compat
        // with a custom resolver that respects the flag. The intent marker
        // matters: when we deploy a real replica and add the resolver,
        // every tagged callsite is already opted in.
        \Illuminate\Database\Eloquent\Builder::macro('readOnly', function () {
            $this->withCasts([]); // no-op chain; macro must return $this

            return $this;
        });

        // Phase-58 TENANT-DISK-RESOLVER-2: Storage::tenant() macro.
        // Every callsite that used to read from the local-pinned disk now
        // reads `Storage::tenant()` and flows through TenantDiskResolver
        // → config('filesystems.tenant_disk'). Operators flip the
        // underlying disk via FILESYSTEM_TENANT_DISK env var.
        \Illuminate\Support\Facades\Storage::macro(
            'tenant',
            fn (?int $landlordId = null) => app(\App\Services\Storage\TenantDiskResolver::class)->resolve($landlordId),
        );

        // CRYPTO-1: wire the project-wide password rules so every
        // Rules\Password::defaults() in controllers/Form Requests applies
        // them. Without this the PasswordPolicy class (HIBP fail-open
        // hardening from Phase-4 HANDLE-11, the 22-password banlist, the
        // 12-char minimum, and the symbol enforcement) is dead code.
        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->rules([new PasswordPolicy]));

        // Register model observers
        Property::observe(PropertyObserver::class);
        Building::observe(BuildingObserver::class);
        Unit::observe(UnitObserver::class);
        WaterReading::observe(WaterReadingObserver::class);
        Ticket::observe(TicketObserver::class);
        User::observe(UserObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);

        // Finance cache invalidation observers
        Expense::observe(ExpenseObserver::class);
        LateFee::observe(LateFeeObserver::class);
        LateFeePolicy::observe(LateFeePolicyObserver::class);
        Lease::observe(LeaseObserver::class);
        Refund::observe(RefundObserver::class);
        // Phase-54 SLA-LANDLORD-UI-3: flush SlaDefinitionService cache on write.
        \App\Models\SlaDefinition::observe(\App\Observers\SlaDefinitionObserver::class);
        // Phase-54 VENDOR-ONBOARDING-1: signed-URL welcome mail on Vendor::created.
        \App\Models\Vendor::observe(\App\Observers\VendorObserver::class);
        // Phase-75 PARTS-PRICING-1: append a price-history row on part cost change.
        \App\Models\Part::observe(\App\Observers\PartObserver::class);

        // Prevent lazy loading in non-production to catch N+1 queries.
        // OBS-9: in production, sample 1% of requests so genuine N+1
        // regressions still surface in logs without hard-throwing on
        // every request. The handler always logs (never throws) in prod
        // so a lazy-load can't take a customer page down.
        //
        // Phase-22 PERF-NPLUS1-1: in the TESTING environment the handler
        // THROWS (Laravel's default LazyLoadingViolationException) so an
        // N+1 in a tested code path fails its test — turning the
        // detector from a passive logger into a CI gate. Known
        // pre-existing offenders on App\Support\NPlusOneBaseline::ALLOWED
        // are logged-not-thrown so the gate is tractable; PERF-NPLUS1-2
        // drives that list to empty.
        $isTesting = app()->environment('testing');
        $shouldDetectLazyLoading = ! app()->environment('production')
            || (app()->runningInConsole() ? false : random_int(1, 100) === 1);

        if ($shouldDetectLazyLoading) {
            Model::preventLazyLoading();

            Model::handleLazyLoadingViolationUsing(function ($model, $relation) use ($isTesting) {
                $modelClass = get_class($model);

                if ($isTesting && ! NPlusOneBaseline::isAllowed($modelClass, $relation)) {
                    throw new LazyLoadingViolationException($model, $relation);
                }

                Log::channel('security')->warning('N+1 Query Detected', [
                    'model' => $modelClass,
                    'relation' => $relation,
                    'environment' => app()->environment(),
                    'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10))
                        ->filter(fn ($frame) => isset($frame['file']) && ! str_contains($frame['file'], '/vendor/'))
                        ->take(5)
                        ->map(fn ($frame) => ($frame['file'] ?? '').':'.($frame['line'] ?? ''))
                        ->values()
                        ->toArray(),
                ]);
            });
        }

        // Configure rate limiters
        (new RateLimiterConfigurator)->configure();

        // Validate security configuration in production
        $this->validateProductionSecurity();
    }

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
    protected function validateProductionSecurity(): void
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
