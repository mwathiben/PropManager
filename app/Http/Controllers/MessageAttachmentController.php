<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\FileAccessAudit;
use App\Models\Message;
use App\Models\MessageThread;
use App\Services\Storage\FileAccessRecorder;
use App\Services\Storage\TenantDiskResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase-71 MEDIA-CI: serves an inbox message attachment, authorised by THREAD
 * PARTICIPATION (not DocumentPolicy, which denies tenants Message-attached
 * documents). Shared by the landlord + tenant route groups. Only clean,
 * existing files of the named message-in-thread are served; everything else
 * 404s. Redirects to a short-lived signed URL (Phase-59 resolver).
 */
class MessageAttachmentController extends Controller
{
    public function show(
        Request $request,
        MessageThread $thread,
        Message $message,
        Document $document,
    ): RedirectResponse {
        $this->authorize('view', $thread);

        abort_unless($message->thread_id === $thread->id, 404);
        abort_unless(
            $document->documentable_type === Message::class
                && (int) $document->documentable_id === (int) $message->id,
            404,
        );
        // Never serve an un-scanned / non-clean attachment.
        abort_unless($document->scan_status === 'clean', 404);
        abort_unless($document->fileExists(), 404);

        app(FileAccessRecorder::class)->record(
            $request->user(),
            $document,
            FileAccessAudit::ACTION_DOWNLOAD,
            $request,
            $document->file_path,
        );

        $disposition = $document->isImage() ? 'inline' : 'attachment';

        return redirect()->away(
            app(TenantDiskResolver::class)->temporaryUrl(
                $document->file_path,
                $document->landlord_id,
                5,
                $this->safeName($document->file_name, (string) $document->file_path),
                $disposition,
            ),
        );
    }

    /**
     * Strip CR/LF/control bytes from the user-supplied filename before it
     * reaches the Content-Disposition header on the signed URL.
     */
    private function safeName(?string $original, string $storedPath): string
    {
        $base = trim((string) $original);
        $base = preg_replace('/[\x00-\x1F\x7F"\\\\;]+/u', '', $base) ?? '';
        $base = str_replace(['/', '\\'], '-', trim($base));

        if ($base === '' || $base === '.' || $base === '..') {
            $ext = pathinfo($storedPath, PATHINFO_EXTENSION);

            return 'attachment'.($ext ? '.'.$ext : '');
        }

        return mb_substr($base, 0, 200);
    }
}
