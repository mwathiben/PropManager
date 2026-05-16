<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'building_id' => 'required|exists:buildings,id',
            'unit_id' => 'nullable|exists:units,id',
            'category' => 'required|in:issue,complaint',
            'subcategory' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'location' => 'nullable|string|max:255',
            'priority' => 'required|in:low,medium,high,urgent',
            // Phase-28 TENANT-MAINT-2: up to 5 photos × 5MB each.
            'photos' => 'sometimes|array|max:5',
            'photos.*' => 'image|mimes:jpeg,png,webp|max:5120',
        ];
    }
}
