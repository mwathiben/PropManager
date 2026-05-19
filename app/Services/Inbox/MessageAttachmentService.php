<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Models\Document;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase-63 INBOX-COMPOSE-2: persist uploaded attachments via
 * Storage::tenant() (Phase 58 SHARED-DISK-MIGRATION) and attach them
 * to a Message via the polymorphic Document model (Phase 45
 * TICKET-PHOTOS pattern).
 *
 * Path template: messages/{thread_id}/{message_id}/{ulid}.{ext} — the
 * PrefixedDisk decorator from Phase 59 TENANT-ROUTING prepends the
 * per-landlord prefix when enabled.
 */
class MessageAttachmentService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, Document>
     */
    public function attachToMessage(Message $message, array $files): array
    {
        $documents = [];
        $threadId = (int) $message->thread_id;
        $messageId = (int) $message->id;
        $landlordId = (int) ($message->thread?->landlord_id ?? 0);
        $uploaderId = $message->sender_id;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $ulid = (string) Str::ulid();
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filename = $ulid.'.'.$extension;
            $directory = "messages/{$threadId}/{$messageId}";

            Storage::tenant()->putFileAs($directory, $file, $filename);

            $documents[] = Document::create([
                'landlord_id' => $landlordId,
                'documentable_id' => $messageId,
                'documentable_type' => Message::class,
                'title' => $file->getClientOriginalName(),
                'file_name' => $filename,
                'file_path' => $directory.'/'.$filename,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'document_type' => 'other',
                'uploaded_by' => $uploaderId,
            ]);
        }

        if ($documents !== []) {
            $message->update(['message_type' => Message::TYPE_ATTACHMENT]);
        }

        return $documents;
    }
}
