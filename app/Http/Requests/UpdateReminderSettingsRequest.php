<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'reminder_days_before_due' => 'required|integer|min:1|max:30',
            'overdue_reminder_frequency' => 'required|string|in:daily,weekly,none',
            'reminder_channels' => 'required|array',
            'reminder_channels.*' => 'string|in:email,sms,push',
        ];
    }

    public function messages(): array
    {
        return [
            'reminder_days_before_due.required' => 'Days before due is required.',
            'reminder_days_before_due.integer' => 'Days must be a whole number.',
            'reminder_days_before_due.min' => 'Days must be at least 1.',
            'reminder_days_before_due.max' => 'Days cannot exceed 30.',
            'overdue_reminder_frequency.required' => 'Reminder frequency is required.',
            'overdue_reminder_frequency.in' => 'Invalid reminder frequency.',
            'reminder_channels.required' => 'Please select at least one reminder channel.',
            'reminder_channels.array' => 'Invalid channels format.',
            'reminder_channels.*.in' => 'Invalid reminder channel selected.',
        ];
    }
}
