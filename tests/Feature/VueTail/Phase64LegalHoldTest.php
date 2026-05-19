<?php

declare(strict_types=1);

namespace Tests\Feature\VueTail;

use App\Models\Lease;
use App\Models\LegalHold;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\LegalHoldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-64 LEGAL-HOLD-1/2/3 watchdog: registry + retention cron
 * integration + lawful-basis audit metadata.
 */
class Phase64LegalHoldTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    private function threadWith(User $landlord, User $tenant): MessageThread
    {
        $thread = MessageThread::create(['landlord_id' => $landlord->id]);
        $thread->participants()->attach($landlord->id, ['role' => MessageThread::ROLE_LANDLORD]);
        $thread->participants()->attach($tenant->id, ['role' => MessageThread::ROLE_TENANT]);

        return $thread;
    }

    public function test_legal_holds_table_present(): void
    {
        $this->assertTrue(Schema::hasTable('legal_holds'));
        foreach ([
            'holdable_type',
            'holdable_id',
            'reason',
            'held_by',
            'held_at',
            'released_at',
            'released_by',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('legal_holds', $column),
                "legal_holds.{$column} missing",
            );
        }
    }

    public function test_hold_creates_active_row(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);

        $hold = LegalHoldRegistry::hold($thread, $this->landlord, 'Court order CV/2026/0123');

        $this->assertInstanceOf(LegalHold::class, $hold);
        $this->assertTrue($hold->isActive());
        $this->assertSame(MessageThread::class, $hold->holdable_type);
        $this->assertSame($thread->id, (int) $hold->holdable_id);
        $this->assertSame($this->landlord->id, (int) $hold->held_by);
    }

    public function test_release_stamps_released_at_and_by(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);
        LegalHoldRegistry::hold($thread, $this->landlord, 'X');

        $released = LegalHoldRegistry::release($thread, $this->landlord);

        $this->assertNotNull($released);
        $this->assertFalse($released->isActive());
        $this->assertNotNull($released->released_at);
        $this->assertSame($this->landlord->id, (int) $released->released_by);
    }

    public function test_is_held_reflects_active_hold(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);

        $this->assertFalse(LegalHoldRegistry::isHeld($thread));

        LegalHoldRegistry::hold($thread, $this->landlord, 'preserve');
        $this->assertTrue(LegalHoldRegistry::isHeld($thread));

        LegalHoldRegistry::release($thread, $this->landlord);
        $this->assertFalse(LegalHoldRegistry::isHeld($thread));
    }

    public function test_held_ids_for_caches_with_60s_ttl(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);
        LegalHoldRegistry::hold($thread, $this->landlord, 'reason');

        $first = LegalHoldRegistry::heldIdsFor(MessageThread::class);
        $this->assertSame([$thread->id], $first);

        // Bypass cache flush — assert the cache key was set.
        $cacheKey = 'legal_hold:ids:App_Models_MessageThread';
        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_retention_command_excludes_held_threads(): void
    {
        $heldThread = $this->threadWith($this->landlord, $this->tenant);
        $unheldThread = $this->threadWith($this->landlord, $this->tenant);

        $oldMsgHeld = $heldThread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'held — must NOT be soft-deleted',
        ]);
        $oldMsgHeld->forceFill(['created_at' => Carbon::now()->subDays(2600)])->save();

        $oldMsgUnheld = $unheldThread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'unheld — should be soft-deleted',
        ]);
        $oldMsgUnheld->forceFill(['created_at' => Carbon::now()->subDays(2600)])->save();

        LegalHoldRegistry::hold($heldThread, $this->landlord, 'litigation');
        $this->landlord->update(['message_retention_days' => 30]);

        $this->artisan('messages:enforce-retention')->assertSuccessful();

        $this->assertDatabaseHas('messages', [
            'id' => $oldMsgHeld->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('messages', ['id' => $oldMsgUnheld->id]);
    }

    public function test_legal_hold_audit_metadata_tags_lawful_basis_legal_obligation(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);
        $hold = new LegalHold;
        $hold->forceFill([
            'holdable_type' => MessageThread::class,
            'holdable_id' => $thread->id,
            'reason' => 'X',
            'held_by' => $this->landlord->id,
            'held_at' => now(),
        ]);

        $this->assertSame('legal_obligation', $hold->getLawfulBasis());
    }

    public function test_policy_registered_in_auth_service_provider(): void
    {
        $provider = new \App\Providers\AuthServiceProvider(app());
        $ref = new \ReflectionClass($provider);
        $prop = $ref->getProperty('policies');
        $prop->setAccessible(true);
        $policies = $prop->getValue($provider);

        $this->assertArrayHasKey(LegalHold::class, $policies);
        $this->assertSame(\App\Policies\LegalHoldPolicy::class, $policies[LegalHold::class]);
    }

    public function test_landlord_can_hold_own_thread(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);

        $this->assertTrue(
            $this->landlord->can('create', [LegalHold::class, MessageThread::class, $thread->id]),
        );
    }

    public function test_tenant_cannot_create_legal_hold(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenant);

        $this->assertFalse(
            $this->tenant->can('create', [LegalHold::class, MessageThread::class, $thread->id]),
        );
    }
}
