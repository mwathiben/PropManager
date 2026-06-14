<?php

declare(strict_types=1);

namespace App\Http\Requests\Consent;

use App\Http\Controllers\ConsentController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObjectToProcessingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Only legitimate_interests-grounded operations are objectable; contract /
        // legal_obligation operations cannot be unilaterally paused by the data subject.
        return [
            'category' => ['required', 'string', Rule::in(ConsentController::OBJECTABLE_CATEGORIES)],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
