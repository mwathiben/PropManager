<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        $ticket = $this->route('ticket');

        if ($user->isTenant()) {
            return $ticket->reporter_id === $user->id && $ticket->canBeEdited();
        }

        return $user->isLandlord() || $user->isCaretaker();
    }

    public function rules(): array
    {
        $user = auth()->user();

        if ($user->isTenant()) {
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
