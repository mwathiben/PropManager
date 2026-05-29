<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Services\DataExportService;
use App\Services\Storage\FileRetentionService;
use App\Support\LegalHoldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-65 RETENTION-INTEGRATION watchdog: FileRetentionService
 * honors Document holds + DataExportService surfaces blocking holds
 * + AuditLegalHoldExclusions cron emits gauges.
 */
class Phase65RetentionIntegrationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->seed(\Database\Seeders\Phase59FileRetentionPolicySeeder::class);

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );

        Storage::fake('tenant');
    }

    public function test_file_retention_service_honors_document_holds(): void
    {
        $heldDoc = Document::factory()->forUser($this->tenant)->create([
            'landlord_id' => $this->landlord->id,
            'document_type' => 'tenant_id',
        ]);
        $unheldOld = Document::factory()->forUser($this->tenant)->create([
            'landlord_id' => $this->landlord->id,
            'document_type' => 'tenant_id',
        ]);
        $unheldNew = Document::factory()->forUser($this->tenant)->create([
            'landlord_id' => $this->landlord->id,
            'document_type' => 'tenant_id',
        ]);

        $heldDoc->forceFill(['created_at' => Carbon::now()->subDays(2600)])->save();
        $unheldOld->forceFill(['created_at' => Carbon::now()->subDays(2600)])->save();

        LegalHoldRegistry::hold($heldDoc, $this->landlord, 'preservation order');

        $service = app(FileRetentionService::class);
        $result = $service->enforce('kyc_doc', dryRun: false);

        $this->assertNotNull($heldDoc->fresh(), 'held doc must NOT be deleted');
        $this->assertSoftDeleted($unheldOld);
        $this->assertNotNull($unheldNew->fresh(), 'new doc must NOT be deleted');
        $this->assertGreaterThanOrEqual(1, $result['deleted']);
    }

    public function test_data_export_includes_legal_holds_blocking_erasure_stanza(): void
    {
        $lease = $this->tenant->leases()->first();
        $invoice = Invoice::factory()->create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $lease->id,
        ]);

        LegalHoldRegistry::hold($invoice, $this->landlord, 'litigation pending');

        $service = app(DataExportService::class);
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('getLegalHoldsBlockingErasure');
        $method->setAccessible(true);
        $holds = $method->invoke($service, $this->tenant);

        $this->assertNotEmpty($holds, 'Held invoice tied to tenant lease must appear in stanza');
        $found = collect($holds)->firstWhere('subject_type', 'Invoice');
        $this->assertNotNull($found);
        $this->assertSame('legal_obligation', $found['lawful_basis']);
        $this->assertStringContainsString('17(3)(b)', $found['erasure_carve_out']);
    }

    public function test_audit_legal_hold_exclusions_command_emits_gauges(): void
    {
        $lease = $this->tenant->leases()->first();
        $invoice = Invoice::factory()->create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $lease->id,
        ]);

        LegalHoldRegistry::hold($invoice, $this->landlord, 'litigation reason');

        Cache::flush();

        $emitted = [];
        $metricsMock = \Mockery::mock(\App\Services\MetricsService::class);
        $metricsMock->shouldReceive('gauge')
            ->andReturnUsing(function (string $name, int $value, array $labels) use (&$emitted) {
                $emitted[] = compact('name', 'value', 'labels');
            });
        $this->app->instance(\App\Services\MetricsService::class, $metricsMock);

        $this->artisan('legal-hold:audit-exclusions')->assertSuccessful();

        $invoiceGauge = collect($emitted)->first(fn ($e) => $e['name'] === 'retention_legal_hold_exclusions_count'
            && ($e['labels']['subject_type'] ?? null) === 'Invoice'
        );

        $this->assertNotNull($invoiceGauge, 'Invoice gauge must be emitted');
        $this->assertSame(1, $invoiceGauge['value']);
    }

    public function test_audit_exclusions_cron_registered_in_schedule(): void
    {
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $commands = collect($events)->map(fn ($e) => $e->command);

        $matched = $commands->first(fn ($c) => is_string($c) && str_contains($c, 'legal-hold:audit-exclusions'));

        $this->assertNotNull($matched, 'legal-hold:audit-exclusions must be scheduled');
    }
}
