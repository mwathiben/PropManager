<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class AddTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        $ticket = $this->route('ticket');

        if (! $user || ! $ticket) {
            return false;
        }

        if ($user->isTenant()) {
            return $ticket->reporter_id === $user->id;
        }

        // PRIV-7: a landlord/caretaker may only comment on their own
        // tenant's ticket. Pre-fix, returning true for any landlord
        // role meant landlord A could comment on landlord B's tickets
        // by guessing the route id.
        if ($user->isScopeOwner() || $user->isCaretaker()) {
            $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

            return (int) $ticket->landlord_id === $landlordId;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'comment' => 'required|string|max:2000',
            'is_internal' => 'boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (auth()->user()->isTenant()) {
            $this->merge(['is_internal' => false]);
        }
    }
}
