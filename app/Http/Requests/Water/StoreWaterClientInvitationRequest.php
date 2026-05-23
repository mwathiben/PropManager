<?php

declare(strict_types=1);

namespace App\Http\Requests\Water;

use Illuminate\Foundation\Http\FormRequest;

class StoreWaterClientInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Connection ownership is enforced in the controller (route-bound model).
        return $this->user()?->isLandlord() ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }
}
