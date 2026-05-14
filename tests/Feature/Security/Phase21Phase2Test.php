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
