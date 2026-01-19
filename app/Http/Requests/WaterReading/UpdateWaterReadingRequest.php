<?php

namespace App\Http\Requests\WaterReading;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateWaterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->isLandlord() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'current_reading' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
        ];
    }
}
