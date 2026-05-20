<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Inbox\MessageSearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-67 MESSAGE-SEARCH-2: tenant message search, scoped by
 * MessageSearchService to the caller's own threads.
 */
class InboxSearchController extends Controller
{
    public function index(Request $request, MessageSearchService $search): Response
    {
        $term = (string) $request->query('q', '');

        return Inertia::render('Tenant/Inbox/Search', [
            'q' => $term,
            'results' => $search->search($request->user(), $term),
        ]);
    }
}
