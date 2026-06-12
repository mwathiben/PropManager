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
use App\Support\ProductionSecurityValidator;
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
        (new ProductionSecurityValidator($this->app))->validateProductionSecurity();
    }
}
