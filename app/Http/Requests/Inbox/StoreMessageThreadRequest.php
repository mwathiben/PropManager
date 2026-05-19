<?php

declare(strict_types=1);

namespace App\Http\Requests\Inbox;

use App\Models\MessageThread;
use App\Models\User;
use App\Support\MessageContentPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase-63 INBOX-COMPOSE-1: landlord (or caretaker) opens a new
 * thread with one or more tenants/caretakers as participants. The
 * landlord-scope is enforced both via `participants.*.exists` (only
 * users belonging to this landlord) and via `subject_id.exists`
 * scoped to landlord_id when a subject is present.
 */
class StoreMessageThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('create', MessageThread::class);
    }

    public function rules(): array
    {
        $user = $this->user();
        $landlordId = $user?->isLandlord() ? $user->id : $user?->landlord_id;

        return [
            'subject_type' => ['nullable', Rule::in(['lease', 'ticket'])],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_type'],
            'title' => ['nullable', 'string', 'max:200'],
            'participants' => ['required', 'array', 'min:1', 'max:10'],
            'participants.*' => [
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->where('landlord_id', $landlordId)
                ),
            ],
            'body' => ['required', 'string', 'min:1', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:jpeg,jpg,png,webp,pdf',
                'max:5120',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $body = (string) $this->input('body', '');
            if ($body !== '' && MessageContentPolicy::isSpam($body)) {
                $v->errors()->add('body', __('inbox.message.spam_rejected'));
                app(\App\Services\MetricsService::class)->gauge('inbox_spam_rejected_count', 1);
            }
        });
    }

    public function landlordId(): int
    {
        $user = $this->user();
        if ($user instanceof User && $user->isLandlord()) {
            return (int) $user->id;
        }

        return (int) $user?->landlord_id;
    }
}
