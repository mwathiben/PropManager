<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Building;
use App\Models\PaymentConfiguration;
use App\Models\Unit;
use App\Models\WaterConnection;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Guards two silent mass-mutation data-loss paths in the landlord onboarding
 * wizard:
 *
 *  - step 5 (financial): the bulk target_rent rewrite must be an explicit
 *    opt-in (apply_default_to_existing) and must log the affected row count,
 *    instead of silently repricing every unit on value-equality.
 *  - step 4 (structure): a re-save that would force-delete units already
 *    carrying leases or water readings must refuse (fail-closed) and log,
 *    instead of hard-deleting billing history.
 */
class OnboardingDataLossGuardsTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function processStep(int $step, array $data, \App\Models\User $landlord): bool
    {
        return app(OnboardingService::class)->processStep(
            $step,
            $data,
            $landlord,
            $landlord->getOrCreateOnboardingProgress(),
        );
    }

    /**
     * @return array{0: \App\Models\User, 1: \Illuminate\Support\Collection}
     */
    private function landlordWithUnitsAtRent(float $rent): array
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();

        Unit::where('landlord_id', $landlord->id)->update(['target_rent' => $rent]);
        PaymentConfiguration::factory()->create([
            'landlord_id' => $landlord->id,
            'default_rent' => $rent,
        ]);

        $this->actingAs($landlord);

        return [$landlord, $units];
    }

    private function financialPayload(array $overrides = []): array
    {
        return array_merge([
            'default_rent' => 30000,
            'water_billing_type' => 'none',
            'accepted_payment_methods' => ['cash'],
        ], $overrides);
    }

    // ---- STEP 5: financial bulk rent propagation ---------------------------

    public function test_step5_propagates_default_rent_only_when_opted_in_and_logs_count(): void
    {
        [$landlord] = $this->landlordWithUnitsAtRent(25000);
        Log::spy();

        $result = $this->processStep(5, $this->financialPayload([
            'default_rent' => 30000,
            'apply_default_to_existing' => true,
        ]), $landlord);

        $this->assertTrue($result);
        $this->assertSame(
            8,
            Unit::where('landlord_id', $landlord->id)->where('target_rent', 30000)->count(),
            'every matching unit should be repriced when the landlord opts in.',
        );

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) use ($landlord): bool {
                return $message === 'Onboarding bulk rent update'
                    && $context['landlord_id'] === $landlord->id
                    && $context['units_updated'] === 8;
            })
            ->once();
    }

    public function test_step5_does_not_reprice_units_when_opt_in_absent(): void
    {
        [$landlord] = $this->landlordWithUnitsAtRent(25000);

        $this->processStep(5, $this->financialPayload(['default_rent' => 30000]), $landlord);

        $this->assertSame(
            8,
            Unit::where('landlord_id', $landlord->id)->where('target_rent', 25000)->count(),
            'units must be left untouched when the landlord did not opt in.',
        );
        $this->assertSame(0, Unit::where('landlord_id', $landlord->id)->where('target_rent', 30000)->count());
    }

    public function test_step5_does_not_reprice_units_when_opt_in_false(): void
    {
        [$landlord] = $this->landlordWithUnitsAtRent(25000);

        $this->processStep(5, $this->financialPayload([
            'default_rent' => 30000,
            'apply_default_to_existing' => false,
        ]), $landlord);

        $this->assertSame(8, Unit::where('landlord_id', $landlord->id)->where('target_rent', 25000)->count());
    }

    public function test_step5_still_persists_payment_configuration_without_opt_in(): void
    {
        [$landlord] = $this->landlordWithUnitsAtRent(25000);

        $this->processStep(5, $this->financialPayload(['default_rent' => 30000]), $landlord);

        $this->assertDatabaseHas('payment_configurations', [
            'landlord_id' => $landlord->id,
            'default_rent' => 30000,
        ]);
    }

    public function test_step5_validation_passes_opt_in_flag_through_to_the_service(): void
    {
        [$landlord] = $this->landlordWithUnitsAtRent(25000);

        $this->actingAs($landlord)
            ->post(route('onboarding.step.save', ['step' => 5]), $this->financialPayload([
                'default_rent' => 30000,
                'apply_default_to_existing' => true,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(8, Unit::where('landlord_id', $landlord->id)->where('target_rent', 30000)->count());
    }

    // ---- STEP 4: structure replace fail-closed guard -----------------------

    public function test_step4_refuses_to_replace_structure_when_a_unit_has_a_lease(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        $this->createTenantWithActiveLease($landlord, $units->first());
        $this->actingAs($landlord);
        Log::spy();

        try {
            $this->processStep(4, [
                'has_wings' => false,
                'floors' => 1,
                'units_per_floor' => 2,
            ], $landlord);
            $this->fail('structure replace must refuse with a ValidationException when dependent rows exist.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('error', $e->errors());
            $this->assertNotSame(
                'onboarding.page.structure.rebuild_blocked',
                $e->errors()['error'][0],
                'the refusal message must resolve to a translation, not the raw key.',
            );
        }

        $this->assertSame(8, Unit::where('landlord_id', $landlord->id)->count(), 'no unit may be deleted.');
        $this->assertSame(1, Building::where('landlord_id', $landlord->id)->count(), 'no building may be deleted.');

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'structure replace blocked')
                    && $context['leases'] === 1;
            })
            ->once();
    }

    public function test_step4_refuses_to_replace_structure_when_a_unit_has_water_readings(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        $this->createWaterReadingForUnit($units->first());
        $this->actingAs($landlord);

        try {
            $this->processStep(4, [
                'has_wings' => false,
                'floors' => 1,
                'units_per_floor' => 2,
            ], $landlord);
            $this->fail('structure replace must refuse with a ValidationException when water readings exist.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('error', $e->errors());
        }

        $this->assertSame(8, Unit::where('landlord_id', $landlord->id)->count());
    }

    public function test_step4_refuses_to_replace_structure_when_a_unit_has_a_water_connection(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        // A water-client connection with no lease and no readings: its invoices
        // hang off the connection (not a lease), and water_connections.unit_id is
        // nullOnDelete, so a force-delete would silently detach it.
        WaterConnection::factory()->create([
            'landlord_id' => $landlord->id,
            'unit_id' => $units->first()->id,
        ]);
        $this->actingAs($landlord);
        Log::spy();

        try {
            $this->processStep(4, [
                'has_wings' => false,
                'floors' => 1,
                'units_per_floor' => 2,
            ], $landlord);
            $this->fail('structure replace must refuse when a unit has a water connection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('error', $e->errors());
        }

        $this->assertSame(8, Unit::where('landlord_id', $landlord->id)->count(), 'no unit may be deleted.');

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'structure replace blocked')
                    && ($context['water_connections'] ?? 0) === 1;
            })
            ->once();
    }

    public function test_step4_replaces_structure_when_no_dependents_exist(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $this->actingAs($landlord);
        Log::spy();

        $result = $this->processStep(4, [
            'has_wings' => false,
            'floors' => 1,
            'units_per_floor' => 2,
        ], $landlord);

        $this->assertTrue($result);
        $this->assertSame(2, Unit::where('landlord_id', $landlord->id)->count(), 'clean structure should be replaced.');

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Onboarding structure replaced'
                    && $context['units_deleted'] === 8;
            })
            ->once();
    }

    public function test_step5_opt_in_does_not_reprice_occupied_or_leased_units(): void
    {
        [$landlord, $units] = $this->landlordWithUnitsAtRent(25000);
        $leasedUnit = $units->first();
        $this->createTenantWithActiveLease($landlord, $leasedUnit);
        Log::spy();

        $this->processStep(5, $this->financialPayload([
            'default_rent' => 30000,
            'apply_default_to_existing' => true,
        ]), $landlord);

        $this->assertSame(
            25000.0,
            (float) $leasedUnit->fresh()->target_rent,
            'an occupied/leased unit must keep its rent even when the landlord opts in.',
        );
        $this->assertSame(
            7,
            Unit::where('landlord_id', $landlord->id)->where('target_rent', 30000)->count(),
            'only the vacant, never-leased units should be repriced on opt-in.',
        );

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $m, array $c): bool => $m === 'Onboarding bulk rent update' && $c['units_updated'] === 7)
            ->once();
    }

    // ---- ISSUE 3: the step write path must not swallow failures silently ----

    public function test_savestep_logs_swallowed_throwable_and_returns_generic_error(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $this->actingAs($landlord);

        $this->mock(OnboardingService::class, function ($mock): void {
            $mock->shouldReceive('processStep')->andThrow(new \RuntimeException('boom'));
        });

        Log::spy();

        $response = $this->post(route('onboarding.step.save', ['step' => 2]), [
            'name' => 'Test Landlord',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) use ($landlord): bool {
                return $message === 'Onboarding step save failed'
                    && $context['step'] === 2
                    && $context['user_id'] === $landlord->id
                    && $context['exception'] === \RuntimeException::class;
            })
            ->once();
    }

    public function test_step4_resave_with_lease_surfaces_validation_error_over_http(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        $this->createTenantWithActiveLease($landlord, $units->first());
        $this->actingAs($landlord);

        $response = $this->post(route('onboarding.step.save', ['step' => 4]), [
            'has_wings' => false,
            'floors' => 1,
            'units_per_floor' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertSame(8, Unit::where('landlord_id', $landlord->id)->count(), 'no unit deleted on a blocked re-save.');
    }

    public function test_step4_structure_guard_is_scoped_per_landlord(): void
    {
        // Landlord B has a leased unit; it must neither block landlord A's own
        // rebuild nor be touched by it — TenantScope keeps the guard per-tenant.
        ['landlord' => $landlordB, 'units' => $unitsB] = $this->createLandlordWithFullSetup();
        $this->createTenantWithActiveLease($landlordB, $unitsB->first());

        ['landlord' => $landlordA] = $this->createLandlordWithFullSetup();
        $this->actingAs($landlordA);

        $result = $this->processStep(4, [
            'has_wings' => false,
            'floors' => 1,
            'units_per_floor' => 2,
        ], $landlordA);

        $this->assertTrue($result, "A has no dependent history, so A's own rebuild proceeds.");
        $this->assertSame(2, Unit::where('landlord_id', $landlordA->id)->count());
        $this->assertSame(
            8,
            Unit::withoutGlobalScope('landlord')->where('landlord_id', $landlordB->id)->count(),
            "B's units are untouched by A's rebuild.",
        );
        $this->assertSame(
            1,
            Building::withoutGlobalScope('landlord')->where('landlord_id', $landlordB->id)->count(),
            "B's building is untouched by A's rebuild.",
        );
    }
}
