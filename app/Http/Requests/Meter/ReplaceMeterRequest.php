<?php

declare(strict_types=1);

namespace App\Http\Requests\Meter;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The meter is route-bound; the controller authorizes 'replace' against it.
        return $this->user()->isScopeOwner();
    }

    public function rules(): array
    {
        return [
            'old_final_reading' => ['required', 'numeric', 'min:0'],
            'new_serial' => ['nullable', 'string', 'max:255'],
            'new_initial_reading' => ['required', 'numeric', 'min:0'],
            'reading_date' => ['nullable', 'date'],
        ];
    }
}
