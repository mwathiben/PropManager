<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

/**
 * Phase-67 PRESENCE CI: the inbox.presence channel returns the member
 * identity ARRAY for a participant (making it a presence channel) and
 * false for everyone else — the same pivot gate as the private channel,
 * so the online roster never leaks to a non-participant or another tenant.
 */
class Phase67PresenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The /broadcasting/auth HTTP path returns 200 unconditionally under
     * the null broadcaster, so the registered channel closure itself is
     * the real gate (same approach as Phase63RealtimeTest).
     *
     * @return array<string, \Closure>
     */
    private function channelClosures(): array
    {
        $broadcaster = Broadcast::driver();
        $ref = new \ReflectionObject($broadcaster);
        while ($ref !== false && ! $ref->hasProperty('channels')) {
            $ref = $ref->getParentClass();
        }
        $this->assertNotFalse($ref, 'Could not locate channels registry on the broadcaster.');

        $property = $ref->getProperty('channels');
        $property->setAccessible(true);

        return $property->getValue($broadcaster);
    }

    public function test_presence_channel_grants_participant_identity_and_denies_others(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenantA = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $tenantB = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $thread = MessageThread::create(['landlord_id' => $landlord->id, 'title' => 'Re: rent']);
        $thread->participants()->attach($landlord->id, ['role' => 'landlord']);
        $thread->participants()->attach($tenantA->id, ['role' => 'tenant']);

        $closures = $this->channelClosures();
        $this->assertArrayHasKey('inbox.presence.{threadId}', $closures);
        $closure = $closures['inbox.presence.{threadId}'];

        // Participant → identity array (presence channel).
        $identity = $closure($tenantA, $thread->id);
        $this->assertIsArray($identity);
        $this->assertSame($tenantA->id, $identity['id']);
        $this->assertSame('tenant', $identity['role']);

        // Non-participant under the SAME landlord → denied (pivot is the gate).
        $this->assertFalse($closure($tenantB, $thread->id));

        // Cross-tenant (different landlord) → denied.
        $this->assertFalse($closure($otherLandlord, $thread->id));
    }

    public function test_presence_composable_exists_and_is_wired(): void
    {
        $this->assertFileExists(base_path('resources/js/composables/usePresenceChannel.ts'));

        foreach (['resources/js/Pages/MessageThreads/Show.vue', 'resources/js/Pages/Tenant/Inbox/Show.vue'] as $page) {
            $this->assertStringContainsString(
                'usePresenceChannel',
                (string) file_get_contents(base_path($page)),
                "{$page} should import usePresenceChannel",
            );
        }
    }
}
