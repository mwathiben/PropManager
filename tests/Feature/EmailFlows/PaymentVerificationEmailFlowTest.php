<?php

declare(strict_types=1);

namespace Tests\Feature\EmailFlows;

use App\Enums\Currency;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Property;
use App\Models\TenantPaymentVerification;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithMailpit;

class PaymentVerificationEmailFlowTest extends TestCase
{
    use InteractsWithMailpit, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMailpit();
        config(['app.name' => 'PropManager']);
    }

    public function test_approving_verification_sends_email_via_mailpit(): void
    {
        $scenario = $this->createVerificationScenario();
        $landlord = $scenario['landlord'];
        $tenant = $scenario['tenant'];
        $verification = $scenario['verification'];
        $unit = $scenario['unit'];
        $building = $scenario['building'];

        $response = $this->actingAs($landlord)->post(
            route('payment-verifications.approve', $verification)
        );

        $response->assertRedirect(route('payment-verifications.index'));
        $response->assertSessionHas('success');

        $verification->refresh();
        $this->assertEquals(
            TenantPaymentVerification::STATUS_PAYMENT_VERIFIED,
            $verification->status
        );

        $this->assertEmailSentTo($tenant->email, 'Payment Verified');
        $this->assertEmailCount(1);

        $html = $this->getLatestEmailHtml();
        $decodedHtml = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString($tenant->name, $decodedHtml);
        $this->assertStringContainsString($unit->unit_number, $decodedHtml);
        $this->assertStringContainsString($building->name, $decodedHtml);
        $this->assertStringContainsString(
            number_format((float) $verification->deposit_required, 2),
            $decodedHtml
        );
        $this->assertStringContainsString(
            number_format((float) $verification->first_rent_required, 2),
            $decodedHtml
        );
        $this->assertStringContainsString('KSh', $decodedHtml);
        $this->assertStringContainsString('Go to Dashboard', $decodedHtml);
        $this->assertStringContainsString('PropManager', $decodedHtml);

        $links = $this->getLatestEmailLinks();
        $this->assertDashboardLinkPresent($links);
        $this->assertSignedUnsubscribeLinkPresent($links);

        $this->assertStringNotContainsString('secret_key', strtolower($decodedHtml));
        $this->assertStringNotContainsString('APP_KEY', $decodedHtml);
        $this->assertStringNotContainsString(config('app.key'), $decodedHtml);
    }

    public function test_rejecting_verification_sends_email_via_mailpit(): void
    {
        $scenario = $this->createVerificationScenario();
        $landlord = $scenario['landlord'];
        $tenant = $scenario['tenant'];
        $verification = $scenario['verification'];
        $unit = $scenario['unit'];

        $rejectionReason = 'Payment proof is blurry and amount does not match';

        $response = $this->actingAs($landlord)->post(
            route('payment-verifications.reject', $verification),
            ['reason' => $rejectionReason]
        );

        $response->assertRedirect(route('payment-verifications.index'));
        $response->assertSessionHas('success');

        $verification->refresh();
        $this->assertEquals(
            TenantPaymentVerification::STATUS_REJECTED,
            $verification->status
        );
        $this->assertEquals($rejectionReason, $verification->rejection_reason);

        $this->assertEmailSentTo($tenant->email, 'Payment Verification Issue');
        $this->assertEmailCount(1);

        $html = $this->getLatestEmailHtml();
        $decodedHtml = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString($tenant->name, $decodedHtml);
        $this->assertStringContainsString($rejectionReason, $decodedHtml);
        $this->assertStringContainsString(
            number_format((float) $verification->deposit_required, 2),
            $decodedHtml
        );
        $this->assertStringContainsString(
            number_format((float) $verification->first_rent_required, 2),
            $decodedHtml
        );
        $this->assertStringContainsString('KSh', $decodedHtml);
        $this->assertStringContainsString('Resubmit Payment Proof', $decodedHtml);
        $this->assertStringContainsString('PropManager', $decodedHtml);

        $links = $this->getLatestEmailLinks();
        $this->assertResubmitLinkPresent($links);
        $this->assertSignedUnsubscribeLinkPresent($links);

        $this->assertStringNotContainsString('secret_key', strtolower($decodedHtml));
        $this->assertStringNotContainsString('APP_KEY', $decodedHtml);
        $this->assertStringNotContainsString(config('app.key'), $decodedHtml);
    }

    public function test_unauthorized_user_cannot_approve_verification(): void
    {
        $scenario = $this->createVerificationScenario();
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($otherLandlord)->post(
            route('payment-verifications.approve', $scenario['verification'])
        );

        $response->assertForbidden();
    }

    public function test_tenant_cannot_approve_own_verification(): void
    {
        $scenario = $this->createVerificationScenario();

        $response = $this->actingAs($scenario['tenant'])->post(
            route('payment-verifications.approve', $scenario['verification'])
        );

        $response->assertForbidden();
    }

    public function test_rejection_requires_reason(): void
    {
        $scenario = $this->createVerificationScenario();

        $response = $this->actingAs($scenario['landlord'])->post(
            route('payment-verifications.reject', $scenario['verification']),
            ['reason' => '']
        );

        $response->assertSessionHasErrors('reason');
    }

    private function createVerificationScenario(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::KES)
            ->create();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::findOrFail($lease->tenant_id);

        $verification = TenantPaymentVerification::factory()
            ->forLease($lease)
            ->paymentSubmitted()
            ->create();

        return compact('landlord', 'tenant', 'building', 'unit', 'lease', 'verification');
    }

    private function assertDashboardLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, '/dashboard')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Dashboard link not found in email');
    }

    private function assertResubmitLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, 'payment-required') || str_contains($link, 'payment_required')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Resubmit payment proof link not found in email');
    }

    private function assertSignedUnsubscribeLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, 'email/preferences') && str_contains($link, 'signature=')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Signed unsubscribe URL not found in email');
    }
}
