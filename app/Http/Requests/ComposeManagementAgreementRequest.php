<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClauseType;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Slice-2 PR-2.2: validate + authorize composing a draft management agreement.
 * property_owner_id is scoped to the acting manager (the cross-tenant write
 * guard the review flagged); the after() hook rejects two clauses of the same
 * exclusive binding so the model's integrity guard surfaces as a 422, not a 500.
 */
class ComposeManagementAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ManagementAgreement::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'property_owner_id' => [
                'required',
                Rule::exists('property_owners', 'id')->where('landlord_id', $this->user()->id),
            ],
            'clauses' => ['required', 'array', 'min:1'],
            'clauses.*.clause_id' => [
                'required',
                Rule::exists('clauses', 'id')
                    ->where('is_active', true)
                    ->where('type', ClauseType::Management->value),
            ],
            'clauses.*.params' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $clauseIds = collect($this->input('clauses', []))
                ->pluck('clause_id')
                ->filter()
                ->all();

            if ($clauseIds === []) {
                return;
            }

            $duplicateBindings = Clause::query()
                ->whereIn('id', $clauseIds)
                ->where('is_exclusive', true)
                ->get()
                ->groupBy(fn (Clause $clause): string => $clause->binding->value)
                ->filter(fn ($group): bool => $group->count() > 1)
                ->keys();

            foreach ($duplicateBindings as $binding) {
                $validator->errors()->add('clauses', "An agreement may hold only one {$binding} clause.");
            }
        });
    }
}
