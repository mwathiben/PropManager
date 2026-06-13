<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The Payments Hub backend was fully built but its Inertia page component
 * (resources/js/Pages/PaymentsHub/Index.vue) was never created, so every
 * payments-hub tab rendered a blank/"Page not found" page client-side while
 * returning 200 server-side. The pre-existing controller test only asserted
 * assertOk() — which passes regardless of whether the .vue file exists — so
 * the break shipped silently.
 *
 * These tests assert both halves: the route resolves to PaymentsHub/Index
 * with the right per-tab props, AND the .vue file the component name maps to
 * actually exists on disk (the gap the old assertOk() coverage missed).
 */
class PaymentsHubRenderTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /** @var array<string, list<string>> tab route name => expected props */
    private const TABS = [
        'overview' => ['stats', 'recentPayments', 'quickActions', 'payoutAccountSummary'],
        'collection' => ['paymentMethods', 'payoutAccounts', 'billingSettings'],
        'analytics' => ['collectionRates', 'platformFees'],
        'settings' => ['preferences', 'invoiceSettings', 'reminderSettings'],
    ];

    public function test_each_payments_hub_tab_renders_the_index_component_with_its_props(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        foreach (self::TABS as $tab => $props) {
            $response = $this->actingAs($landlord)->get(route("payments-hub.{$tab}"));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('PaymentsHub/Index')
                ->where('activeTab', $tab)
                ->has('setupProgress')
                ->has('tabs')
                ->etc()
            );

            foreach ($props as $prop) {
                $response->assertInertia(fn ($page) => $page->has($prop));
            }
        }
    }

    public function test_rendered_component_resolves_to_an_existing_vue_file(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $response = $this->actingAs($landlord)->get(route('payments-hub.overview'));

        $component = $response->viewData('page')['component'] ?? null;
        $this->assertSame('PaymentsHub/Index', $component);

        // The exact gap the old assertOk()-only test missed: a 200 response can
        // still reference a component whose .vue file does not exist.
        $this->assertFileExists(
            resource_path('js/Pages/'.$component.'.vue'),
            "Inertia renders {$component} but resources/js/Pages/{$component}.vue is missing.",
        );
    }

    public function test_index_redirects_to_overview(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->get(route('payments-hub.index'))
            ->assertRedirect(route('payments-hub.overview'));
    }

    public function test_payments_index_redirects_to_finance_hub_payments(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        // /payments now redirects to Finance Hub payments list (not Payments Hub transactions).
        $this->actingAs($landlord)
            ->get(route('payments.index'))
            ->assertRedirect(route('finances.payments'));
    }

    public function test_payout_entrypoint_lands_on_the_hub(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->get(route('settings.payout.index'))
            ->assertRedirect(route('payments-hub.collection'));
    }
}
