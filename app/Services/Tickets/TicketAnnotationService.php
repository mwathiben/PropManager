<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Models\Document;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Phase-45 TICKET-PHOTOS-1/2: persist an annotated PNG copy of a
 * maintenance-ticket photo attachment alongside the original Document.
 * The annotated copy is a sibling Document row with annotates_document_id
 * pointing back to the original; annotation_data carries the canvas
 * scene JSON so the editor can hydrate for further edits.
 *
 * Authorisation lives in the controller (tenant must own the ticket,
 * landlord must own the ticket's landlord_id); this service trusts the
 * caller and runs the persist transaction.
 */
class TicketAnnotationService
{
    /**
     * @param  array<string, mixed>  $annotationData  Canvas scene JSON (shape opaque to the service)
     */
    public function storeAnnotation(
        Ticket $ticket,
        Document $original,
        string $annotatedImageBase64,
        array $annotationData,
        User $actor,
    ): Document {
        $payload = $this->decodeBase64Png($annotatedImageBase64);

        return DB::transaction(function () use ($ticket, $original, $payload, $annotationData, $actor): Document {
            $path = 'tickets/'.$ticket->id.'/annotation-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.png';
            Storage::tenant()->put($path, $payload);

            $annotated = $ticket->attachments()->create([
                'landlord_id' => $ticket->landlord_id,
                'annotates_document_id' => $original->id,
                'annotation_data' => $annotationData,
                'title' => 'Annotated — '.$original->title,
                'file_name' => 'annotation-'.$original->file_name,
                'file_path' => $path,
                'mime_type' => 'image/png',
                'file_size' => strlen($payload),
                'document_type' => $original->document_type,
                'uploaded_by' => $actor->id,
            ]);

            return $annotated;
        });
    }

    private function decodeBase64Png(string $input): string
    {
        // Accept either a bare base64 string or a data URL like
        // 'data:image/png;base64,iVBORw0KGgo...' (the canvas.toDataURL()
        // output the client sends).
        if (str_starts_with($input, 'data:')) {
            $comma = strpos($input, ',');
            if ($comma === false) {
                throw new \InvalidArgumentException('Invalid data URL for annotation upload.');
            }
            $input = substr($input, $comma + 1);
        }

        $decoded = base64_decode($input, true);
        if ($decoded === false || strlen($decoded) < 16) {
            throw new \InvalidArgumentException('Annotation image could not be decoded.');
        }

        return $decoded;
    }
}
