<?php

declare(strict_types=1);

namespace Tests\Unit\Agreements;

use App\Enums\AgreementStatus;
use Tests\TestCase;

/**
 * Slice-2 PR-2.1: the agreement lifecycle. isLocked() marks the point past which
 * the snapshot + governed config are immutable (signed onward); canTransitionTo()
 * encodes the legal transitions the apply/amend flow (PR 2.3) will enforce.
 */
class AgreementStatusTest extends TestCase
{
    public function test_locked_states_are_signed_active_and_amending(): void
    {
        $this->assertFalse(AgreementStatus::Draft->isLocked());
        $this->assertFalse(AgreementStatus::Sent->isLocked());
        $this->assertTrue(AgreementStatus::Signed->isLocked());
        $this->assertTrue(AgreementStatus::Active->isLocked());
        $this->assertTrue(AgreementStatus::Amending->isLocked());
        $this->assertFalse(AgreementStatus::Terminated->isLocked());
    }

    public function test_legal_forward_transitions_are_allowed(): void
    {
        $this->assertTrue(AgreementStatus::Draft->canTransitionTo(AgreementStatus::Sent));
        $this->assertTrue(AgreementStatus::Sent->canTransitionTo(AgreementStatus::Signed));
        $this->assertTrue(AgreementStatus::Signed->canTransitionTo(AgreementStatus::Active));
        $this->assertTrue(AgreementStatus::Active->canTransitionTo(AgreementStatus::Amending));
        $this->assertTrue(AgreementStatus::Amending->canTransitionTo(AgreementStatus::Active));
    }

    public function test_illegal_transitions_are_rejected(): void
    {
        $this->assertFalse(AgreementStatus::Draft->canTransitionTo(AgreementStatus::Active), 'cannot activate without signing');
        $this->assertFalse(AgreementStatus::Signed->canTransitionTo(AgreementStatus::Draft), 'cannot un-sign');
        $this->assertFalse(AgreementStatus::Terminated->canTransitionTo(AgreementStatus::Active), 'terminated is final');
    }

    public function test_any_state_can_terminate_except_terminated(): void
    {
        foreach ([AgreementStatus::Draft, AgreementStatus::Sent, AgreementStatus::Signed, AgreementStatus::Active, AgreementStatus::Amending] as $status) {
            $this->assertTrue($status->canTransitionTo(AgreementStatus::Terminated));
        }
        $this->assertFalse(AgreementStatus::Terminated->canTransitionTo(AgreementStatus::Terminated));
    }
}
