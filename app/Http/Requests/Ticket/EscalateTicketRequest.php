<?php

declare(strict_types=1);

namespace App\Http\Requests\Ticket;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase-80 ESCALATION-2/3: a caretaker may escalate only a ticket assigned to
 * THEM (not just any ticket under their landlord). Reason is required, with an
 * optional preset from config.
 */
class EscalateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $ticket = $this->route('ticket');

        return $user !== null
            && $user->isCaretaker()
            && $ticket instanceof Ticket
            && (int) $ticket->assigned_to === (int) $user->id;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'preset' => ['nullable', 'string', Rule::in(array_keys((array) config('maintenance.escalation_reasons', [])))],
        ];
    }
}
