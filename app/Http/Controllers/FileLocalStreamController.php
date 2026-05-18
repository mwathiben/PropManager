<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Storage\TenantDiskResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase-59 SIGNED-URLS-1: local-driver fallback for
 * TenantDiskResolver::temporaryUrl(). On s3 the resolver returns a
 * presigned S3 URL directly; on local the resolver returns a signed
 * Laravel route that lands here.
 *
 * The signed middleware re-validates the URL before the action runs —
 * the request path + query params + the signature must round-trip.
 * Mutating ?path= invalidates the signature, so a recipient can't
 * traverse to a different file with the same token.
 */
class FileLocalStreamController extends Controller
{
    public function stream(Request $request, TenantDiskResolver $resolver): StreamedResponse
    {
        $path = (string) $request->query('path', '');
        $filename = $request->query('filename');
        $disposition = (string) $request->query('disposition', 'attachment');

        if ($path === '') {
            abort(400, 'missing path');
        }

        $disk = $resolver->resolve();

        if (! $disk->exists($path)) {
            abort(404);
        }

        $headers = [];
        if (is_string($filename) && $filename !== '') {
            return $disk->download($path, $filename, $headers);
        }

        return $disk->response($path, null, $headers, $disposition);
    }
}
