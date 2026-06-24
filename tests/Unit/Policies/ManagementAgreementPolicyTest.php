<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\ManagementAgreement;
use App\Models\User;
use App\Policies\ManagementAgreementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementAgreementPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ManagementAgreementPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ManagementAgreementPolicy;
    }

    public function test_a_manager_may_view_any_and_create(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->assertTrue($this->policy->viewAny($manager));
        $this->assertTrue($this->policy->create($manager));
    }

    public function test_a_non_manager_may_not_view_any_or_create(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->assertFalse($this->policy->viewAny($landlord));
        $this->assertFalse($this->policy->create($landlord));
    }

    public function test_a_manager_may_view_only_their_own_agreement(): void
    {
        $owner = User::factory()->create(['role' => 'manager']);
        $other = User::factory()->create(['role' => 'manager']);
        $agreement = ManagementAgreement::factory()->create(['landlord_id' => $owner->id]);

        $this->assertTrue($this->policy->view($owner, $agreement));
        $this->assertFalse($this->policy->view($other, $agreement));
    }
}
