<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase-24 I18N-INFRA-4: validates a locale-switch request. The
 * `locale` must be one of config('app.available_locales') — the
 * single source of truth — so an unsupported value can never be
 * persisted to users.locale or the session.
 */
class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => [
                'required',
                'string',
                Rule::in(array_keys(config('app.available_locales', []))),
            ],
        ];
    }
}
