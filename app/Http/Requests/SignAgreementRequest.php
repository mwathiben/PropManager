<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Slice-2 PR-2.3c: the owner's signing submission. Public + token-gated (the
 * controller validates the token), so authorize() is open; the OTP is the
 * identity factor and content_hash binds the submission to the reviewed snapshot.
 */
class SignAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
            'content_hash' => ['required', 'string'],
            'agree' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agree.accepted' => __('agreements.sign.errors.must_agree'),
        ];
    }
}
