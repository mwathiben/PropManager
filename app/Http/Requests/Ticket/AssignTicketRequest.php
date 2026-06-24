<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner();
    }

    public function rules(): array
    {
        return [
            'assigned_to' => 'required|exists:users,id',
        ];
    }
}
