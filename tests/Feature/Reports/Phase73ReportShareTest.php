<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\ReportShare;
use App\Models\SavedReport;
use App\Models\User;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-73 REPORT-SHARE: minting + the public signed read-only view. The
 * signature is the authz (tamper/expiry), the row adds revocation; the report
 * id cannot be swapped (it's bound into the signature).
 */
class Phase73ReportShareTest extends TestCase
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

    private function reportFor(User $owner, string $name = 'Rent report'): SavedReport
    {
        $this->actingAs($owner);

        return SavedReport::create([
            'name' => $name,
            'config' => ['table' => 'payments', 'fields' => ['payment.amount'], 'filters' => [], 'group_by' => [], 'sort_by' => [], 'limit' => 50],
        ]);
    }

    private function share(User $owner, SavedReport $report, ?\Carbon\Carbon $expiresAt = null): ReportShare
    {
        $this->actingAs($owner);

        return ReportShare::create([
            'saved_report_id' => $report->id,
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);
    }

    private function signedUrl(ReportShare $share, ?\Carbon\Carbon $expiry = null): string
    {
        return URL::temporarySignedRoute('reports.share.view', $expiry ?? $share->expires_at, ['share' => $share->id]);
    }

    public function test_store_mints_a_share_for_an_owned_report(): void
    {
        $report = $this->reportFor($this->landlord);

        $this->actingAs($this->landlord)
            ->post(route('reports.shares.store'), ['saved_report_id' => $report->id, 'expiry_days' => 7])
            ->assertRedirect();

        $this->assertDatabaseHas('report_shares', [
            'landlord_id' => $this->landlord->id,
            'saved_report_id' => $report->id,
        ]);
    }

    public function test_store_rejects_a_foreign_report(): void
    {
        $foreign = $this->reportFor($this->otherLandlord, 'Theirs');

        $this->actingAs($this->landlord)
            ->post(route('reports.shares.store'), ['saved_report_id' => $foreign->id, 'expiry_days' => 7])
            ->assertSessionHasErrors('saved_report_id');

        $this->assertSame(0, ReportShare::withoutGlobalScopes()->count());
    }

    public function test_valid_signature_renders_the_report(): void
    {
        $report = $this->reportFor($this->landlord, 'Quarterly rent');
        $share = $this->share($this->landlord, $report);

        $this->get($this->signedUrl($share))
            ->assertOk()
            ->assertSee('Quarterly rent');

        $this->assertSame(1, $share->fresh()->view_count);
    }

    public function test_view_renders_only_the_share_owners_rows(): void
    {
        $report = $this->reportFor($this->landlord, 'Owner rows');
        // Suppress model events so the factory's invoice/lease chain doesn't
        // trip unrelated onboarding observers; we only need the payment rows.
        Model::withoutEvents(function () {
            PaymentFactory::new()->create(['landlord_id' => $this->landlord->id, 'amount' => 4242.00]);
            PaymentFactory::new()->create(['landlord_id' => $this->otherLandlord->id, 'amount' => 9999.00]);
        });
        $share = $this->share($this->landlord, $report);

        $this->get($this->signedUrl($share))
            ->assertOk()
            ->assertSee('4242')
            ->assertDontSee('9999');
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $report = $this->reportFor($this->landlord);
        $share = $this->share($this->landlord, $report);

        $this->get($this->signedUrl($share).'&tampered=1')->assertForbidden();
    }

    public function test_expired_signature_is_rejected(): void
    {
        $report = $this->reportFor($this->landlord);
        $share = $this->share($this->landlord, $report);

        $this->get($this->signedUrl($share, now()->subDay()))->assertForbidden();
    }

    public function test_revoked_share_is_rejected_even_with_valid_signature(): void
    {
        $report = $this->reportFor($this->landlord);
        $share = $this->share($this->landlord, $report);
        $url = $this->signedUrl($share);

        $share->update(['revoked_at' => now()]);

        $this->get($url)->assertForbidden();
    }

    public function test_report_id_cannot_be_swapped(): void
    {
        $reportA = $this->reportFor($this->landlord, 'A');
        $shareA = $this->share($this->landlord, $reportA);
        $reportB = $this->reportFor($this->landlord, 'B');
        $shareB = $this->share($this->landlord, $reportB);

        // A's signature on B's id → signature mismatch → 403.
        $swapped = str_replace('/reports/share/'.$shareA->id, '/reports/share/'.$shareB->id, $this->signedUrl($shareA));

        $this->get($swapped)->assertForbidden();
    }

    public function test_cannot_revoke_another_landlords_share(): void
    {
        $report = $this->reportFor($this->otherLandlord, 'Theirs');
        $foreign = $this->share($this->otherLandlord, $report);

        $this->actingAs($this->landlord)
            ->post(route('reports.shares.revoke', $foreign->id))
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->revoked_at);
    }
}
