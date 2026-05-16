<?php

declare(strict_types=1);

namespace Tests\Feature\Vendors;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-39 PUSH-EXTEND-1/3: clickUrl propagation in PushNotificationService
 * ::send and the /ops/push test runner surface.
 */
class Phase39PushExtendTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_signature_accepts_explicit_click_url_parameter(): void
    {
        $reflection = new \ReflectionMethod(PushNotificationService::class, 'send');
        $params = $reflection->getParameters();
        $names = array_map(fn ($p) => $p->getName(), $params);

        $this->assertContains('clickUrl', $names);
    }

    public function test_ops_push_show_blocks_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'email_verified_at' => now()]);
        $this->actingAs($landlord)->get(route('ops.push.show'))->assertForbidden();
    }

    public function test_ops_push_show_renders_for_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        $response = $this->actingAs($admin)->get(route('ops.push.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Ops/PushTester')
            ->has('users')
        );
    }

    public function test_ops_push_send_validates_user_id(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $response = $this->actingAs($admin)->post(route('ops.push.send'), [
            'title' => 'x',
            'body' => 'y',
            // missing user_id
        ]);

        $response->assertSessionHasErrors(['user_id']);
    }

    public function test_ops_push_send_returns_back_flash_on_no_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('ops.push.send'), [
            'user_id' => $target->id,
            'title' => 'test',
            'body' => 'test',
            'click_url' => '/leases/42',
        ]);

        // No active subscription → send returns false → flash with error key.
        $response->assertRedirect();
        $this->assertNotNull(session('error'));
    }
}
