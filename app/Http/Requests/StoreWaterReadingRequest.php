<?php

namespace App\Http\Requests;

use App\Rules\SecureFile;
use Illuminate\Foundation\Http\FormRequest;

class StoreWaterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'readings' => 'required|array|min:1',
            'readings.*.unit_id' => 'required|exists:units,id',
            'readings.*.current_reading' => 'required|numeric|min:0',
            'readings.*.reading_date' => 'required|date',
            'readings.*.photo' => ['required', 'file', SecureFile::image(5)],
        ];
    }

    public function messages(): array
    {
        return [
            'readings.required' => 'Please provide at least one reading.',
            'readings.*.unit_id.required' => 'Unit is required for each reading.',
            'readings.*.unit_id.exists' => 'The selected unit does not exist.',
            'readings.*.current_reading.required' => 'Current reading is required.',
            'readings.*.current_reading.min' => 'Reading cannot be negative.',
            'readings.*.reading_date.required' => 'Reading date is required.',
            'readings.*.photo.required' => 'Photo evidence is required for each reading.',
            'readings.*.photo.image' => 'Photo must be an image file.',
            'readings.*.photo.max' => 'Photo cannot exceed 5MB.',
        ];
    }
}
