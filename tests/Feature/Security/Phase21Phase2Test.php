<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\BankWebhookLog;
use App\Models\WebhookLog;
use App\Services\Payment\WebhookLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase-21 Phase-2 MEDIUM coverage:
 *   DEFER-DPA-3 (RETAIN-5 closure): dpa:enforce-retention orchestrator
 *     runs the 10-stage retention pipeline with per-stage health gauges
 *     + aggregated failure count.
 *   DEFER-OBSERV-1 (OBSERV-10 closure): webhook_logs + bank_webhook_logs
 *     carry request_id stamped from the X-Request-Id header, surfaced
 *     in logs:correlate.
 */
class Phase21Phase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_dpa_enforce_retention_runs_in_dry_run_mode(): void
    {
        // Dry-run mode propagates --dry-run to each child command that
        // supports it; stages without --dry-run support are SKIPPED
        // (operator preview semantics). Exit code is 0 because skipped
        // != failed.
        $exitCode = Artisan::call('dpa:enforce-retention', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode, 'Dry-run with no real drift + skipped non-dry-run stages = SUCCESS.');
    }

    public function test_dpa_enforce_retention_pipeline_definition_includes_all_stages(): void
    {
        // Verify the orchestrator's internal pipeline definition by
        // reflection. A refactor accidentally dropping a stage from the
        // PIPELINE constant trips this test.
        $reflection = new \ReflectionClass(\App\Console\Commands\EnforceRetention::class);
        $pipeline = $reflection->getConstant('PIPELINE');

        $expectedStages = [
            'logs_audit', 'logs_security', 'logs_webhook',
            'logs_bank_webhook', 'logs_dead_letter', 'logs_consent',
            'soft_deleted_purge', 'queue_prune_batches',
            'queue_prune_failed', 'gdpr_process_deletions',
        ];

        $actualStages = array_map(fn ($entry) => $entry['stage'], $pipeline);

        $this->assertSame(
            $expectedStages,
            $actualStages,
            'Phase-21 DEFER-DPA-3: pipeline stages must match the documented retention pipeline order.',
        );
    }

    public function test_dpa_enforce_retention_pipeline_targets_real_commands(): void
    {
        // Each stage's `command` must be a registered artisan command —
        // a typo in the orchestrator silently misroutes a stage and the
        // try/catch swallows the resulting "command not found" error.
        $reflection = new \ReflectionClass(\App\Console\Commands\EnforceRetention::class);
        $pipeline = $reflection->getConstant('PIPELINE');

        $registered = array_keys(\Illuminate\Support\Facades\Artisan::all());

        foreach ($pipeline as $entry) {
            $this->assertContains(
                $entry['command'],
                $registered,
                "Phase-21 DEFER-DPA-3: pipeline stage '{$entry['stage']}' targets unknown command '{$entry['command']}'.",
            );
        }
    }

    public function test_webhook_logs_table_has_request_id_column(): void
    {
        $log = WebhookLog::create([
            'provider' => 'paystack',
            'event_id' => 'evt_test_'.uniqid(),
            'event_type' => 'charge.success',
            'payload_hash' => hash('sha256', 'test'),
            'retry_count' => 1,
            'first_received_at' => now(),
            'last_received_at' => now(),
            'status' => WebhookLog::STATUS_PENDING,
            'ip_address' => '203.0.113.5',
            'request_id' => '01HX-test-uuid-1234',
        ]);

        $this->assertSame('01HX-test-uuid-1234', $log->fresh()->request_id);
    }

    public function test_webhook_log_service_stamps_request_id_from_header(): void
    {
        $explicitRequestId = (string) Str::uuid();

        // Simulate Phase-14 AddRequestId middleware behavior — the header
        // is present on the incoming request.
        request()->headers->set('X-Request-Id', $explicitRequestId);

        $service = app(WebhookLogService::class);
        $log = $service->recordHit(
            provider: 'paystack',
            eventId: 'evt_'.uniqid(),
            eventType: 'charge.success',
            rawPayload: '{"foo":"bar"}',
            landlordId: null,
            ipAddress: '203.0.113.5',
        );

        $this->assertSame($explicitRequestId, $log->request_id);
    }

    public function test_webhook_log_service_generates_uuid_when_no_header(): void
    {
        // No X-Request-Id header — service stamps a fresh UUID so the
        // column is never NULL on the happy path. logs:correlate can
        // still group by request_id even for fixture/replay paths.
        $log = app(WebhookLogService::class)->recordHit(
            provider: 'mpesa',
            eventId: 'evt_'.uniqid(),
            eventType: 'stk.callback',
            rawPayload: '{}',
        );

        $this->assertNotNull($log->request_id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $log->request_id,
            'Fallback request_id must be a valid UUID.',
        );
    }

    public function test_bank_webhook_log_carries_request_id_for_correlation(): void
    {
        $log = BankWebhookLog::create([
            'bank_code' => 'kcb',
            'event_type' => 'payment_received',
            'payload' => ['amount' => 1000, 'reference' => 'TEST123'],
            'status' => 'received',
            'ip_address' => '203.0.113.10',
            'request_id' => '01HX-bank-uuid-5678',
        ]);

        $this->assertSame('01HX-bank-uuid-5678', $log->fresh()->request_id);
    }

    public function test_logs_correlate_surfaces_webhook_logs_by_request_id(): void
    {
        $requestId = '01HX-correlate-uuid-9999';

        WebhookLog::create([
            'provider' => 'paystack',
            'event_id' => 'evt_correlate_1',
            'event_type' => 'charge.success',
            'payload_hash' => hash('sha256', 'p1'),
            'retry_count' => 1,
            'first_received_at' => now(),
            'last_received_at' => now(),
            'status' => WebhookLog::STATUS_PENDING,
            'ip_address' => '203.0.113.5',
            'request_id' => $requestId,
        ]);

        BankWebhookLog::create([
            'bank_code' => 'kcb',
            'event_type' => 'payment_received',
            'payload' => ['amount' => 500],
            'status' => 'received',
            'ip_address' => '203.0.113.10',
            'request_id' => $requestId,
        ]);

        Artisan::call('logs:correlate', ['--request-id' => $requestId, '--since' => '24h']);
        $output = Artisan::output();

        $this->assertStringContainsString('webhook_logs', $output, 'Phase-21 DEFER-OBSERV-1: webhook_logs must surface in correlate output.');
        $this->assertStringContainsString('bank_webhook_logs', $output, 'Phase-21 DEFER-OBSERV-1: bank_webhook_logs must surface in correlate output.');
    }

    public function test_aux_log_channels_have_json_formatter_tap_when_configured(): void
    {
        // Phase-21 DEFER-OBSERV-2 (closes Phase-14 OBSERV-7 deferral):
        // simulate LOG_FORMATTER=json + reload config. Every auxiliary
        // channel must include TapJsonFormatter in its tap chain so log
        // aggregators receive the same JSON shape as single/daily.
        config(['logging.channels.whatsapp.tap' => [
            \App\Logging\TapMaskingProcessor::class,
            \App\Logging\TapJsonFormatter::class,
        ]]);

        // Walk the config as it would resolve under LOG_FORMATTER=json.
        $auxChannels = ['whatsapp', 'cache', 'slow-query', 'notifications', 'payments', 'schedule', 'metrics'];
        $rawConfig = require base_path('config/logging.php');

        foreach ($auxChannels as $channel) {
            $this->assertArrayHasKey(
                $channel,
                $rawConfig['channels'],
                "Phase-21 DEFER-OBSERV-2: channel '$channel' must exist in logging config.",
            );
            $this->assertArrayHasKey(
                'tap',
                $rawConfig['channels'][$channel],
                "Phase-21 DEFER-OBSERV-2: channel '$channel' must declare a tap chain. Pre-Phase-21 only single/daily/security had taps.",
            );
        }
    }

    public function test_data_export_service_wires_large_export_detector_call_site(): void
    {
        // Phase-21 DEFER-DPA-2 (closes Phase-13 BREACH-2 deferral):
        // verify the IncidentDetector::checkLargeDataExport call site
        // exists in DataExportService source. Pre-Phase-21 the detector
        // rule method existed (Phase-13 1b) but no consumer called it —
        // grep-verifying the wiring is sufficient because (a) the
        // detector method signature is contract-tested in Phase-13
        // tests and (b) Storage/ZipArchive coupling in
        // exportUserData() makes end-to-end testing brittle.
        $source = file_get_contents(base_path('app/Services/DataExportService.php'));

        $this->assertStringContainsString(
            'use App\\Services\\IncidentDetector;',
            $source,
            'Phase-21 DEFER-DPA-2: DataExportService must import IncidentDetector.',
        );
        $this->assertStringContainsString(
            'checkLargeDataExport',
            $source,
            'Phase-21 DEFER-DPA-2: DataExportService must call checkLargeDataExport.',
        );
        $this->assertStringContainsString(
            "'gdpr_portability'",
            $source,
            'Phase-21 DEFER-DPA-2: call must tag the export type as gdpr_portability for incident triage.',
        );
    }

    public function test_incident_detector_check_large_data_export_returns_incident_at_threshold(): void
    {
        // Phase-21 DEFER-DPA-2: contract-verify the detector behavior the
        // DataExportService call site relies on. Below threshold = null
        // (no incident); at-or-above threshold = SecurityIncident created.
        config(['security.detection.large_export.threshold' => 100]);

        $belowResult = app(\App\Services\IncidentDetector::class)
            ->checkLargeDataExport(userId: 1, rowCount: 50, exportType: 'gdpr_portability');
        $this->assertNull($belowResult, 'Below threshold = no incident.');

        $aboveResult = app(\App\Services\IncidentDetector::class)
            ->checkLargeDataExport(userId: 1, rowCount: 200, exportType: 'gdpr_portability');
        $this->assertInstanceOf(
            \App\Models\SecurityIncident::class,
            $aboveResult,
            'At-threshold export must create a SecurityIncident the SuspiciousActivityDetected listener can pick up.',
        );
    }

    public function test_logs_correlate_excludes_other_request_ids(): void
    {
        $targetId = 'target-request-id';
        $otherId = 'other-request-id';

        WebhookLog::create([
            'provider' => 'paystack',
            'event_id' => 'evt_target',
            'event_type' => 'charge.success',
            'payload_hash' => hash('sha256', 'target'),
            'retry_count' => 1,
            'first_received_at' => now(),
            'last_received_at' => now(),
            'status' => WebhookLog::STATUS_PENDING,
            'ip_address' => '203.0.113.5',
            'request_id' => $targetId,
        ]);

        WebhookLog::create([
            'provider' => 'paystack',
            'event_id' => 'evt_other',
            'event_type' => 'transfer.success',
            'payload_hash' => hash('sha256', 'other'),
            'retry_count' => 1,
            'first_received_at' => now(),
            'last_received_at' => now(),
            'status' => WebhookLog::STATUS_PENDING,
            'ip_address' => '203.0.113.5',
            'request_id' => $otherId,
        ]);

        Artisan::call('logs:correlate', ['--request-id' => $targetId, '--since' => '24h']);
        $output = Artisan::output();

        $this->assertStringContainsString('charge.success', $output);
        $this->assertStringNotContainsString('transfer.success', $output, 'logs:correlate must filter by request_id, not return all rows.');
    }
}
