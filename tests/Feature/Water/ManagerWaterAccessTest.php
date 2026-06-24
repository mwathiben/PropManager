<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Http\Requests\BulkOperations\UpdateMeterNumbersRequest;
use App\Http\Requests\Meter\ReplaceMeterRequest;
use App\Http\Requests\StoreWaterReadingRequest;
use App\Http\Requests\Water\SetupWaterClientsRequest;
use App\Http\Requests\Water\StoreWaterConnectionRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-86 role-split gated the water module to landlord/caretaker and never
 * accounted for the `manager` role (a scope owner). Managers could not open
 * water settings or pass the water FormRequests, so the module never showed in
 * full for them. A manager is a scope owner (landlord_id == self) and runs water
 * billing for its properties exactly like a self-managing landlord.
 */
class ManagerWaterAccessTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    /**
     * Authorize a FormRequest as the given user. Sets both the global guard
     * (requests that read auth()->user()) and the request resolver (those that
     * read $this->user()).
     */
    private function authorizesAs(FormRequest $request, User $user): bool
    {
        $this->actingAs($user);
        $request->setUserResolver(fn () => $user);

        return $request->authorize();
    }

    public function test_manager_can_open_water_settings(): void
    {
        $this->actingAs($this->manager())->get(route('water.settings'))->assertOk();
    }

    public function test_store_water_reading_request_authorizes_a_manager(): void
    {
        $this->assertTrue($this->authorizesAs(StoreWaterReadingRequest::create('/readings', 'POST'), $this->manager()));
    }

    public function test_update_meter_numbers_request_authorizes_a_manager(): void
    {
        $this->assertTrue($this->authorizesAs(UpdateMeterNumbersRequest::create('/bulk/meter-numbers', 'POST'), $this->manager()));
    }

    public function test_replace_meter_request_authorizes_a_manager(): void
    {
        $this->assertTrue($this->authorizesAs(ReplaceMeterRequest::create('/water/meters/1/replace', 'POST'), $this->manager()));
    }

    public function test_store_water_connection_request_authorizes_a_manager(): void
    {
        $this->assertTrue($this->authorizesAs(StoreWaterConnectionRequest::create('/water/connections', 'POST'), $this->manager()));
    }

    public function test_setup_water_clients_request_authorizes_a_manager(): void
    {
        $this->assertTrue($this->authorizesAs(SetupWaterClientsRequest::create('/water/clients/setup', 'POST'), $this->manager()));
    }
}
