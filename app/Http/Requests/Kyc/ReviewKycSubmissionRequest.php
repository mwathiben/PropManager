<?php

namespace App\Http\Requests\Kyc;

use App\Enums\KycSubmissionStatus;
use App\Models\TenantKycSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewKycSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        if (! $submission instanceof TenantKycSubmission) {
            return false;
        }

        return $this->user()?->can('review', $submission) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    KycSubmissionStatus::Approved->value,
                    KycSubmissionStatus::Rejected->value,
                ]),
            ],
            'rejection_reason' => [
                'nullable',
                'required_if:status,'.KycSubmissionStatus::Rejected->value,
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Please select a review decision.',
            'status.in' => 'Invalid review decision.',
            'rejection_reason.required_if' => 'Please provide a reason for rejection.',
        ];
    }
}
