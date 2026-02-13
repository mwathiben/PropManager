<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDefaultCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isLandlord() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'default_currency' => ['required', 'string', Rule::in(Currency::values())],
        ];
    }
}
