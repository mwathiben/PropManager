<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('building'));
    }

    public function rules(): array
    {
        return [
            'auto_generate_invoices' => 'boolean',
            'invoice_generation_day' => ['required_if:auto_generate_invoices,1', 'required_if:auto_generate_invoices,true', 'nullable', 'integer', 'min:1', 'max:28'],
            'auto_send_invoices' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_generation_day.required_if' => 'Invoice generation day is required when automation is enabled.',
            'invoice_generation_day.max' => 'Invoice generation day must be between 1 and 28.',
        ];
    }
}
