<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTicketRequest extends FormRequest
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

        if ($user->isTenant()) {
            return (int) $ticket->reporter_id === (int) $user->id && $ticket->canBeEdited();
        }

        if ($user->isScopeOwner()) {
            return (int) $ticket->landlord_id === (int) $user->id;
        }

        if ($user->isCaretaker()) {
            return (int) $ticket->landlord_id === (int) $user->landlord_id;
        }

        return false;
    }

    public function rules(): array
    {
        $user = Auth::user();

        if ($user && $user->isTenant()) {
            return [
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:2000',
                'location' => 'nullable|string|max:255',
                'priority' => 'required|in:low,medium,high,urgent',
            ];
        }

        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:2000',
            'location' => 'nullable|string|max:255',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:open,acknowledged,in_progress,resolved,closed,cancelled',
            'resolution_notes' => 'nullable|string|max:2000',
        ];
    }
}
