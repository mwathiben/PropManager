<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UnsubscribePushRequest extends FormRequest
{
    // VALID-6: push unsubscribe is tied to the authenticated user only.
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'endpoint' => 'required|string',
        ];
    }
}
