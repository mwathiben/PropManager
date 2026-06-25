<?php

declare(strict_types=1);

namespace App\Services\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\ManagementFeeBase;
use App\Enums\ManagementFeeFlatCadence;
use App\Exceptions\DataIntegrityException;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Slice-2 PR-2.3: the seam where "the contract is the config" becomes real.
 *
 * Activating a signed management agreement transitions it to active AND writes
 * the fee clause's governed values onto the owner relationship, LOCKING them so
 * the fee can only ever change through a re-signed amendment. The owner
 * statement and payout downstream read that locked config — never a free-form
 * field — so what the PM bills the owner is exactly what the owner signed.
 *
 * Every refusal is fail-closed: if the agreement isn't activatable or its fee
 * clause is missing/malformed, NO money config is written and the transition is
 * not made.
 */
class AgreementApplicator
{
    /**
     * Transition a signed agreement to active and apply + lock its governed fee.
     *
     * @throws DataIntegrityException when the agreement isn't activatable or its
     *                                fee clause is missing/malformed.
     */
    public function activate(ManagementAgreement $agreement): void
    {
        if (! $agreement->status->canTransitionTo(AgreementStatus::Active)) {
            throw new DataIntegrityException(
                "A {$agreement->status->value} agreement cannot be activated; only a signed agreement can.",
                'agreement.not_activatable',
            );
        }

        $feeParams = $this->resolveFeeParams($agreement);

        $agreement->loadMissing('propertyOwner');
        $owner = $agreement->propertyOwner;
        if ($owner === null) {
            throw new DataIntegrityException(
                'The agreement has no property owner to apply the fee to.',
                'agreement.no_owner',
            );
        }

        DB::transaction(function () use ($agreement, $owner, $feeParams): void {
            PropertyOwner::withoutFeeLock(function () use ($owner, $agreement, $feeParams): void {
                $owner->forceFill([
                    'management_fee_type' => $feeParams->type,
                    'management_fee_value' => $feeParams->value,
                    'management_fee_base' => $feeParams->base ?? ManagementFeeBase::Collected,
                    'management_fee_flat_cadence' => $feeParams->cadence ?? ManagementFeeFlatCadence::PerPeriod,
                    'management_fee_locked_at' => now(),
                    'management_agreement_id' => $agreement->id,
                ])->save();
            });

            $agreement->forceFill([
                'status' => AgreementStatus::Active,
                'activated_at' => now(),
            ])->save();
        });
    }

    private function resolveFeeParams(ManagementAgreement $agreement): \App\Services\ManagementFee\FeeClauseParams
    {
        $feeClause = $agreement->feeClause();
        if ($feeClause === null) {
            throw new DataIntegrityException(
                'A management agreement must carry a fee clause before it can be activated.',
                'agreement.no_fee_clause',
            );
        }

        try {
            $feeParams = $feeClause->feeParams();
        } catch (InvalidArgumentException $e) {
            throw new DataIntegrityException(
                'The agreement fee clause is malformed and cannot be applied: '.$e->getMessage(),
                'agreement.invalid_fee_clause',
            );
        }

        if ($feeParams === null) {
            throw new DataIntegrityException(
                'A management agreement must carry a fee clause before it can be activated.',
                'agreement.no_fee_clause',
            );
        }

        return $feeParams;
    }
}
