<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait WithETag
{
    protected function jsonWithCache(
        array $data,
        int $maxAge = 60,
        int $staleWhileRevalidate = 300
    ): JsonResponse {
        $content = json_encode($data);
        $etag = '"'.md5($content).'"';

        $request = request();
        $ifNoneMatch = $request->header('If-None-Match');

        if ($ifNoneMatch === $etag) {
            return response()->json(null, 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', "private, max-age={$maxAge}, stale-while-revalidate={$staleWhileRevalidate}");
        }

        return response()->json($data)
            ->header('ETag', $etag)
            ->header('Cache-Control', "private, max-age={$maxAge}, stale-while-revalidate={$staleWhileRevalidate}");
    }
}
