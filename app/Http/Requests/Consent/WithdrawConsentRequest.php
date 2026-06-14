<?php

declare(strict_types=1);

namespace App\Http\Requests\Consent;

use App\Http\Controllers\ConsentController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Mandatory consents (terms/privacy) are intentionally excluded — withdrawing those is
        // account-blocking, so the user must use account deletion instead.
        return [
            'type' => ['required', 'string', Rule::in(ConsentController::WITHDRAWABLE_CONSENT_TYPES)],
        ];
    }
}
