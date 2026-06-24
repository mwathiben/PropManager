<?php

declare(strict_types=1);

namespace App\Services\Agreements;

use App\Enums\AgreementStatus;
use App\Models\ManagementAgreement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Slice-2 PR-2.2: assembles a draft management agreement from a validated
 * compose payload — one transactional write so a half-composed agreement can't
 * persist. Keeps the controller thin.
 */
class AgreementComposer
{
    /**
     * @param  array{title?: ?string, property_owner_id: int|string, clauses: list<array{clause_id: int|string, params?: array<string, mixed>}>}  $data
     */
    public function composeDraft(User $manager, array $data): ManagementAgreement
    {
        return DB::transaction(function () use ($manager, $data): ManagementAgreement {
            $agreement = ManagementAgreement::create([
                'landlord_id' => $manager->id,
                'property_owner_id' => $data['property_owner_id'],
                'status' => AgreementStatus::Draft,
                'title' => $data['title'] ?? 'Management agreement',
            ]);

            foreach (array_values($data['clauses']) as $position => $row) {
                $agreement->agreementClauses()->create([
                    'clause_id' => $row['clause_id'],
                    'params' => $row['params'] ?? [],
                    'position' => $position,
                ]);
            }

            $agreement->recomputeRenderedBody();

            return $agreement;
        });
    }
}
