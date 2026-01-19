<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class AddTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        $ticket = $this->route('ticket');

        if ($user->isTenant()) {
            return $ticket->reporter_id === $user->id;
        }

        return $user->isLandlord() || $user->isCaretaker();
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
