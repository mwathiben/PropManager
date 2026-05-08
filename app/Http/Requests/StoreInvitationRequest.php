<?php

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = Property::find($this->input('property_id'));

        if (! $property) {
            return false;
        }

        return $this->user()?->can('create', [Invitation::class, $property]) ?? false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'property_id' => ['required', 'exists:properties,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please provide an email address.',
            'email.email' => 'Please provide a valid email address.',
            'property_id.required' => 'Please select a property.',
            'property_id.exists' => 'The selected property does not exist.',
        ];
    }
}
