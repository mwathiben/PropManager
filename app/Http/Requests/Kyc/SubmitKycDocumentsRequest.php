<?php

namespace App\Http\Requests\Kyc;

use App\Models\KycRequirement;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class SubmitKycDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTenant() ?? false;
    }

    public function rules(): array
    {
        return [
            'submissions' => ['required', 'array', 'min:1'],
            'submissions.*.requirement_id' => ['required', 'integer', 'exists:kyc_requirements,id'],
            'submissions.*.file' => [
                'nullable',
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'gif'])
                    ->max(10 * 1024), // 10MB
            ],
            'submissions.*.value' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'submissions.required' => 'Please provide at least one document submission.',
            'submissions.*.requirement_id.exists' => 'Invalid requirement selected.',
            'submissions.*.file.max' => 'Each file must not exceed 10MB.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->validateRequiredDocuments($validator);
                $this->validateSubmissionContent($validator);
            },
        ];
    }

    private function validateRequiredDocuments(Validator $validator): void
    {
        $user = $this->user();
        if (! $user) {
            return;
        }

        $requiredIds = $this->getRequiredRequirementIds($user);
        $submittedIds = collect($this->submissions ?? [])
            ->pluck('requirement_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        foreach ($requiredIds as $reqId) {
            if (! in_array($reqId, $submittedIds)) {
                $validator->errors()->add(
                    "submissions.{$reqId}",
                    'This required document is missing.'
                );
            }
        }
    }

    private function validateSubmissionContent(Validator $validator): void
    {
        foreach ($this->submissions ?? [] as $index => $submission) {
            $hasFile = ! empty($submission['file']);
            $hasValue = ! empty($submission['value']);

            if (! $hasFile && ! $hasValue) {
                $validator->errors()->add(
                    "submissions.{$index}",
                    'Each submission must have a file or value.'
                );
            }
        }
    }

    private function getRequiredRequirementIds(User $user): array
    {
        $lease = $user->lease;
        $buildingId = $lease?->unit?->building_id;
        $landlordId = $user->landlord_id;

        return KycRequirement::withoutGlobalScope('landlord')
            ->where(function ($query) use ($landlordId) {
                $query->where('landlord_id', $landlordId)
                    ->orWhereNull('landlord_id');
            })
            ->where(function ($query) use ($buildingId) {
                $query->where('building_id', $buildingId)
                    ->orWhereNull('building_id');
            })
            ->active()
            ->required()
            ->pluck('id')
            ->toArray();
    }
}
