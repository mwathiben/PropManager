<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use App\Services\Inbox\Scanning\AttachmentScannerInterface;
use App\Services\Inbox\Scanning\ScanResult;
use App\Services\MetricsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Phase-63 INBOX-COMPOSE-2: persist uploaded attachments via
 * Storage::tenant() (Phase 58 SHARED-DISK-MIGRATION) and attach them to
 * a Message via the polymorphic Document model (Phase 45 TICKET-PHOTOS
 * pattern).
 *
 * Phase-67 ATTACHMENT-SCAN-1/2: every file is virus-scanned BEFORE a
 * single byte is persisted, split across two phases the caller invokes
 * around its own transaction:
 *
 *   1. scan() — runs OUTSIDE the request transaction. Scans every file;
 *      an infected file aborts the whole send and the security event is
 *      audited against the sender. Because this runs before the
 *      transaction opens, the audit row survives the rejection (a
 *      ValidationException thrown inside DB::transaction would otherwise
 *      roll the audit write back — losing the forensic record of the
 *      attempted malware upload exactly when it matters).
 *
 *   2. persist() — runs INSIDE the transaction with the new Message. It
 *      stores the (already-scanned) bytes and creates the Document rows,
 *      and is self-cleaning: any storage/DB failure deletes the bytes it
 *      wrote before rethrowing, so a rolled-back batch never orphans
 *      files on the tenant disk.
 */
class MessageAttachmentService
{
    public function __construct(
        private readonly AttachmentScannerInterface $scanner,
        private readonly MetricsService $metrics,
    ) {}

    /**
     * Pass 1 — scan every file before the persistence transaction opens.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{file: UploadedFile, status: string}>
     */
    public function scan(array $files, User $sender, ?int $threadId = null): array
    {
        $landlordId = $this->landlordIdFor($sender);

        $scanned = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $result = $this->scanner->scan((string) $file->getRealPath());

            if ($result->isInfected()) {
                $this->recordInfection($landlordId, $sender, $threadId, $file, $result);

                throw ValidationException::withMessages([
                    'attachments' => __('inbox.scan.blocked'),
                ]);
            }

            if ($result->isError()) {
                $this->metrics->increment('inbox_attachment_scan_error_count', 1, ['landlord_id' => (string) $landlordId]);

                if ((bool) config('inbox.scan.fail_closed', true)) {
                    throw ValidationException::withMessages([
                        'attachments' => __('inbox.scan.unavailable'),
                    ]);
                }
            }

            $scanned[] = [
                'file' => $file,
                'status' => $result->isClean() ? Document::SCAN_CLEAN : Document::SCAN_ERROR,
            ];
        }

        return $scanned;
    }

    /**
     * Pass 2 — persist already-scanned files inside the caller's
     * transaction.
     *
     * @param  array<int, array{file: UploadedFile, status: string}>  $scanned
     * @return array<int, Document>
     */
    public function persist(Message $message, array $scanned): array
    {
        if ($scanned === []) {
            return [];
        }

        $landlordId = (int) ($message->thread?->landlord_id ?? 0);
        $threadId = (int) $message->thread_id;
        $messageId = (int) $message->id;
        $directory = "messages/{$threadId}/{$messageId}";

        $writtenPaths = [];
        $documents = [];

        try {
            foreach ($scanned as $entry) {
                /** @var UploadedFile $file */
                $file = $entry['file'];
                $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
                $filename = (string) Str::ulid().'.'.$extension;
                $path = $directory.'/'.$filename;

                if (Storage::tenant()->putFileAs($directory, $file, $filename) === false) {
                    throw new RuntimeException("Failed to persist inbox attachment {$filename}.");
                }
                $writtenPaths[] = $path;

                $documents[] = Document::create([
                    'landlord_id' => $landlordId,
                    'documentable_id' => $messageId,
                    'documentable_type' => Message::class,
                    'title' => $file->getClientOriginalName(),
                    'file_name' => $filename,
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'document_type' => 'other',
                    'uploaded_by' => $message->sender_id,
                    'scan_status' => $entry['status'],
                ]);
            }
        } catch (Throwable $e) {
            if ($writtenPaths !== []) {
                Storage::tenant()->delete($writtenPaths);
            }

            throw $e;
        }

        $message->update(['message_type' => Message::TYPE_ATTACHMENT]);

        return $documents;
    }

    private function recordInfection(int $landlordId, User $sender, ?int $threadId, UploadedFile $file, ScanResult $result): void
    {
        $this->metrics->increment('inbox_attachment_scan_infected_count', 1, ['landlord_id' => (string) $landlordId]);

        AuditLog::record('inbox.attachment.infected', $sender, [
            'metadata' => [
                'thread_id' => $threadId,
                'file_name' => $file->getClientOriginalName(),
                'signature' => $result->signature,
            ],
        ]);
    }

    private function landlordIdFor(User $sender): int
    {
        if ($sender->isScopeOwner()) {
            return (int) $sender->id;
        }

        return (int) ($sender->landlord_id ?? 0);
    }
}
