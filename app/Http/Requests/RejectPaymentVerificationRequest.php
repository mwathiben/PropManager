<?php

namespace App\Http\Requests;

use App\Models\TenantPaymentVerification;
use Illuminate\Foundation\Http\FormRequest;

class RejectPaymentVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $verification = $this->route('verification');

        if (! $verification instanceof TenantPaymentVerification) {
            return false;
        }

        return $this->user()?->can('reject', $verification) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for rejection.',
        ];
    }
}
