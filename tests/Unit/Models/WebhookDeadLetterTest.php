<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\WebhookDeadLetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookDeadLetterTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    public function test_can_create_dead_letter_with_factory(): void
    {
        $deadLetter = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
        ]);

        $this->assertDatabaseHas('webhook_dead_letters', [
            'id' => $deadLetter->id,
            'landlord_id' => $this->landlord->id,
            'provider' => WebhookDeadLetter::PROVIDER_MPESA,
        ]);
    }

    public function test_payload_is_cast_to_array(): void
    {
        $payload = ['transaction_id' => 'TXN123', 'amount' => 5000];

        $deadLetter = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'payload' => $payload,
        ]);

        $deadLetter->refresh();

        $this->assertIsArray($deadLetter->payload);
        $this->assertEquals('TXN123', $deadLetter->payload['transaction_id']);
    }

    public function test_headers_is_cast_to_array(): void
    {
        $headers = ['x-paystack-signature' => 'abc123', 'content-type' => 'application/json'];

        $deadLetter = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'headers' => $headers,
        ]);

        $deadLetter->refresh();

        $this->assertIsArray($deadLetter->headers);
        $this->assertEquals('abc123', $deadLetter->headers['x-paystack-signature']);
    }

    public function test_unresolved_scope_returns_only_unresolved(): void
    {
        WebhookDeadLetter::factory()->count(2)->create([
            'landlord_id' => $this->landlord->id,
            'resolved_at' => null,
        ]);

        $resolver = User::factory()->create(['role' => 'landlord']);
        WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'resolved_at' => now(),
            'resolved_by' => $resolver->id,
            'resolution_notes' => 'Manually resolved',
        ]);

        $this->actingAs($this->landlord);

        $unresolved = WebhookDeadLetter::unresolved()->get();

        $this->assertCount(2, $unresolved);
    }

    public function test_resolved_scope_returns_only_resolved(): void
    {
        WebhookDeadLetter::factory()->count(2)->create([
            'landlord_id' => $this->landlord->id,
            'resolved_at' => null,
        ]);

        $resolver = User::factory()->create(['role' => 'landlord']);
        WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'resolved_at' => now(),
            'resolved_by' => $resolver->id,
            'resolution_notes' => 'Manually resolved',
        ]);

        $this->actingAs($this->landlord);

        $resolved = WebhookDeadLetter::resolved()->get();

        $this->assertCount(1, $resolved);
    }

    public function test_by_provider_scope_filters_correctly(): void
    {
        WebhookDeadLetter::factory()->mpesa()->create(['landlord_id' => $this->landlord->id]);
        WebhookDeadLetter::factory()->paystack()->create(['landlord_id' => $this->landlord->id]);
        WebhookDeadLetter::factory()->intasend()->create(['landlord_id' => $this->landlord->id]);

        $this->actingAs($this->landlord);

        $mpesaOnly = WebhookDeadLetter::byProvider(WebhookDeadLetter::PROVIDER_MPESA)->get();

        $this->assertCount(1, $mpesaOnly);
        $this->assertEquals(WebhookDeadLetter::PROVIDER_MPESA, $mpesaOnly->first()->provider);
    }

    public function test_recent_first_scope_orders_descending(): void
    {
        $oldest = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'created_at' => now()->subHours(3),
        ]);

        $newest = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'created_at' => now(),
        ]);

        $middle = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'created_at' => now()->subHour(),
        ]);

        $this->actingAs($this->landlord);

        $ordered = WebhookDeadLetter::recentFirst()->get();

        $this->assertEquals($newest->id, $ordered->first()->id);
        $this->assertEquals($oldest->id, $ordered->last()->id);
    }

    public function test_is_resolved_returns_correct_boolean(): void
    {
        $unresolved = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'resolved_at' => null,
        ]);

        $resolver = User::factory()->create(['role' => 'landlord']);
        $resolved = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'resolved_at' => now(),
            'resolved_by' => $resolver->id,
        ]);

        $this->assertFalse($unresolved->isResolved());
        $this->assertTrue($resolved->isResolved());
    }

    public function test_is_unresolved_returns_correct_boolean(): void
    {
        $unresolved = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'resolved_at' => null,
        ]);

        $resolver = User::factory()->create(['role' => 'landlord']);
        $resolved = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'resolved_at' => now(),
            'resolved_by' => $resolver->id,
        ]);

        $this->assertTrue($unresolved->isUnresolved());
        $this->assertFalse($resolved->isUnresolved());
    }

    public function test_mark_resolved_sets_all_fields(): void
    {
        $deadLetter = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
        ]);

        $resolver = User::factory()->create(['role' => 'landlord']);
        $result = $deadLetter->markResolved($resolver, 'Payment found in bank statement');

        $deadLetter->refresh();

        $this->assertInstanceOf(WebhookDeadLetter::class, $result);
        $this->assertNotNull($deadLetter->resolved_at);
        $this->assertEquals($resolver->id, $deadLetter->resolved_by);
        $this->assertEquals('Payment found in bank statement', $deadLetter->resolution_notes);
    }

    public function test_increment_attempts_increases_count(): void
    {
        $deadLetter = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'attempts' => 1,
            'error_class' => WebhookDeadLetter::ERROR_TRANSIENT,
        ]);

        $deadLetter->incrementAttempts();
        $deadLetter->refresh();

        $this->assertEquals(2, $deadLetter->attempts);
        $this->assertNotNull($deadLetter->next_retry_at);
    }

    public function test_landlord_relationship(): void
    {
        $deadLetter = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
        ]);

        $this->assertInstanceOf(User::class, $deadLetter->landlord);
        $this->assertEquals($this->landlord->id, $deadLetter->landlord->id);
    }

    public function test_resolver_relationship(): void
    {
        $resolver = User::factory()->create(['role' => 'landlord']);

        $deadLetter = WebhookDeadLetter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'resolved_at' => now(),
            'resolved_by' => $resolver->id,
            'resolution_notes' => 'Fixed',
        ]);

        $this->assertInstanceOf(User::class, $deadLetter->resolver);
        $this->assertEquals($resolver->id, $deadLetter->resolver->id);
    }

    public function test_tenant_scope_isolates_by_landlord(): void
    {
        $landlord2 = User::factory()->create(['role' => 'landlord']);

        // Authenticate BEFORE model operations so TenantScope boot registers the global scope
        $this->actingAs($this->landlord);

        // Force model re-boot so TenantScope registers with the authenticated user
        WebhookDeadLetter::clearBootedModels();

        // Create records directly via DB to bypass TenantScope auto-fill interference
        WebhookDeadLetter::withoutGlobalScope('landlord')->insert(
            collect(range(1, 3))->map(fn () => [
                'landlord_id' => $this->landlord->id,
                'provider' => WebhookDeadLetter::PROVIDER_MPESA,
                'event_type' => 'stk_callback',
                'payload' => json_encode(['test' => true]),
                'error_reason' => 'Test error',
                'error_class' => WebhookDeadLetter::ERROR_TRANSIENT,
                'attempts' => 1,
                'max_retries' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray()
        );

        WebhookDeadLetter::withoutGlobalScope('landlord')->insert(
            collect(range(1, 2))->map(fn () => [
                'landlord_id' => $landlord2->id,
                'provider' => WebhookDeadLetter::PROVIDER_PAYSTACK,
                'event_type' => 'charge.success',
                'payload' => json_encode(['test' => true]),
                'error_reason' => 'Test error',
                'error_class' => WebhookDeadLetter::ERROR_TRANSIENT,
                'attempts' => 1,
                'max_retries' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray()
        );

        $landlord1Results = WebhookDeadLetter::all();
        $this->assertCount(3, $landlord1Results);

        $this->actingAs($landlord2);
        $landlord2Results = WebhookDeadLetter::all();
        $this->assertCount(2, $landlord2Results);
    }
}
