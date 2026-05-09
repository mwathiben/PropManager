<?php

namespace App\Http\Requests\MoveOut;

use Illuminate\Foundation\Http\FormRequest;

class StoreMoveOutRequest extends FormRequest
{
    // VALID-6: route-model ownership check on the parent lease.
    public function authorize(): bool
    {
        $user = $this->user();
        $lease = $this->route('lease');

        if (! $user || ! $lease) {
            return false;
        }

        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $lease->landlord_id === $landlordId;
    }

    public function rules(): array
    {
        return [
            'notice_date' => 'required|date',
            'intended_move_out_date' => 'required|date|after_or_equal:notice_date',
            'reason' => 'nullable|string|max:500',
        ];
    }
}
