<?php

namespace Tests\Traits;

use App\Models\User;

trait TestsMultiTenancy
{
    protected function assertCannotAccessOtherLandlordsResource(string $endpoint, User $actingAs, int $resourceId): void
    {
        $this->actingAs($actingAs)
            ->get($endpoint.'/'.$resourceId)
            ->assertStatus(403);
    }

    protected function assertResourceIsolatedByLandlord(string $endpoint, User $landlordA, User $landlordB): void
    {
        $responseA = $this->actingAs($landlordA)->getJson($endpoint);
        $responseA->assertOk();

        $dataA = $responseA->json('data') ?? $responseA->json();
        if (is_array($dataA) && ! empty($dataA)) {
            $ids = collect($dataA)->pluck('landlord_id')->unique()->filter();
            if ($ids->isNotEmpty()) {
                $this->assertTrue($ids->every(fn ($id) => $id === $landlordA->id));
            }
        }

        $responseB = $this->actingAs($landlordB)->getJson($endpoint);
        $responseB->assertOk();

        $dataB = $responseB->json('data') ?? $responseB->json();
        if (is_array($dataB) && ! empty($dataB)) {
            $ids = collect($dataB)->pluck('landlord_id')->unique()->filter();
            if ($ids->isNotEmpty()) {
                $this->assertTrue($ids->every(fn ($id) => $id === $landlordB->id));
            }
        }
    }

    protected function assertApiEndpointIsolated(string $endpoint, User $userA, User $userB): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($userA, ['landlord:manage']);
        $responseA = $this->getJson($endpoint);
        $responseA->assertOk();

        \Laravel\Sanctum\Sanctum::actingAs($userB, ['landlord:manage']);
        $responseB = $this->getJson($endpoint);
        $responseB->assertOk();
    }

    protected function assertCannotAccessOtherLandlordsApiResource(string $endpoint, User $actingAs): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($actingAs, ['landlord:manage']);

        $this->getJson($endpoint)
            ->assertStatus(403);
    }
}
