<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FinalizeDocumensoSignatureJob;
use App\Models\AgreementSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Slice-2 PR-2.4b: the authoritative trigger for the Documenso integrity layer.
 *
 * Documenso fires DOCUMENT_COMPLETED only after the owner finishes signing in
 * the embedded widget and the PDF is PKCS#12-sealed. The postMessage from the
 * iframe is UX-only (targetOrigin '*', spoofable); THIS webhook is what seals
 * the evidence and activates the fee — handed to a queued job so the response
 * is fast and the download+activation is retriable. Unauthenticated (the
 * shared-secret middleware is the gate), so it runs without tenant scope and
 * matches the signature across all tenants by document id.
 */
class DocumensoWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if ((string) $request->input('event') !== 'DOCUMENT_COMPLETED') {
            return response()->json(['status' => 'ignored']);
        }

        $documentId = $this->resolveDocumentId($request);
        if ($documentId <= 0) {
            // A completion we cannot correlate (missing or malformed id, e.g. "42abc")
            // is abnormal — never drop it silently.
            Log::warning('Documenso completion event with missing or malformed document id', ['payload' => $request->input('payload')]);

            return response()->json(['status' => 'ignored']);
        }

        $signature = AgreementSignature::query()
            ->where('documenso_document_id', $documentId)
            ->first();

        if ($signature === null) {
            Log::warning('Documenso webhook: no signature matches document', ['document_id' => $documentId]);

            return response()->json(['status' => 'unknown']);
        }

        if ($signature->status->isTerminal()) {
            // Signed or declined — a late/duplicate completion must not re-process it.
            return response()->json(['status' => 'already_resolved']);
        }

        $envelopeId = $request->input('payload.envelopeId');

        FinalizeDocumensoSignatureJob::dispatch(
            $signature->id,
            $documentId,
            is_string($envelopeId) ? $envelopeId : null,
        );

        return response()->json(['status' => 'accepted']);
    }

    /** Coerce payload.id to a positive int, rejecting malformed values like "42abc" (returns 0). */
    private function resolveDocumentId(Request $request): int
    {
        $raw = $request->input('payload.id');

        return is_numeric($raw) ? (int) $raw : 0;
    }
}
