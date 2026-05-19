<?php

declare(strict_types=1);

namespace App\Http\Requests\Inbox;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'body' => ['required', 'string', 'min:1', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:jpeg,jpg,png,webp,pdf',
                'max:5120',
            ],
        ];
    }
}
