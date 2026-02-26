<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Notification\UnsubscribeUrlResolver;
use Tests\TestCase;

class UnsubscribeUrlResolverTest extends TestCase
{
    private UnsubscribeUrlResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UnsubscribeUrlResolver;
    }

    public function test_tenant_returns_signed_email_preferences_url(): void
    {
        $tenant = User::factory()->make(['role' => 'tenant', 'id' => 99]);

        $url = $this->resolver->resolve($tenant);

        $this->assertNotNull($url);
        $this->assertStringContainsString('email/preferences', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_landlord_returns_notifications_settings_url(): void
    {
        $landlord = User::factory()->make(['role' => 'landlord']);

        $url = $this->resolver->resolve($landlord);

        $this->assertNotNull($url);
        $this->assertEquals(route('notifications.settings'), $url);
    }

    public function test_caretaker_returns_notifications_settings_url(): void
    {
        $caretaker = User::factory()->make(['role' => 'caretaker']);

        $url = $this->resolver->resolve($caretaker);

        $this->assertNotNull($url);
        $this->assertEquals(route('notifications.settings'), $url);
    }

    public function test_super_admin_returns_null(): void
    {
        $admin = User::factory()->make(['role' => 'super_admin']);

        $url = $this->resolver->resolve($admin);

        $this->assertNull($url);
    }

    public function test_unknown_role_returns_null(): void
    {
        $user = User::factory()->make(['role' => 'unknown']);

        $url = $this->resolver->resolve($user);

        $this->assertNull($url);
    }

    public function test_resolve_for_header_returns_signed_post_url_for_tenant(): void
    {
        $tenant = User::factory()->make(['role' => 'tenant', 'id' => 99]);

        $url = $this->resolver->resolveForHeader($tenant);

        $this->assertNotNull($url);
        $this->assertStringContainsString('email/unsubscribe', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('user=99', $url);
    }

    public function test_resolve_for_header_returns_notifications_settings_for_landlord(): void
    {
        $landlord = User::factory()->make(['role' => 'landlord']);

        $url = $this->resolver->resolveForHeader($landlord);

        $this->assertNotNull($url);
        $this->assertEquals(route('notifications.settings'), $url);
    }

    public function test_resolve_for_header_returns_null_for_unknown_role(): void
    {
        $user = User::factory()->make(['role' => 'unknown']);

        $url = $this->resolver->resolveForHeader($user);

        $this->assertNull($url);
    }
}
