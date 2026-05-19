<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\MessagePosted;
use App\Http\Requests\Inbox\StoreMessageRequest;
use App\Http\Requests\Inbox\StoreMessageThreadRequest;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Inbox\MessageAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-63 INBOX-COMPOSE-1: landlord-side message-thread surface.
 *
 * Routes mounted under role:landlord,caretaker middleware. Index +
 * show are Inertia pages; store + storeMessage are POST endpoints
 * carrying throttle:messages (registered in RouteServiceProvider in
 * sub-phase 1e INBOX-MOD-3).
 */
class MessageThreadController extends Controller
{
    public function __construct(
        private readonly MessageAttachmentService $attachments,
    ) {}

    public function index(Request $request): Response
    {
        $threads = MessageThread::query()
            ->forUser($request->user())
            ->openOnly()
            ->orderByDesc('last_message_at')
            ->with([
                'participants:id,name,role',
                'subject',
            ])
            ->paginate(20);

        return Inertia::render('MessageThreads/Index', [
            'threads' => $threads,
        ]);
    }

    public function show(Request $request, MessageThread $thread): Response
    {
        $this->authorize('view', $thread);

        $thread->load([
            'participants:id,name,role',
            'subject',
            'messages' => fn ($q) => $q->orderBy('created_at')
                ->with(['sender:id,name,role', 'documents']),
        ]);

        return Inertia::render('MessageThreads/Show', [
            'thread' => $thread,
            'unreadCount' => $thread->unreadCountFor($request->user()),
        ]);
    }

    public function store(StoreMessageThreadRequest $request): RedirectResponse
    {
        $landlord = $request->user();
        $landlordId = $request->landlordId();

        [$thread, $initialMessage] = DB::transaction(function () use ($request, $landlord, $landlordId) {
            $subjectType = $request->input('subject_type');

            $thread = MessageThread::create([
                'landlord_id' => $landlordId,
                'subject_type' => $subjectType ? $this->resolveSubjectType($subjectType) : null,
                'subject_id' => $subjectType ? $request->input('subject_id') : null,
                'title' => $request->input('title'),
            ]);

            $thread->participants()->attach($landlord->id, [
                'role' => $landlord->isLandlord()
                    ? MessageThread::ROLE_LANDLORD
                    : MessageThread::ROLE_CARETAKER,
            ]);

            foreach ($request->input('participants', []) as $userId) {
                if ((int) $userId === (int) $landlord->id) {
                    continue;
                }

                $user = User::findOrFail($userId);
                $thread->participants()->attach($user->id, [
                    'role' => $this->roleFor($user),
                ]);
            }

            $message = $thread->messages()->create([
                'sender_id' => $landlord->id,
                'body' => $request->input('body'),
            ]);

            $files = $request->file('attachments');
            if (is_array($files) && $files !== []) {
                $this->attachments->attachToMessage($message, $files);
            }

            return [$thread, $message];
        });

        broadcast(new MessagePosted($initialMessage))->toOthers();

        return redirect()
            ->route('message-threads.show', $thread)
            ->with('status', __('inbox.thread_created'));
    }

    public function storeMessage(
        StoreMessageRequest $request,
        MessageThread $thread,
    ): RedirectResponse {
        $user = $request->user();

        $message = DB::transaction(function () use ($request, $thread, $user) {
            $message = $thread->messages()->create([
                'sender_id' => $user->id,
                'body' => $request->input('body'),
            ]);

            $files = $request->file('attachments');
            if (is_array($files) && $files !== []) {
                $this->attachments->attachToMessage($message, $files);
            }

            return $message;
        });

        broadcast(new MessagePosted($message))->toOthers();

        return back()->with('status', __('inbox.message_sent'));
    }

    private function resolveSubjectType(string $key): string
    {
        return match ($key) {
            'lease' => \App\Models\Lease::class,
            'ticket' => \App\Models\Ticket::class,
            default => throw new \InvalidArgumentException("Unknown subject type: {$key}"),
        };
    }

    private function roleFor(User $user): string
    {
        return match (true) {
            $user->isLandlord() => MessageThread::ROLE_LANDLORD,
            $user->isCaretaker() => MessageThread::ROLE_CARETAKER,
            default => MessageThread::ROLE_TENANT,
        };
    }
}
