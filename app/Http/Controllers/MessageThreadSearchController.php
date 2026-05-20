<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Inbox\MessageSearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-67 MESSAGE-SEARCH-2: landlord/caretaker message search. The
 * searcher is ALWAYS the authenticated caller — MessageSearchService
 * scopes results to that user's own threads, so this controller cannot
 * be coerced into searching someone else's inbox.
 */
class MessageThreadSearchController extends Controller
{
    public function index(Request $request, MessageSearchService $search): Response
    {
        $term = (string) $request->query('q', '');

        return Inertia::render('MessageThreads/Search', [
            'q' => $term,
            'results' => $search->search($request->user(), $term),
        ]);
    }
}
