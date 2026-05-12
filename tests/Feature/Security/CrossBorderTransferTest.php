<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Providers\AppServiceProvider;
use App\Services\KenyaDpaService;
use Tests\TestCase;

/**
 * Phase-13 DPA-2 regression coverage. KenyaDpaService::
 * canTransferCrossBorder existed with zero callers; AppServiceProvider
 * now invokes it at boot for the three known cross-border seams.
 *
 * Tests use reflection to invoke the protected helper directly so we
 * don't need to flip $app->environment to production (which has
 * ripple effects across the test container).
 */
class CrossBorderTransferTest extends TestCase
{
    private function warnings(): array
    {
        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'collectCrossBorderTransferWarnings');
        $method->setAccessible(true);

        // The method early-returns when not in production; flip the
        // env for the duration of the call only.
        $this->app['env'] = 'production';
        try {
            return $method->invoke($provider);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_helper_classifies_aws_us_region(): void
    {
        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'awsRegionToCountryCode');
        $method->setAccessible(true);

        $this->assertSame('US', $method->invoke($provider, 'us-east-1'));
        $this->assertSame('US', $method->invoke($provider, 'us-west-2'));
        $this->assertSame('EU', $method->invoke($provider, 'eu-west-1'));
        $this->assertSame('CA', $method->invoke($provider, 'ca-central-1'));
        $this->assertSame('JP', $method->invoke($provider, 'ap-northeast-1'));
        $this->assertNull($method->invoke($provider, ''));
        $this->assertNull($method->invoke($provider, 'unknown-region'));
    }

    public function test_helper_classifies_sentry_host(): void
    {
        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'sentryHostToCountryCode');
        $method->setAccessible(true);

        $this->assertSame('US', $method->invoke($provider, 'o123.ingest.sentry.io'));
        $this->assertSame('US', $method->invoke($provider, 'sentry.io'));
        $this->assertSame('EU', $method->invoke($provider, 'o123.ingest.de.sentry.io'));
        $this->assertSame('EU', $method->invoke($provider, 'de.sentry.io'));
        $this->assertNull($method->invoke($provider, 'sentry.self-hosted.example.com'));
        $this->assertNull($method->invoke($provider, ''));
    }

    public function test_backup_disk_in_us_region_is_warned(): void
    {
        config([
            'backup.backup.destination.disks' => ['s3'],
            'filesystems.disks.s3.region' => 'us-east-1',
            'filesystems.default' => 'local',
            'sentry.dsn' => '',
        ]);

        $warnings = $this->warnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('us-east-1', $warnings[0]);
        $this->assertStringContainsString('US', $warnings[0]);
    }

    public function test_backup_disk_in_eu_region_is_silent(): void
    {
        config([
            'backup.backup.destination.disks' => ['s3'],
            'filesystems.disks.s3.region' => 'eu-west-1',
            'filesystems.default' => 'local',
            'sentry.dsn' => '',
        ]);

        $this->assertSame([], $this->warnings());
    }

    public function test_us_sentry_dsn_is_warned(): void
    {
        config([
            'backup.backup.destination.disks' => [],
            'filesystems.default' => 'local',
            'sentry.dsn' => 'https://abc@o123.ingest.sentry.io/456',
        ]);

        $warnings = $this->warnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Sentry', $warnings[0]);
        $this->assertStringContainsString('US', $warnings[0]);
    }

    public function test_eu_sentry_dsn_is_silent(): void
    {
        config([
            'backup.backup.destination.disks' => [],
            'filesystems.default' => 'local',
            'sentry.dsn' => 'https://abc@o123.ingest.de.sentry.io/456',
        ]);

        $this->assertSame([], $this->warnings());
    }

    public function test_default_uploads_disk_in_non_adequate_region_is_warned(): void
    {
        config([
            'backup.backup.destination.disks' => [],
            'filesystems.default' => 's3',
            'filesystems.disks.s3.region' => 'ap-south-1',
            'sentry.dsn' => '',
        ]);

        $warnings = $this->warnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('uploads', $warnings[0]);
        $this->assertStringContainsString('IN', $warnings[0]);
    }

    public function test_helper_uses_kenya_dpa_service_adequate_list(): void
    {
        // Regression: if KenyaDpaService::canTransferCrossBorder's
        // adequate list ever changes, this test will catch the drift
        // — KE / EU / GB / CA must be 'allowed' = true.
        $dpa = app(KenyaDpaService::class);
        $this->assertTrue($dpa->canTransferCrossBorder('KE')['allowed']);
        $this->assertTrue($dpa->canTransferCrossBorder('EU')['allowed']);
        $this->assertTrue($dpa->canTransferCrossBorder('GB')['allowed']);
        $this->assertTrue($dpa->canTransferCrossBorder('CA')['allowed']);
        $this->assertFalse($dpa->canTransferCrossBorder('US')['allowed']);
        $this->assertFalse($dpa->canTransferCrossBorder('IN')['allowed']);
    }
}
