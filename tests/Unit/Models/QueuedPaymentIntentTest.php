<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Invoice;
use App\Models\QueuedPaymentIntent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QueuedPaymentIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('queued_payment_intents'));
    }

    public function test_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'idempotency_key', 'tenant_id', 'invoice_id', 'landlord_id',
            'amount', 'currency', 'payment_method', 'phone_number',
            'status', 'attempts', 'last_attempt_at', 'next_retry_at',
            'expires_at', 'failure_reason', 'metadata',
            'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('queued_payment_intents', $column),
                "Missing column: {$column}"
            );
        }
    }

    public function test_pending_scope_returns_only_pending(): void
    {
        QueuedPaymentIntent::factory()->pending()->create();
        QueuedPaymentIntent::factory()->processing()->create();
        QueuedPaymentIntent::factory()->completed()->create();

        $results = QueuedPaymentIntent::withoutGlobalScope('landlord')->pending()->get();

        $this->assertCount(1, $results);
        $this->assertEquals(QueuedPaymentIntent::STATUS_PENDING, $results->first()->status);
    }

    public function test_expired_scope_returns_past_expiry(): void
    {
        QueuedPaymentIntent::factory()->pending()->create(['expires_at' => now()->subHour()]);
        QueuedPaymentIntent::factory()->pending()->create(['expires_at' => now()->addHour()]);

        $results = QueuedPaymentIntent::withoutGlobalScope('landlord')->expired()->get();

        $this->assertCount(1, $results);
    }

    public function test_by_tenant_scope_filters_by_tenant_id(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $other = User::factory()->create(['role' => 'tenant']);

        QueuedPaymentIntent::factory()->create(['tenant_id' => $tenant->id]);
        QueuedPaymentIntent::factory()->create(['tenant_id' => $other->id]);

        $results = QueuedPaymentIntent::withoutGlobalScope('landlord')
            ->byTenant($tenant->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($tenant->id, $results->first()->tenant_id);
    }

    public function test_retryable_scope_finds_pending_intents_ready_for_retry(): void
    {
        QueuedPaymentIntent::factory()->pending()->create(['next_retry_at' => now()->subMinute()]);
        QueuedPaymentIntent::factory()->pending()->create(['next_retry_at' => now()->addHour()]);
        QueuedPaymentIntent::factory()->pending()->create(['next_retry_at' => null]);
        QueuedPaymentIntent::factory()->completed()->create(['next_retry_at' => null]);

        $results = QueuedPaymentIntent::withoutGlobalScope('landlord')->retryable()->get();

        $this->assertCount(2, $results);
    }

    public function test_is_pending(): void
    {
        $intent = QueuedPaymentIntent::factory()->pending()->create();

        $this->assertTrue($intent->isPending());
        $this->assertFalse($intent->isProcessing());
    }

    public function test_is_processing(): void
    {
        $intent = QueuedPaymentIntent::factory()->processing()->create();

        $this->assertTrue($intent->isProcessing());
        $this->assertFalse($intent->isPending());
    }

    public function test_is_completed(): void
    {
        $intent = QueuedPaymentIntent::factory()->completed()->create();

        $this->assertTrue($intent->isCompleted());
    }

    public function test_is_failed(): void
    {
        $intent = QueuedPaymentIntent::factory()->failed()->create();

        $this->assertTrue($intent->isFailed());
    }

    public function test_is_expired_when_past_expiry(): void
    {
        $intent = QueuedPaymentIntent::factory()->create([
            'expires_at' => now()->subHour(),
        ]);

        $this->assertTrue($intent->isExpired());
    }

    public function test_is_not_expired_when_before_expiry(): void
    {
        $intent = QueuedPaymentIntent::factory()->create([
            'expires_at' => now()->addHour(),
        ]);

        $this->assertFalse($intent->isExpired());
    }

    public function test_is_terminal_for_final_states(): void
    {
        $completed = QueuedPaymentIntent::factory()->completed()->create();
        $failed = QueuedPaymentIntent::factory()->failed()->create();
        $expired = QueuedPaymentIntent::factory()->expired()->create();
        $pending = QueuedPaymentIntent::factory()->pending()->create();

        $this->assertTrue($completed->isTerminal());
        $this->assertTrue($failed->isTerminal());
        $this->assertTrue($expired->isTerminal());
        $this->assertFalse($pending->isTerminal());
    }

    public function test_mark_processing_increments_attempts_and_sets_timestamps(): void
    {
        $intent = QueuedPaymentIntent::factory()->pending()->create();

        $intent->markProcessing();
        $intent->refresh();

        $this->assertEquals(QueuedPaymentIntent::STATUS_PROCESSING, $intent->status);
        $this->assertEquals(1, $intent->attempts);
        $this->assertNotNull($intent->last_attempt_at);
        $this->assertNotNull($intent->next_retry_at);
    }

    public function test_mark_completed_updates_status_and_clears_retry(): void
    {
        $intent = QueuedPaymentIntent::factory()->processing()->create([
            'next_retry_at' => now()->addMinute(),
        ]);

        $intent->markCompleted();
        $intent->refresh();

        $this->assertEquals(QueuedPaymentIntent::STATUS_COMPLETED, $intent->status);
        $this->assertNull($intent->next_retry_at);
    }

    public function test_mark_failed_sets_status_and_reason(): void
    {
        $intent = QueuedPaymentIntent::factory()->processing()->create();

        $intent->markFailed('Insufficient balance');
        $intent->refresh();

        $this->assertEquals(QueuedPaymentIntent::STATUS_FAILED, $intent->status);
        $this->assertEquals('Insufficient balance', $intent->failure_reason);
    }

    public function test_mark_expired_updates_status(): void
    {
        $intent = QueuedPaymentIntent::factory()->pending()->create([
            'expires_at' => now()->subHour(),
        ]);

        $intent->markExpired();
        $intent->refresh();

        $this->assertEquals(QueuedPaymentIntent::STATUS_EXPIRED, $intent->status);
    }

    public function test_mark_completed_on_terminal_state_returns_false(): void
    {
        $intent = QueuedPaymentIntent::factory()->expired()->create();

        $result = $intent->markCompleted();

        $this->assertFalse($result);
        $this->assertEquals(QueuedPaymentIntent::STATUS_EXPIRED, $intent->fresh()->status);
    }

    public function test_tenant_relationship(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $intent = QueuedPaymentIntent::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($intent->tenant->is($tenant));
    }

    public function test_landlord_relationship(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $intent = QueuedPaymentIntent::factory()->create(['landlord_id' => $landlord->id]);

        $this->assertTrue($intent->landlord->is($landlord));
    }

    public function test_invoice_relationship(): void
    {
        $invoice = Invoice::factory()->sent()->create();
        $intent = QueuedPaymentIntent::factory()->create([
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
        ]);

        $this->assertTrue($intent->invoice->is($invoice));
    }

    public function test_invoice_can_be_null(): void
    {
        $intent = QueuedPaymentIntent::factory()->create(['invoice_id' => null]);

        $this->assertNull($intent->invoice);
    }

    public function test_factory_creates_valid_record(): void
    {
        $intent = QueuedPaymentIntent::factory()->create();

        $this->assertDatabaseHas('queued_payment_intents', ['id' => $intent->id]);
        $this->assertNotNull($intent->idempotency_key);
    }

    public function test_for_invoice_factory_sets_ids(): void
    {
        $invoice = Invoice::factory()->sent()->create();
        $intent = QueuedPaymentIntent::factory()->forInvoice($invoice)->create();

        $this->assertEquals($invoice->id, $intent->invoice_id);
        $this->assertEquals($invoice->landlord_id, $intent->landlord_id);
    }

    public function test_idempotency_key_is_unique(): void
    {
        $intent = QueuedPaymentIntent::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        QueuedPaymentIntent::factory()->create([
            'idempotency_key' => $intent->idempotency_key,
        ]);
    }

    public function test_generate_idempotency_key_is_deterministic(): void
    {
        $key1 = QueuedPaymentIntent::generateIdempotencyKey(1, 10, 'abc-uuid');
        $key2 = QueuedPaymentIntent::generateIdempotencyKey(1, 10, 'abc-uuid');
        $key3 = QueuedPaymentIntent::generateIdempotencyKey(1, 10, 'different-uuid');

        $this->assertEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
    }
}
