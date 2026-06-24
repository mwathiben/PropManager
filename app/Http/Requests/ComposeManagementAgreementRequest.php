<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClauseBinding;
use App\Enums\ClauseType;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use App\Services\ManagementFee\FeeClauseParams;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Slice-2 PR-2.2: validate + authorize composing a draft management agreement.
 * property_owner_id is scoped to the acting manager (the cross-tenant write
 * guard the review flagged). The after() hook turns every domain invariant the
 * model would otherwise throw on into a 422: a duplicate exclusive binding, an
 * invalid fee (via FeeClauseParams, the single source of truth), and any clause
 * whose declared template fields are left blank — so no agreement is ever hashed
 * with a malformed fee or a literal {placeholder} in its signed body.
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
            $rows = collect($this->input('clauses', []));
            $clauseIds = $rows->pluck('clause_id')->filter()->all();

            if ($clauseIds === []) {
                return;
            }

            $clauses = Clause::query()->whereIn('id', $clauseIds)->get()->keyBy('id');

            $this->assertNoDuplicateExclusiveBinding($rows, $clauses, $validator);
            $this->assertClauseParamsAreComplete($rows, $clauses, $validator);
        });
    }

    /**
     * Mirror AgreementClause's creating guard so a duplicate exclusive binding
     * fails here as a 422 rather than as the model's backstop exception.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, Clause>  $clauses
     */
    private function assertNoDuplicateExclusiveBinding(Collection $rows, Collection $clauses, Validator $validator): void
    {
        $rows->map(fn (array $row): ?Clause => $clauses->get($row['clause_id'] ?? null))
            ->filter()
            ->groupBy(fn (Clause $clause): string => $clause->binding->value)
            ->filter(fn (Collection $group): bool => $group->count() > 1 && $group->contains->is_exclusive)
            ->each(fn () => $validator->errors()->add('clauses', __('agreements.errors.duplicate_binding')));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, Clause>  $clauses
     */
    private function assertClauseParamsAreComplete(Collection $rows, Collection $clauses, Validator $validator): void
    {
        foreach ($rows as $i => $row) {
            $clause = $clauses->get($row['clause_id'] ?? null);
            if ($clause === null) {
                continue;
            }

            $params = is_array($row['params'] ?? null) ? $row['params'] : [];

            if ($clause->binding === ClauseBinding::ManagementFee) {
                try {
                    FeeClauseParams::fromParams($params);
                } catch (InvalidArgumentException) {
                    $validator->errors()->add("clauses.{$i}.params", __('agreements.errors.invalid_fee'));
                }

                continue;
            }

            $this->assertSchemaFieldsFilled($clause, $params, "clauses.{$i}.params", $validator);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function assertSchemaFieldsFilled(Clause $clause, array $params, string $key, Validator $validator): void
    {
        foreach ($clause->params_schema ?? [] as $field) {
            $this->validateSchemaField($field, $params, $key, $validator);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $params
     */
    private function validateSchemaField(array $field, array $params, string $key, Validator $validator): void
    {
        $name = $field['name'] ?? null;
        if ($name === null) {
            return;
        }

        $value = $params[$name] ?? null;
        if ($value === null || $value === '') {
            $validator->errors()->add("{$key}.{$name}", __('agreements.errors.missing_param', ['field' => $name]));

            return;
        }

        if (! empty($field['options']) && ! in_array($value, $field['options'], true)) {
            $validator->errors()->add("{$key}.{$name}", __('agreements.errors.invalid_option', ['field' => $name]));
        }
    }
}
