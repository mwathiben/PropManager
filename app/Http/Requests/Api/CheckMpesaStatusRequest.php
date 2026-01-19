<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CheckMpesaStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->role === 'tenant';
    }

    public function rules(): array
    {
        return [
            'checkout_request_id' => 'required|string',
        ];
    }
}
