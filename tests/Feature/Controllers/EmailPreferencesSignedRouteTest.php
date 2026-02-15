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

    public function test_signed_url_redirects_to_preferences(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);

        $signedUrl = URL::temporarySignedRoute(
            'email.preferences',
            now()->addDays(30),
            ['user' => $user->id]
        );

        $response = $this->get($signedUrl);

        $response->assertRedirect(route('notifications.preferences'));
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
}
