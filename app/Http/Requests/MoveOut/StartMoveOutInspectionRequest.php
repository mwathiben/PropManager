<?php

namespace App\Http\Requests\MoveOut;

use Illuminate\Foundation\Http\FormRequest;

class StartMoveOutInspectionRequest extends FormRequest
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
            'actual_move_out_date' => 'required|date',
        ];
    }
}
