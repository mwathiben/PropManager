<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Document;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Phase-75 PHOTO-ROLLUP: a landlord-wide gallery of maintenance-ticket photos
 * (Document rows morphed to Ticket), grouping each original with its Phase-45
 * annotation siblings. Filterable by building / unit / category / date. Image
 * bytes are never served here — the grid links to the Phase-59 signed-URL
 * `documents.view` route; the PDF export embeds capped base64 thumbnails.
 */
class MaintenancePhotoGalleryController extends Controller
{
    use WithLandlordScope;

    private const PER_PAGE = 48;

    private const PDF_MAX = 60;

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        $photos = $this->filtered($landlordId, $request)
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Document $doc) => $this->present($doc));

        return Inertia::render('Maintenance/PhotoGallery', [
            'photos' => $photos,
            'buildings' => $this->getBuildings($landlordId),
            'categories' => array_keys(Ticket::issueSubcategories() + Ticket::complaintSubcategories()),
            'filters' => [
                'building_id' => $request->integer('building_id') ?: null,
                'unit_id' => $request->integer('unit_id') ?: null,
                'category' => $request->string('category')->toString() ?: null,
                'from' => $request->date('from')?->toDateString(),
                'to' => $request->date('to')?->toDateString(),
            ],
        ]);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        $landlordId = $this->getLandlordId();
        $user = $request->user();

        $docs = $this->filtered($landlordId, $request)
            ->limit(self::PDF_MAX)
            ->get();

        $images = $docs
            ->map(function (Document $doc) use ($landlordId) {
                $uri = $this->dataUri($doc, $landlordId);
                if ($uri === null) {
                    return null;
                }
                $ticket = $doc->documentable;

                return [
                    'data_uri' => $uri,
                    'ticket_ref' => $ticket ? '#'.$ticket->id.' — '.$ticket->title : '—',
                    'building' => $ticket?->building?->name,
                    'unit' => $ticket?->unit?->unit_number,
                    'category' => $ticket?->subcategory,
                    'date' => $doc->created_at?->format('M j, Y'),
                    'annotation_count' => $doc->annotations->count(),
                ];
            })
            ->filter()
            ->values();

        $pdf = Pdf::loadView('pdf.maintenance-photos', [
            'images' => $images,
            'landlord' => $user->isScopeOwner() ? $user : $user->landlord,
            'generated_at' => now()->format('F j, Y g:i A'),
        ]);

        return $pdf->download('maintenance-photos_'.now()->format('Y_m_d').'.pdf');
    }

    /**
     * Landlord-scoped originals (annotation siblings are loaded as a relation,
     * not as top-level rows) filtered by the request. NO N+1: documentable +
     * its building/unit and the annotation siblings are eager-loaded.
     *
     * @return Builder<Document>
     */
    private function filtered(int $landlordId, Request $request): Builder
    {
        return Document::query()
            ->where('documents.landlord_id', $landlordId)
            ->where('documentable_type', Ticket::class)
            ->where('mime_type', 'like', 'image/%')
            ->whereNull('annotates_document_id')
            ->whereHasMorph('documentable', [Ticket::class], function (Builder $ticket) use ($request) {
                $ticket
                    ->when($request->filled('building_id'), fn ($q) => $q->where('building_id', $request->integer('building_id')))
                    ->when($request->filled('unit_id'), fn ($q) => $q->where('unit_id', $request->integer('unit_id')))
                    ->when($request->filled('category'), fn ($q) => $q->where('subcategory', $request->string('category')->toString()));
            })
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('documents.created_at', '>=', $from))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('documents.created_at', '<=', $to))
            ->with([
                'documentable' => fn (MorphTo $morph) => $morph->morphWith([
                    Ticket::class => ['building:id,name', 'unit:id,unit_number'],
                ]),
                'annotations:id,annotates_document_id,file_name,created_at',
            ])
            ->orderByDesc('documents.created_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Document $doc): array
    {
        $ticket = $doc->documentable;

        return [
            'id' => $doc->id,
            'url' => route('documents.view', $doc->id),
            'file_name' => $doc->file_name,
            'created_at' => $doc->created_at?->toDateString(),
            'ticket' => $ticket ? [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'category' => $ticket->subcategory,
                'building' => $ticket->building?->name,
                'unit' => $ticket->unit?->unit_number,
            ] : null,
            'annotations' => $doc->annotations
                ->map(fn (Document $a) => [
                    'id' => $a->id,
                    'url' => route('documents.view', $a->id),
                ])
                ->values(),
        ];
    }

    private function dataUri(Document $doc, int $landlordId): ?string
    {
        $disk = Storage::tenant($landlordId);

        if (! $disk->exists($doc->file_path)) {
            return null;
        }

        return 'data:'.$doc->mime_type.';base64,'.base64_encode($disk->get($doc->file_path));
    }
}
