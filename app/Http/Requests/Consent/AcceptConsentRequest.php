<?php

declare(strict_types=1);

namespace App\Http\Requests\Consent;

use Illuminate\Foundation\Http\FormRequest;

class AcceptConsentRequest extends FormRequest
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
        return [
            'consents' => ['required', 'array'],
            // VALID-11: enforce the type:version contract at the validator. Without the regex,
            // a crafted string would destructure via explode() into null/garbage.
            'consents.*' => [
                'required',
                'string',
                'regex:/^(terms|privacy|marketing|data_processing|third_party_sharing):\d+\.\d+$/',
            ],
        ];
    }
}
