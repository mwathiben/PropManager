<?php

namespace App\Http\Requests\MoveOut;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMoveOutInspectionRequest extends FormRequest
{
    // VALID-6: route-model ownership check.
    public function authorize(): bool
    {
        $user = $this->user();
        $moveOut = $this->route('moveOut');

        if (! $user || ! $moveOut) {
            return false;
        }

        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $moveOut->landlord_id === $landlordId;
    }

    public function rules(): array
    {
        return [
            'inspection_notes' => 'nullable|string|max:2000',
        ];
    }
}
