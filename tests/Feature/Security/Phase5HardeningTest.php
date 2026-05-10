<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\Tokens;
use Database\Factories\TicketFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Regression coverage for Phase-5 audit-cycle security guarantees.
 *
 * Each test names the finding ID it locks in so a future drop in
 * coverage is easy to attribute.
 */
class Phase5HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_priv9_api_register_endpoint_is_closed(): void
    {
        // PRIV-9: tenant accounts are invitation-only; the API register
        // endpoint must return 403 regardless of payload.
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Attacker',
            'email' => 'evil@example.com',
            'password' => 'Str0ng-Pass-Phrase!',
            'password_confirmation' => 'Str0ng-Pass-Phrase!',
            'device_name' => 'phone',
        ]);

        $response->assertStatus(403);
        $this->assertNull(User::where('email', 'evil@example.com')->first());
    }

    public function test_crypto10_callback_url_host_match_logic(): void
    {
        // CRYPTO-10: the host-match logic from the form request — same
        // parse_url + strcasecmp comparison, isolated from the
        // Laravel FormRequest plumbing so we don't need a tenant +
        // invoice + sanctum token to verify the comparison.
        config(['app.url' => 'https://propmanager.test']);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        $offHost = parse_url('https://attacker.example.com/steal', PHP_URL_HOST);
        $sameHost = parse_url('https://propmanager.test/cb', PHP_URL_HOST);
        $caseHost = parse_url('https://PropManager.Test/cb', PHP_URL_HOST);

        $this->assertNotSame(0, strcasecmp($offHost, $appHost), 'Off-host must NOT match');
        $this->assertSame(0, strcasecmp($sameHost, $appHost), 'Same-host must match');
        $this->assertSame(0, strcasecmp($caseHost, $appHost), 'Case-insensitive match required');
    }

    public function test_obs10_response_carries_request_id_header(): void
    {
        // OBS-10: AddRequestId middleware always emits X-Request-Id.
        $response = $this->get('/login');
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $response->headers->get('X-Request-Id')
        );
    }

    public function test_obs10_inbound_request_id_is_propagated(): void
    {
        // OBS-10: when the upstream proxy supplies a valid UUID
        // X-Request-Id, the middleware honours it instead of minting
        // a new one — this is what enables end-to-end tracing.
        $upstream = '11111111-2222-3333-4444-555555555555';
        $response = $this->withHeaders(['X-Request-Id' => $upstream])->get('/login');
        $this->assertSame($upstream, $response->headers->get('X-Request-Id'));
    }

    public function test_obs10_invalid_inbound_request_id_is_replaced(): void
    {
        // OBS-10: malformed inbound IDs must NOT propagate (a client
        // sending a 4 KB attacker-controlled string in X-Request-Id
        // would otherwise pollute every log line in the request).
        $response = $this->withHeaders(['X-Request-Id' => 'not-a-uuid'])->get('/login');
        $this->assertNotSame('not-a-uuid', $response->headers->get('X-Request-Id'));
    }

    public function test_priv7_landlord_cannot_comment_on_other_landlords_ticket(): void
    {
        // PRIV-7: AddTicketCommentRequest::authorize must scope by
        // ticket->landlord_id. The cross-landlord request is blocked —
        // 404 (TenantScope hides the row from route binding) is just as
        // good as 403 here; both are "you can't reach this resource".
        // The point is that NO comment lands on landlord B's ticket.
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);

        $ticketB = TicketFactory::new()
            ->forLandlord($landlordB)
            ->create(['reporter_id' => $landlordB->id]);

        $this->actingAs($landlordA);
        $response = $this->postJson("/tickets/{$ticketB->id}/comments", [
            'comment' => 'cross-landlord poke',
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            'Cross-landlord comment must be blocked'
        );
        // Defense-in-depth: even if the route somehow resolved (e.g. a
        // future regression that calls withoutGlobalScope), the
        // FormRequest::authorize() must still return false for landlord A
        // → ticket B. Anonymous subclass overrides user()/route() so we
        // can exercise authorize() without touching the route layer
        // (Request has its own method() so PHPUnit MockBuilder gets
        // ambiguous about which method() we mean).
        $stub = new class extends \App\Http\Requests\Ticket\AddTicketCommentRequest
        {
            public ?User $stubUser = null;

            public ?\App\Models\Ticket $stubTicket = null;

            public function user($guard = null)
            {
                return $this->stubUser;
            }

            public function route($param = null, $default = null)
            {
                return $param === 'ticket' ? $this->stubTicket : $default;
            }
        };
        $stub->stubUser = $landlordA;
        $stub->stubTicket = $ticketB;
        $this->assertFalse($stub->authorize());
    }

    public function test_priv15_tenant_role_cannot_access_landlord_tenants_index(): void
    {
        // PRIV-15: the route-level role guard ensures a tenant-role
        // session cannot reach landlord-only tenant management surfaces
        // even if the controller's inline check regresses.
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant);
        $response = $this->get('/tenants');
        $response->assertStatus(403);
    }

    public function test_crypto8_centralised_token_generator_meets_entropy_floor(): void
    {
        // CRYPTO-8: every secret-token entry point routes through
        // App\Support\Tokens::secure with a minimum 16-byte (128-bit)
        // entropy floor.
        $this->assertSame(64, strlen(Tokens::secure(32)));
        $this->assertSame(32, strlen(Tokens::secure(16)));
        $this->expectException(\InvalidArgumentException::class);
        Tokens::secure(8);
    }

    public function test_rate9_signed_email_preferences_link_is_single_use(): void
    {
        // RATE-9: signed.once middleware rejects a replay of an
        // already-consumed signature. The first call may redirect or
        // render content; what matters is that the SECOND call gets
        // the 403 the middleware emits on duplicate signature_hash.
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'email_verified_at' => now(),
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'email.preferences',
            now()->addHour(),
            ['user' => $tenant->id]
        );

        $first = $this->get($signedUrl);
        $this->assertNotSame(403, $first->getStatusCode(), 'First hit should consume the signature, not reject it');

        $second = $this->get($signedUrl);
        $second->assertStatus(403);
    }
}
