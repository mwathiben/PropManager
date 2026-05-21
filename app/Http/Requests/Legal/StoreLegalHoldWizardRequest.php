<?php

declare(strict_types=1);

namespace App\Http\Requests\Legal;

use App\Models\LegalHold;
use App\Support\LegalHoldRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase-72 WIZARD-FLOW: validates the guided create-hold submission. Authorizes
 * via LegalHoldPolicy::create (landlord); subject ownership is re-checked at
 * write time by BulkHoldService::validateOwnership, so the suggest payload is
 * never trusted.
 */
class StoreLegalHoldWizardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', LegalHold::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'matter_reference' => ['nullable', 'string', 'max:255'],
            'situation' => ['nullable', 'string', Rule::in(array_keys((array) config('legal_hold.situations', [])))],
            'review_by' => ['nullable', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*' => ['array'],
            'subjects.*.*' => ['integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $subjects = (array) $this->input('subjects', []);

            $total = 0;
            foreach ($subjects as $type => $ids) {
                if (! in_array($type, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES, true)) {
                    $v->errors()->add('subjects', __('legal_holds.unsupported_holdable_type'));

                    continue;
                }
                $total += count((array) $ids);
            }

            if ($total === 0) {
                $v->errors()->add('subjects', __('legal_holds.wizard.no_subjects_selected'));
            }
        });
    }
}
