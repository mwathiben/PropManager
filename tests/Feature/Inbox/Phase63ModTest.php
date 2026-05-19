<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Models\Lease;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\MessageContentPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-63 INBOX-MOD-1/2/3 watchdog: moderation transitions,
 * retention cron, rate limiter, spam guard.
 */
class Phase63ModTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenantA;

    private User $tenantB;

    private Lease $leaseA;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];

        ['tenant' => $this->tenantA, 'lease' => $this->leaseA] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );

        ['tenant' => $this->tenantB] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->get(1),
        );
    }

    private function threadWith(User $landlord, User $tenant, string $status = MessageThread::STATUS_OPEN): MessageThread
    {
        $thread = MessageThread::create([
            'landlord_id' => $landlord->id,
            'status' => $status,
        ]);
        $thread->participants()->attach($landlord->id, ['role' => MessageThread::ROLE_LANDLORD]);
        $thread->participants()->attach($tenant->id, ['role' => MessageThread::ROLE_TENANT]);

        return $thread;
    }

    public function test_sender_can_delete_within_5_min_window(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'oops, ignore me',
        ]);

        $response = $this->actingAs($this->landlord)->delete(route('messages.destroy', $message));

        $response->assertRedirect();
        $this->assertSoftDeleted('messages', ['id' => $message->id]);

        // System placeholder inserted so the recipient sees the gap.
        $this->assertSame(
            1,
            $thread->fresh()->messages()->where('message_type', Message::TYPE_SYSTEM)->count(),
        );
    }

    public function test_non_sender_cannot_delete(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'landlord message',
        ]);

        $response = $this->actingAs($this->tenantA)->delete(route('messages.destroy', $message));

        $response->assertForbidden();
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'deleted_at' => null]);
    }

    public function test_sender_cannot_delete_after_5_min_window(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'stale',
        ]);
        $message->forceFill(['created_at' => now()->subMinutes(6)])->save();

        $response = $this->actingAs($this->landlord)->delete(route('messages.destroy', $message));

        $response->assertForbidden();
    }

    public function test_system_message_immutable(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $system = $thread->recordSystemEvent('Thread opened');

        $response = $this->actingAs($this->landlord)->delete(route('messages.destroy', $system));

        $response->assertForbidden();
    }

    public function test_landlord_can_archive_thread(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);

        $response = $this->actingAs($this->landlord)
            ->post(route('message-threads.archive', $thread));

        $response->assertRedirect();
        $this->assertSame(MessageThread::STATUS_ARCHIVED, $thread->fresh()->status);
        $this->assertSame(
            1,
            $thread->fresh()->messages()->where('message_type', Message::TYPE_SYSTEM)->count(),
        );
    }

    public function test_tenant_cannot_archive_thread(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);

        $response = $this->actingAs($this->tenantA)
            ->post(route('message-threads.archive', $thread));

        $response->assertForbidden();
    }

    public function test_landlord_can_lock_and_unlock_thread(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);

        $this->actingAs($this->landlord)
            ->post(route('message-threads.lock', $thread))
            ->assertRedirect();
        $this->assertSame(MessageThread::STATUS_LOCKED, $thread->fresh()->status);

        $this->actingAs($this->landlord)
            ->post(route('message-threads.unlock', $thread))
            ->assertRedirect();
        $this->assertSame(MessageThread::STATUS_OPEN, $thread->fresh()->status);
    }

    public function test_spam_guard_rejects_url_repetition(): void
    {
        $body = str_repeat('Visit https://spam.example.com now! ', 10);
        $this->assertTrue(MessageContentPolicy::isSpam($body));
    }

    public function test_spam_guard_passes_normal_body(): void
    {
        $body = "Hi there, just following up on the rent for this month.\nLet me know when convenient.";
        $this->assertFalse(MessageContentPolicy::isSpam($body));
    }

    public function test_spam_guard_rejects_high_non_printable_fraction(): void
    {
        $body = str_repeat("\x01\x02\x03", 30);
        $this->assertTrue(MessageContentPolicy::isSpam($body));
    }

    public function test_spam_body_rejected_at_form_request_level(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $spamBody = str_repeat('Visit https://spam.example.com now! ', 10);

        $response = $this->actingAs($this->landlord)->post(
            route('message-threads.messages.store', $thread),
            ['body' => $spamBody],
        );

        $response->assertSessionHasErrors(['body']);
        $this->assertSame(0, $thread->messages()->count());
    }

    public function test_message_rate_limiter_registered(): void
    {
        $resolver = RateLimiter::limiter('messages');
        $this->assertNotNull($resolver, 'RateLimiter::for(\'messages\') must be registered.');
    }

    public function test_throttle_messages_middleware_on_post_routes(): void
    {
        $routes = collect(\Route::getRoutes()->getRoutes());

        $postNames = [
            'message-threads.store',
            'message-threads.messages.store',
            'tenant.inbox.store',
            'tenant.inbox.messages.store',
        ];

        foreach ($postNames as $name) {
            $route = $routes->first(fn ($r) => $r->getName() === $name);
            $this->assertNotNull($route, "Route {$name} not registered.");
            $this->assertContains(
                'throttle:messages',
                $route->gatherMiddleware(),
                "Route {$name} must carry throttle:messages middleware.",
            );
        }
    }

    public function test_users_message_retention_days_column_present(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'message_retention_days'));
    }

    public function test_enforce_retention_command_soft_deletes_messages_beyond_window(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'ancient',
        ]);
        // Force the message to be older than the platform default
        // (7 years = 2557 days).
        $message->forceFill(['created_at' => Carbon::now()->subDays(2600)])->save();

        $this->landlord->update(['message_retention_days' => 30]);

        $this->artisan('messages:enforce-retention')->assertSuccessful();

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_enforce_retention_respects_landlord_override_keep_window(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create([
            'sender_id' => $this->landlord->id,
            'body' => 'within window',
        ]);
        $message->forceFill(['created_at' => Carbon::now()->subDays(15)])->save();

        $this->landlord->update(['message_retention_days' => 30]);

        $this->artisan('messages:enforce-retention')->assertSuccessful();

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'deleted_at' => null,
        ]);
    }

    public function test_messages_enforce_retention_scheduled_daily_03_15_nairobi(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(
            fn ($e) => str_contains($e->command ?? '', 'messages:enforce-retention'),
        );

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('15 3 * * *', $event->expression);
        $this->assertSame(
            'Africa/Nairobi',
            $event->timezone instanceof \DateTimeZone
                ? $event->timezone->getName()
                : (string) $event->timezone,
        );
    }
}
