<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ManagerNavBadgesTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    /**
     * A manager is a scope owner (User::isScopeOwner() === true, landlord_id == own id),
     * so it must receive the full landlord nav-badge bundle. Before the fix the match
     * had no 'manager' arm and a manager fell through to default => null — no badges.
     */
    public function test_manager_receives_the_same_nav_badge_bundle_as_a_landlord(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $manager = User::factory()->manager()->create();

        $this->seedScopeOwnerBadges($landlord);
        $this->seedScopeOwnerBadges($manager);

        $landlordBadges = $this->navBadgesFor($landlord);
        $managerBadges = $this->navBadgesFor($manager);

        $this->assertIsArray(
            $managerBadges,
            'A manager is a scope owner and must receive the landlord badge bundle, not null.',
        );
        $this->assertSame(
            array_keys($landlordBadges),
            array_keys($managerBadges),
            'Manager must surface the same badge keys as a landlord given identical data.',
        );
        $this->assertEquals($landlordBadges, $managerBadges);
    }

    private function seedScopeOwnerBadges(User $scopeOwner): void
    {
        Notification::create([
            'landlord_id' => $scopeOwner->id,
            'recipient_id' => $scopeOwner->id,
            'type' => 'rent_reminder',
            'channel' => 'in_app',
            'subject' => 'Test notification',
            'message' => 'Test notification body',
            'status' => 'sent',
            'read_at' => null,
        ]);

        Ticket::factory()->open()->forLandlord($scopeOwner)->reportedBy($scopeOwner)->create();
    }

    private function navBadgesFor(User $user): ?array
    {
        $middleware = new HandleInertiaRequests;
        $method = new \ReflectionMethod($middleware, 'getNavBadges');
        $method->setAccessible(true);

        $request = Request::create('/dashboard');
        $request->setUserResolver(fn () => $user);

        return $method->invoke($middleware, $request);
    }
}
