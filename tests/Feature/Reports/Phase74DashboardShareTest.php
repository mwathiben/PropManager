<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\DashboardShare;
use App\Models\LandlordDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-74 DASH-SHARE: minting + the public signed read-only dashboard view.
 * The signature is the authz (tamper/expiry); the row adds revocation; the
 * dashboard id cannot be swapped (it's bound into the signature). Mirrors the
 * Phase-73 report-share suite.
 */
class Phase74DashboardShareTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
        $this->otherLandlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function dashboardFor(User $owner, string $name = 'Owner KPIs'): LandlordDashboard
    {
        $this->actingAs($owner);

        return LandlordDashboard::create([
            'name' => $name,
            'slug' => 'd-'.uniqid(),
            'layout' => [['type' => 'text', 'body' => 'Welcome to the board', 'size' => 'wide']],
        ]);
    }

    private function share(User $owner, LandlordDashboard $dashboard, ?\Carbon\Carbon $expiresAt = null): DashboardShare
    {
        $this->actingAs($owner);

        return DashboardShare::create([
            'landlord_dashboard_id' => $dashboard->id,
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);
    }

    private function signedUrl(DashboardShare $share, ?\Carbon\Carbon $expiry = null): string
    {
        return URL::temporarySignedRoute('dashboards.share.view', $expiry ?? $share->expires_at, ['share' => $share->id]);
    }

    public function test_store_mints_a_share_for_an_owned_dashboard(): void
    {
        $dashboard = $this->dashboardFor($this->landlord);

        $this->actingAs($this->landlord)
            ->post(route('dashboards.shares.store'), ['landlord_dashboard_id' => $dashboard->id, 'expiry_days' => 7])
            ->assertRedirect();

        $this->assertDatabaseHas('dashboard_shares', [
            'landlord_id' => $this->landlord->id,
            'landlord_dashboard_id' => $dashboard->id,
        ]);
    }

    public function test_store_rejects_a_foreign_dashboard(): void
    {
        $foreign = $this->dashboardFor($this->otherLandlord, 'Theirs');

        $this->actingAs($this->landlord)
            ->post(route('dashboards.shares.store'), ['landlord_dashboard_id' => $foreign->id, 'expiry_days' => 7])
            ->assertSessionHasErrors('landlord_dashboard_id');

        $this->assertSame(0, DashboardShare::withoutGlobalScopes()->count());
    }

    public function test_valid_signature_renders_the_dashboard(): void
    {
        $dashboard = $this->dashboardFor($this->landlord, 'Quarterly board');
        $share = $this->share($this->landlord, $dashboard);

        $this->get($this->signedUrl($share))
            ->assertOk()
            ->assertSee('Quarterly board')
            ->assertSee('Welcome to the board');

        $this->assertSame(1, $share->fresh()->view_count);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $dashboard = $this->dashboardFor($this->landlord);
        $share = $this->share($this->landlord, $dashboard);

        $this->get($this->signedUrl($share).'&tampered=1')->assertForbidden();
    }

    public function test_expired_signature_is_rejected(): void
    {
        $dashboard = $this->dashboardFor($this->landlord);
        $share = $this->share($this->landlord, $dashboard);

        $this->get($this->signedUrl($share, now()->subDay()))->assertForbidden();
    }

    public function test_revoked_share_is_rejected_even_with_valid_signature(): void
    {
        $dashboard = $this->dashboardFor($this->landlord);
        $share = $this->share($this->landlord, $dashboard);
        $url = $this->signedUrl($share);

        $share->update(['revoked_at' => now()]);

        $this->get($url)->assertForbidden();
    }

    public function test_dashboard_id_cannot_be_swapped(): void
    {
        $dashA = $this->dashboardFor($this->landlord, 'A');
        $shareA = $this->share($this->landlord, $dashA);
        $dashB = $this->dashboardFor($this->landlord, 'B');
        $shareB = $this->share($this->landlord, $dashB);

        $swapped = str_replace('/dashboards/share/'.$shareA->id, '/dashboards/share/'.$shareB->id, $this->signedUrl($shareA));

        $this->get($swapped)->assertForbidden();
    }

    public function test_revoke_is_idempotent(): void
    {
        $dashboard = $this->dashboardFor($this->landlord);
        $share = $this->share($this->landlord, $dashboard);

        $this->actingAs($this->landlord)->post(route('dashboards.shares.revoke', $share->id))->assertRedirect();
        $firstRevokedAt = $share->fresh()->revoked_at;

        $this->actingAs($this->landlord)->post(route('dashboards.shares.revoke', $share->id))->assertRedirect();
        $this->assertEquals($firstRevokedAt, $share->fresh()->revoked_at);
    }

    public function test_cannot_revoke_another_landlords_share(): void
    {
        $dashboard = $this->dashboardFor($this->otherLandlord, 'Theirs');
        $foreign = $this->share($this->otherLandlord, $dashboard);

        $this->actingAs($this->landlord)
            ->post(route('dashboards.shares.revoke', $foreign->id))
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->revoked_at);
    }
}
