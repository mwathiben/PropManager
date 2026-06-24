<?php

declare(strict_types=1);

namespace App\Http\Requests\Legal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase-72 HOLD-SETTINGS: validates a landlord's legal-hold preferences within
 * sane bounds. Authorization is the landlord-only route middleware; a landlord
 * can only ever edit their own row (keyed on the authed user in the controller).
 */
class UpdateHoldSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isScopeOwner();
    }

    public function rules(): array
    {
        return [
            'stale_after_days' => ['nullable', 'integer', 'min:30', 'max:3650'],
            'reminder_cooldown_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'matter_reference_format' => ['nullable', 'string', 'max:100'],
            'reminder_recipients' => ['nullable', 'array', 'max:10'],
            'reminder_recipients.*' => ['email', 'max:255'],
            'auto_hold_on_eviction' => ['boolean'],
        ];
    }
}
