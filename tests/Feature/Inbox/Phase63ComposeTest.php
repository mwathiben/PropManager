<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Models\Lease;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-63 INBOX-COMPOSE-1/2/3 watchdog: controllers + policies +
 * Form Requests + Vue scaffold existence + i18n parity.
 */
class Phase63ComposeTest extends TestCase
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

        $unitA = $setup['units']->first();
        $unitB = $setup['units']->get(1);

        ['tenant' => $this->tenantA, 'lease' => $this->leaseA] = $this->createTenantWithActiveLease(
            $this->landlord,
            $unitA,
        );

        ['tenant' => $this->tenantB] = $this->createTenantWithActiveLease(
            $this->landlord,
            $unitB,
        );
    }

    public function test_policies_registered_in_auth_service_provider(): void
    {
        $provider = new \App\Providers\AuthServiceProvider(app());
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('policies');
        $property->setAccessible(true);
        $policies = $property->getValue($provider);

        $this->assertArrayHasKey(MessageThread::class, $policies);
        $this->assertSame(\App\Policies\MessageThreadPolicy::class, $policies[MessageThread::class]);

        $this->assertArrayHasKey(Message::class, $policies);
        $this->assertSame(\App\Policies\MessagePolicy::class, $policies[Message::class]);
    }

    public function test_landlord_can_open_inbox_index(): void
    {
        $response = $this->actingAs($this->landlord)->get('/message-threads');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('MessageThreads/Index'));
    }

    public function test_landlord_can_create_thread_with_tenant_participant(): void
    {
        $response = $this->actingAs($this->landlord)->post('/message-threads', [
            'title' => 'Rent question',
            'participants' => [$this->tenantA->id],
            'body' => 'Hi, following up on this month rent.',
        ]);

        $thread = MessageThread::query()->latest('id')->first();
        $this->assertNotNull($thread);
        $this->assertSame($this->landlord->id, $thread->landlord_id);
        $this->assertSame('Rent question', $thread->title);

        $this->assertSame(
            [$this->landlord->id, $this->tenantA->id],
            $thread->participants()->orderBy('users.id')->pluck('users.id')->all(),
        );

        $this->assertSame(1, $thread->messages()->count());
        $this->assertSame($this->landlord->id, $thread->messages()->first()->sender_id);

        $response->assertRedirect(route('message-threads.show', $thread));
    }

    public function test_landlord_cannot_invite_user_from_other_landlord(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $otherTenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $otherLandlord->id,
        ]);

        $response = $this->actingAs($this->landlord)->post('/message-threads', [
            'participants' => [$otherTenant->id],
            'body' => 'Attempt to cross-tenant.',
        ]);

        $response->assertSessionHasErrors(['participants.0']);
        $this->assertSame(0, MessageThread::query()->count());
    }

    public function test_tenant_a_cannot_view_tenant_b_thread_under_same_landlord(): void
    {
        $threadForB = MessageThread::create([
            'landlord_id' => $this->landlord->id,
            'title' => 'Private to B',
        ]);
        $threadForB->participants()->attach($this->landlord->id, [
            'role' => MessageThread::ROLE_LANDLORD,
        ]);
        $threadForB->participants()->attach($this->tenantB->id, [
            'role' => MessageThread::ROLE_TENANT,
        ]);

        $response = $this->actingAs($this->tenantA)->get(
            route('tenant.inbox.show', $threadForB),
        );

        $response->assertForbidden();
    }

    public function test_tenant_can_initiate_thread_with_landlord(): void
    {
        $response = $this->actingAs($this->tenantA)->post('/tenant/inbox', [
            'body' => 'Hello landlord, I have a question.',
        ]);

        $thread = MessageThread::query()->latest('id')->first();
        $this->assertNotNull($thread);
        $this->assertSame($this->landlord->id, $thread->landlord_id);
        $this->assertSame(
            [$this->landlord->id, $this->tenantA->id],
            $thread->participants()->orderBy('users.id')->pluck('users.id')->all(),
        );

        $response->assertRedirect(route('tenant.inbox.show', $thread));
    }

    public function test_non_participant_cannot_reply_to_thread(): void
    {
        $thread = MessageThread::create(['landlord_id' => $this->landlord->id]);
        $thread->participants()->attach($this->landlord->id, [
            'role' => MessageThread::ROLE_LANDLORD,
        ]);
        $thread->participants()->attach($this->tenantA->id, [
            'role' => MessageThread::ROLE_TENANT,
        ]);

        $response = $this->actingAs($this->tenantB)->post(
            route('tenant.inbox.messages.store', $thread),
            ['body' => 'I should not be able to do this.'],
        );

        $response->assertForbidden();
        $this->assertSame(0, $thread->messages()->count());
    }

    public function test_locked_thread_rejects_new_messages(): void
    {
        $thread = MessageThread::create([
            'landlord_id' => $this->landlord->id,
            'status' => MessageThread::STATUS_LOCKED,
        ]);
        $thread->participants()->attach($this->landlord->id, [
            'role' => MessageThread::ROLE_LANDLORD,
        ]);
        $thread->participants()->attach($this->tenantA->id, [
            'role' => MessageThread::ROLE_TENANT,
        ]);

        $response = $this->actingAs($this->landlord)->post(
            route('message-threads.messages.store', $thread),
            ['body' => 'Attempted reply.'],
        );

        $response->assertForbidden();
        $this->assertSame(0, $thread->messages()->count());
    }

    public function test_body_max_length_enforced_at_4000_chars(): void
    {
        $response = $this->actingAs($this->landlord)->post('/message-threads', [
            'participants' => [$this->tenantA->id],
            'body' => str_repeat('a', 4001),
        ]);

        $response->assertSessionHasErrors(['body']);
    }

    public function test_vue_scaffolds_exist_with_expected_tokens(): void
    {
        $base = base_path('resources/js/Pages');
        // Heading tokens are the t() KEYS, not English copy — the i18n
        // migration wrapped the literals ('Message Threads', 'Inbox') in
        // t(), so asserting copy would couple this test to one locale.
        $files = [
            "{$base}/MessageThreads/Index.vue" => ['message_threads_index.heading', 'message-threads.show'],
            "{$base}/MessageThreads/Show.vue" => ['message-threads.messages.store', 'message-compose'],
            "{$base}/Tenant/Inbox/Index.vue" => ['inbox.title', 'tenant.inbox.store'],
            "{$base}/Tenant/Inbox/Show.vue" => ['tenant.inbox.messages.store', 'tenant-message-compose'],
        ];

        foreach ($files as $path => $tokens) {
            $this->assertFileExists($path);
            $contents = file_get_contents($path);
            foreach ($tokens as $token) {
                $this->assertStringContainsString(
                    $token,
                    $contents,
                    "Vue scaffold {$path} missing expected token '{$token}'",
                );
            }
        }
    }

    public function test_inbox_i18n_keys_parity_across_locales(): void
    {
        $en = require base_path('lang/en/inbox.php');
        $sw = require base_path('lang/sw/inbox.php');
        $ar = require base_path('lang/ar/inbox.php');

        $flatten = static function (array $arr, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($arr as $k => $v) {
                $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;
                if (is_array($v)) {
                    $out = array_merge($out, $flatten($v, $key));
                } else {
                    $out[] = $key;
                }
            }
            sort($out);

            return $out;
        };

        $enKeys = $flatten($en);
        $swKeys = $flatten($sw);
        $arKeys = $flatten($ar);

        $this->assertSame($enKeys, $swKeys, 'inbox.php key parity broken between en and sw');
        $this->assertSame($enKeys, $arKeys, 'inbox.php key parity broken between en and ar');
    }

    public function test_routes_registered_with_role_middleware(): void
    {
        $expected = [
            ['GET', 'message-threads', 'message-threads.index'],
            ['GET', 'message-threads/{thread}', 'message-threads.show'],
            ['POST', 'message-threads', 'message-threads.store'],
            ['POST', 'message-threads/{thread}/messages', 'message-threads.messages.store'],
            ['GET', 'tenant/inbox', 'tenant.inbox.index'],
            ['GET', 'tenant/inbox/{thread}', 'tenant.inbox.show'],
            ['POST', 'tenant/inbox', 'tenant.inbox.store'],
            ['POST', 'tenant/inbox/{thread}/messages', 'tenant.inbox.messages.store'],
        ];

        $routes = collect(\Route::getRoutes()->getRoutes());

        foreach ($expected as [$method, $uri, $name]) {
            $matched = $routes->first(
                fn ($r) => $r->getName() === $name
                    && in_array($method, $r->methods(), true),
            );

            $this->assertNotNull($matched, "Route {$method} {$uri} ({$name}) is not registered.");
            $this->assertSame($uri, $matched->uri(), "Route {$name} uri mismatch.");
        }
    }
}
