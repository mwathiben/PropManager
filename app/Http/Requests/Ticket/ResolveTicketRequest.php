<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ResolveTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $ticket = $this->route('ticket');
        if (! $ticket) {
            return false;
        }

        if ($user->isLandlord()) {
            return (int) $ticket->landlord_id === (int) $user->id;
        }

        if ($user->isCaretaker()) {
            return (int) $ticket->landlord_id === (int) $user->landlord_id;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'resolution_notes' => 'nullable|string|max:2000',
        ];
    }
}
