<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Phase-25 API-AUTH-1 + AUTH-2 + AUTH-3 watchdog: API-key self-serve
 * UI for landlords, last-used-IP tracking, audit-log entries on
 * token lifecycle.
 */
class Phase25AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_can_list_active_tokens(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $landlord->createToken('quickbooks-sync', ['landlord:manage']);

        $response = $this->actingAs($landlord)->get(route('settings.api-tokens.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ApiTokens/Index')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'quickbooks-sync')
            ->where('tokens.0.scopes', ['landlord:manage'])
        );
    }

    public function test_landlord_can_mint_a_token(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)->post(route('settings.api-tokens.store'), [
            'name' => 'integration-test',
            'scopes' => ['landlord:manage'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('plaintextToken');

        $this->assertSame(1, $landlord->tokens()->count());
        $token = $landlord->tokens()->first();
        $this->assertSame('integration-test', $token->name);
        $scopes = is_string($token->abilities) ? json_decode($token->abilities, true) : $token->abilities;
        $this->assertSame(['landlord:manage'], $scopes);
    }

    public function test_mint_rejects_unknown_scope(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)->post(route('settings.api-tokens.store'), [
            'name' => 'sneaky',
            'scopes' => ['tenant:read'],
        ])->assertSessionHasErrors('scopes.0');

        $this->assertSame(0, $landlord->tokens()->count());
    }

    public function test_tenant_role_cannot_access_token_ui(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($tenant)
            ->get(route('settings.api-tokens.index'))
            ->assertForbidden();
    }

    public function test_revoke_deletes_the_token_immediately(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $token = $landlord->createToken('to-revoke', ['landlord:manage']);
        $tokenId = $token->accessToken->id;

        $this->actingAs($landlord)
            ->delete(route('settings.api-tokens.destroy', $tokenId))
            ->assertRedirect();

        $this->assertNull(PersonalAccessToken::find($tokenId));
    }

    public function test_token_lifecycle_writes_security_log_entries(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)->post(route('settings.api-tokens.store'), [
            'name' => 'audited-token',
            'scopes' => ['integration:webhook'],
        ]);

        $issued = SecurityLog::where('event_type', 'api_token_issued')->first();
        $this->assertNotNull($issued, 'API-AUTH-3: issue must write SecurityLog(event_type=api_token_issued).');
        $this->assertSame($landlord->id, $issued->user_id);
        $this->assertSame('audited-token', $issued->metadata['name']);

        $tokenId = $landlord->tokens()->first()->id;
        $this->actingAs($landlord)->delete(route('settings.api-tokens.destroy', $tokenId));

        $revoked = SecurityLog::where('event_type', 'api_token_revoked')->first();
        $this->assertNotNull($revoked, 'API-AUTH-3: revoke must write SecurityLog(event_type=api_token_revoked).');
        $this->assertSame('audited-token', $revoked->metadata['name']);
    }

    public function test_authenticated_api_request_stamps_last_used_ip(): void
    {
        $user = User::factory()->create();
        $issued = $user->createToken('ip-track-test', ['tenant:read']);
        $tokenId = $issued->accessToken->id;
        $plaintext = $issued->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$plaintext,
            'Accept' => 'application/json',
            'REMOTE_ADDR' => '203.0.113.42',
        ])->getJson('/api/v1/tenant/lease');

        $stored = PersonalAccessToken::find($tokenId);
        $this->assertSame(
            '203.0.113.42',
            $stored->last_used_ip,
            'API-AUTH-2: TrackTokenLastUsedIp middleware must stamp the requester IP.',
        );
    }

    public function test_personal_access_tokens_has_last_used_ip_column(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('personal_access_tokens', 'last_used_ip'),
            'API-AUTH-2: personal_access_tokens.last_used_ip column must exist.',
        );
    }
}
