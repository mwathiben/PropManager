<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailPreferencesSignedRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_url_redirects_to_profile_notifications(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);

        $signedUrl = URL::temporarySignedRoute(
            'email.preferences',
            now()->addDays(30),
            ['user' => $user->id]
        );

        $response = $this->get($signedUrl);

        $response->assertRedirect(route('profile.edit', ['tab' => 'notifications']));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unsigned_url_returns_403(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);

        $response = $this->get(route('email.preferences', ['user' => $user->id]));

        $response->assertForbidden();
    }

    public function test_expired_signed_url_returns_403(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);

        $signedUrl = URL::temporarySignedRoute(
            'email.preferences',
            now()->subMinute(),
            ['user' => $user->id]
        );

        $response = $this->get($signedUrl);

        $response->assertForbidden();
    }

    public function test_signed_url_with_landlord_user_returns_403(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $signedUrl = URL::temporarySignedRoute(
            'email.preferences',
            now()->addDays(30),
            ['user' => $landlord->id]
        );

        $response = $this->get($signedUrl);

        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_signed_url_with_admin_user_returns_403(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $signedUrl = URL::temporarySignedRoute(
            'email.preferences',
            now()->addDays(30),
            ['user' => $admin->id]
        );

        $response = $this->get($signedUrl);

        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_signed_url_with_caretaker_user_returns_403(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'email.preferences',
            now()->addDays(30),
            ['user' => $caretaker->id]
        );

        $response = $this->get($signedUrl);

        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_signed_url_with_nonexistent_user_returns_404(): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'email.preferences',
            now()->addDays(30),
            ['user' => 999999]
        );

        $response = $this->get($signedUrl);

        $response->assertNotFound();
    }
}
