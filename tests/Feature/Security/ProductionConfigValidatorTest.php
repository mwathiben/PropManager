<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

/**
 * Phase-11 Phase-1: production-config validator must fail-closed on
 * critical misconfig (no more warn-and-pray). These tests pin the
 * critical / warning split per finding ID.
 *
 * We exercise the protected collectCriticalProductionMisconfig and
 * collectProductionWarnings helpers directly via reflection — running
 * the full validateProductionSecurity would require swapping
 * $app->environment to 'production', which has ripple effects across
 * the test container (config:cache, queue worker mode, etc.).
 */
class ProductionConfigValidatorTest extends TestCase
{
    private function critical(): array
    {
        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'collectCriticalProductionMisconfig');
        $method->setAccessible(true);

        return $method->invoke($provider);
    }

    private function warnings(): array
    {
        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'collectProductionWarnings');
        $method->setAccessible(true);

        return $method->invoke($provider);
    }

    private function setProductionSafeBaseline(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.debug' => false,
            'app.env' => 'production',
            'session.encrypt' => true,
            'session.secure' => true,
            'mail.default' => 'smtp',
            'security.headers.hsts_enabled' => true,
            'reverb.apps.apps.0.key' => 'real-key',
            'reverb.apps.apps.0.secret' => 'real-secret',
            'sentry.dsn' => 'https://example.ingest.sentry.io/123',
            'logging.channels.single.level' => 'warning',
            'security.kenya_dpa.enabled' => true,
            'security.kenya_dpa.registration' => 'KE-DPA-12345',
            'hashing.bcrypt.rounds' => 12,
            'filesystems.default' => 's3',
        ]);
    }

    public function test_baseline_safe_config_produces_no_critical_errors(): void
    {
        $this->setProductionSafeBaseline();
        $this->assertSame([], $this->critical());
        $this->assertSame([], $this->warnings());
    }

    public function test_empty_app_key_is_critical(): void
    {
        $this->setProductionSafeBaseline();
        config(['app.key' => '']);

        $errors = $this->critical();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('APP_KEY', $errors[0]);
    }

    public function test_app_debug_with_non_local_env_is_critical(): void
    {
        $this->setProductionSafeBaseline();
        config(['app.debug' => true, 'app.env' => 'staging']);

        $errors = $this->critical();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('APP_DEBUG', $errors[0]);
    }

    public function test_app_debug_with_local_env_is_allowed(): void
    {
        $this->setProductionSafeBaseline();
        config(['app.debug' => true, 'app.env' => 'local']);

        $this->assertSame([], $this->critical());
    }

    public function test_session_encrypt_false_is_critical(): void
    {
        $this->setProductionSafeBaseline();
        config(['session.encrypt' => false]);

        $this->assertNotEmpty(array_filter($this->critical(), fn ($e) => str_contains($e, 'SESSION_ENCRYPT')));
    }

    public function test_session_secure_false_is_critical(): void
    {
        $this->setProductionSafeBaseline();
        config(['session.secure' => false]);

        $this->assertNotEmpty(array_filter($this->critical(), fn ($e) => str_contains($e, 'SESSION_SECURE_COOKIE')));
    }

    public function test_mail_mailer_log_is_critical(): void
    {
        $this->setProductionSafeBaseline();
        config(['mail.default' => 'log']);

        $this->assertNotEmpty(array_filter($this->critical(), fn ($e) => str_contains($e, 'MAIL_MAILER=log')));
    }

    public function test_mail_mailer_array_is_critical(): void
    {
        $this->setProductionSafeBaseline();
        config(['mail.default' => 'array']);

        $this->assertNotEmpty(array_filter($this->critical(), fn ($e) => str_contains($e, 'MAIL_MAILER=array')));
    }

    public function test_empty_sentry_dsn_is_warning(): void
    {
        $this->setProductionSafeBaseline();
        config(['sentry.dsn' => '']);

        $this->assertSame([], $this->critical());
        $warnings = $this->warnings();
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'SENTRY_LARAVEL_DSN')));
    }

    public function test_reverb_placeholder_is_warning(): void
    {
        $this->setProductionSafeBaseline();
        config(['reverb.apps.apps.0.secret' => 'your-secret-key-here']);

        $warnings = $this->warnings();
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'REVERB')));
    }

    public function test_hsts_disabled_is_warning(): void
    {
        $this->setProductionSafeBaseline();
        config(['security.headers.hsts_enabled' => false]);

        $warnings = $this->warnings();
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'HSTS')));
    }

    public function test_log_level_debug_is_warning(): void
    {
        $this->setProductionSafeBaseline();
        config(['logging.channels.single.level' => 'debug']);

        $warnings = $this->warnings();
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'LOG_LEVEL=debug')));
    }

    public function test_log_level_info_is_warning(): void
    {
        $this->setProductionSafeBaseline();
        config(['logging.channels.single.level' => 'info']);

        $warnings = $this->warnings();
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'LOG_LEVEL=info')));
    }

    public function test_missing_kenya_dpa_registration_is_warning_when_enabled(): void
    {
        $this->setProductionSafeBaseline();
        config(['security.kenya_dpa.registration' => '']);

        $warnings = $this->warnings();
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'KENYA_DPA_REGISTRATION')));
    }

    public function test_missing_kenya_dpa_registration_is_silent_when_disabled(): void
    {
        $this->setProductionSafeBaseline();
        config([
            'security.kenya_dpa.enabled' => false,
            'security.kenya_dpa.registration' => '',
        ]);

        $warnings = $this->warnings();
        $this->assertEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'KENYA_DPA_REGISTRATION')));
    }

    public function test_bcrypt_rounds_below_12_is_warning(): void
    {
        $this->setProductionSafeBaseline();
        config(['hashing.bcrypt.rounds' => 10]);

        $warnings = $this->warnings();
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'BCRYPT_ROUNDS=10')));
    }

    public function test_filesystem_disk_local_is_warning(): void
    {
        $this->setProductionSafeBaseline();
        config(['filesystems.default' => 'local']);

        $warnings = $this->warnings();
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, 'FILESYSTEM_DISK=local')));
    }

    public function test_multiple_critical_misconfigs_all_reported(): void
    {
        $this->setProductionSafeBaseline();
        config([
            'app.key' => '',
            'session.encrypt' => false,
            'mail.default' => 'log',
        ]);

        $errors = $this->critical();
        $this->assertCount(3, $errors);
    }
}
