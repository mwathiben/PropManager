<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Phase-13 DPA-4 regression coverage. GDPR Article 18 / Kenya DPA
 * Section 26(d) right to restriction of processing.
 *
 *   - active → restricted: stamps restricted_at + reason, audit row
 *     captures the reason, Gate denies subsequent write abilities
 *   - restricted → released: clears the flags, audit row preserves
 *     the previous reason, Gate restores writes
 *   - read-side abilities remain allowed while restricted (view,
 *     export-data, request-deletion, viewLedger)
 */
class ProcessingRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_starts_unrestricted(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->isRestricted());
        $this->assertNull($user->restricted_at);
    }

    public function test_request_restriction_stamps_the_flag_and_writes_audit(): void
    {
        // A real tenant always has a landlord (TenantScope fails closed for
        // a landlord-less non-landlord), so give them one — otherwise the
        // AuditLog query below is scoped to nothing.
        $landlord = User::factory()->create(['role' => 'landlord']);
        $user = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $response = $this
            ->actingAs($user)
            ->post('/privacy/restrict', ['reason' => 'Pending review of incorrect mobile number']);

        $response->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->isRestricted());
        $this->assertSame('Pending review of incorrect mobile number', $user->restriction_reason);

        $audit = AuditLog::where('event_type', 'processing_restricted')->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->auditable_id);
        $this->assertSame('gdpr_article_18', $audit->metadata['compliance']);
    }

    public function test_request_restriction_requires_reason(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/privacy/restrict', []);

        $response->assertSessionHasErrors('reason');
        $user->refresh();
        $this->assertFalse($user->isRestricted());
    }

    public function test_release_restriction_clears_flags_and_writes_audit(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $user = User::factory()->create([
            'restricted_at' => now()->subHour(),
            'restriction_reason' => 'Previous reason',
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/privacy/restrict/release');

        $response->assertRedirect();
        $user->refresh();
        $this->assertFalse($user->isRestricted());
        $this->assertNull($user->restricted_at);
        $this->assertNull($user->restriction_reason);

        $audit = AuditLog::where('event_type', 'processing_restriction_released')->first();
        $this->assertNotNull($audit);
        $this->assertSame('Previous reason', $audit->metadata['previous_reason']);
    }

    public function test_gate_denies_write_abilities_while_restricted(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $lease = Lease::factory()->create(['landlord_id' => $landlord->id]);

        $this->actingAs($landlord);
        $this->assertTrue(Gate::allows('update', $lease));

        $landlord->forceFill([
            'restricted_at' => now(),
            'restriction_reason' => 'Article 18',
        ])->save();
        $landlord->refresh();
        $this->actingAs($landlord);

        $this->assertFalse(
            Gate::allows('update', $lease),
            'Article 18 restriction must deny write abilities',
        );
    }

    public function test_gate_allows_read_abilities_while_restricted(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $lease = Lease::factory()->create(['landlord_id' => $landlord->id]);

        $landlord->forceFill([
            'restricted_at' => now(),
            'restriction_reason' => 'Article 18',
        ])->save();
        $this->actingAs($landlord->refresh());

        $this->assertTrue(
            Gate::allows('view', $lease),
            'Read abilities must remain available while restricted',
        );
        $this->assertTrue(
            Gate::allows('export-data', $landlord),
            'Article 20 export must remain available while restricted',
        );
    }

    public function test_double_restriction_request_is_idempotent(): void
    {
        $user = User::factory()->create([
            'restricted_at' => now()->subHour(),
            'restriction_reason' => 'First',
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/privacy/restrict', ['reason' => 'Second']);

        $response->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->isRestricted());
        // Reason isn't overwritten — first restriction stays canonical.
        $this->assertSame('First', $user->restriction_reason);
    }

    public function test_release_when_not_restricted_is_a_no_op(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/privacy/restrict/release');

        $response->assertRedirect();
        $this->assertSame(0, AuditLog::where('event_type', 'processing_restriction_released')->count());
    }
}
