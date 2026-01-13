<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('api')]
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_login_with_valid_credentials(): void
    {
        $this->markTestSkipped('API routes not yet implemented - tests document expected behavior');
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $tenant->email,
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'role'],
        ]);
    }

    public function test_login_returns_sanctum_token(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $tenant->email,
            'password' => 'password123',
            'device_name' => 'Mobile App',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
        $this->assertIsString($response->json('token'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $tenant->email,
            'password' => 'wrongpassword',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
        $response = $this->postJson('/api/v1/auth/login', [
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_revokes_token(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_get_current_user(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_login(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_token_has_correct_abilities_for_tenant(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_token_has_correct_abilities_for_landlord(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }
}
