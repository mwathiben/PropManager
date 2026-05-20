<?php

declare(strict_types=1);

namespace App\Http\Requests\Inbox;

use App\Models\Message;
use App\Models\MessageThread;
use App\Support\MessageContentPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase-63 INBOX-COMPOSE-1: post a reply into an existing open thread.
 * Authorization is delegated to MessagePolicy::create which in turn
 * checks the thread is open AND the user is in the participants pivot.
 */
class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');
        $user = $this->user();

        if ($thread === null || $user === null) {
            return false;
        }

        return $user->can('create', [Message::class, $thread]);
    }

    public function rules(): array
    {
        $thread = $this->route('thread');
        $threadId = $thread instanceof MessageThread ? $thread->id : 0;

        return [
            'body' => ['required', 'string', 'min:1', 'max:4000'],
            // A reply may only quote a (non-deleted) message in THIS thread —
            // never another thread's or tenant's message.
            'reply_to_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where(
                    fn ($query) => $query->where('thread_id', $threadId)->whereNull('deleted_at'),
                ),
            ],
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
}
