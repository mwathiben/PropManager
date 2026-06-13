<?php

use Illuminate\Support\Facades\Route;

// Phase-45 LEASE-COUNTER-2: landlord-side review of a tenant counter-offer.
Route::middleware(['auth', 'verified', 'role:landlord,manager,caretaker'])->group(function () {
    Route::post('/landlords/renewals/{renewal}/counter/accept', [\App\Http\Controllers\LeaseRenewalCounterReviewController::class, 'accept'])
        ->middleware('throttle:sensitive')
        ->name('landlords.renewals.counter.accept');
    Route::post('/landlords/renewals/{renewal}/counter/reject', [\App\Http\Controllers\LeaseRenewalCounterReviewController::class, 'reject'])
        ->middleware('throttle:sensitive')
        ->name('landlords.renewals.counter.reject');
    Route::post('/landlords/renewals/{renewal}/counter/re-propose', [\App\Http\Controllers\LeaseRenewalCounterReviewController::class, 'rePropose'])
        ->middleware('throttle:sensitive')
        ->name('landlords.renewals.counter.re_propose');

    // Phase-63 INBOX-COMPOSE-1: landlord-side message-thread surface.
    Route::get('/message-threads', [\App\Http\Controllers\MessageThreadController::class, 'index'])
        ->name('message-threads.index');
    Route::get('/message-threads/{thread}', [\App\Http\Controllers\MessageThreadController::class, 'show'])
        ->whereNumber('thread')
        ->name('message-threads.show');
    Route::post('/message-threads', [\App\Http\Controllers\MessageThreadController::class, 'store'])
        ->middleware('throttle:messages')
        ->name('message-threads.store');
    Route::post('/message-threads/{thread}/messages', [\App\Http\Controllers\MessageThreadController::class, 'storeMessage'])
        ->middleware('throttle:messages')
        ->name('message-threads.messages.store');

    // Phase-71 REACTIONS: toggle an emoji reaction (participant-gated).
    Route::post('/message-threads/{thread}/messages/{message}/reactions', [\App\Http\Controllers\MessageReactionController::class, 'toggle'])
        ->whereNumber('thread')
        ->whereNumber('message')
        ->middleware('throttle:reactions')
        ->name('message-threads.messages.react');

    // Phase-71 MEDIA-CI: participant-gated message attachment (image/file).
    Route::get('/message-threads/{thread}/messages/{message}/attachments/{document}', [\App\Http\Controllers\MessageAttachmentController::class, 'show'])
        ->whereNumber('thread')
        ->whereNumber('message')
        ->whereNumber('document')
        ->name('message-threads.attachments.show');

    // Phase-63 INBOX-MOD-1: landlord moderation transitions.
    Route::post('/message-threads/{thread}/archive', [\App\Http\Controllers\MessageThreadModerationController::class, 'archive'])
        ->name('message-threads.archive');
    Route::post('/message-threads/{thread}/lock', [\App\Http\Controllers\MessageThreadModerationController::class, 'lock'])
        ->name('message-threads.lock');
    Route::post('/message-threads/{thread}/unlock', [\App\Http\Controllers\MessageThreadModerationController::class, 'unlock'])
        ->name('message-threads.unlock');

    // Phase-67 READ-RECEIPTS-1: mark the whole thread read.
    Route::post('/message-threads/{thread}/read-all', \App\Http\Controllers\MessageThreadReadAllController::class)
        ->name('message-threads.read-all');

    // Phase-67 MESSAGE-SEARCH-2: participant-scoped full-text search.
    // The show route is ->whereNumber, so the literal /search resolves here.
    Route::get('/message-threads/search', [\App\Http\Controllers\MessageThreadSearchController::class, 'index'])
        ->middleware('throttle:messages')
        ->name('message-threads.search');
});

// Phase-63 INBOX-REALTIME-2: shared read-receipt endpoint. Any
// authenticated participant on the thread can mark a message read.
Route::middleware('auth')->group(function () {
    Route::patch('/messages/{message}/read', \App\Http\Controllers\MessageReadController::class)
        ->name('messages.read');
    // Phase-63 INBOX-MOD-1: sender soft-delete within the 5-min window.
    Route::delete('/messages/{message}', \App\Http\Controllers\MessageDeleteController::class)
        ->name('messages.destroy');
});
