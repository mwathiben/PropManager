<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Events\MessagePosted;
use App\Http\Controllers\Concerns\AttachesReplyPreviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\StoreMessageRequest;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Inbox\MessageAttachmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-63 INBOX-COMPOSE-1: tenant-side inbox surface.
 *
 * Tenants can list their own threads, view one, post replies into
 * existing threads, and initiate a brand-new thread to the landlord.
 * scopeForUser via the participants pivot is the authoritative
 * cross-tenant isolation gate.
 */
class InboxController extends Controller
{
    use AttachesReplyPreviews;
    use AuthorizesRequests;

    public function __construct(
        private readonly MessageAttachmentService $attachments,
    ) {}

    public function index(Request $request): Response
    {
        $threads = MessageThread::query()
            ->forUser($request->user())
            ->orderByDesc('last_message_at')
            ->with([
                'participants:id,name,role',
            ])
            ->paginate(20);

        return Inertia::render('Tenant/Inbox/Index', [
            'threads' => $threads,
        ]);
    }

    public function show(Request $request, MessageThread $thread): Response
    {
        $this->authorize('view', $thread);

        $thread->load([
            'participants:id,name,role',
            'messages' => fn ($q) => $q->orderBy('created_at')
                ->with([
                    'sender:id,name,role',
                    'documents',
                    'replyTo:id,sender_id,body',
                    'replyTo.sender:id,name',
                    'reactions:id,message_id,user_id,emoji',
                ]),
        ]);

        $this->attachReplyPreviews($thread);
        $this->attachReactionSummaries($thread, $request->user()?->id);
        $this->attachAttachmentMeta($thread, 'tenant.inbox.attachments.show');

        return Inertia::render('Tenant/Inbox/Show', [
            'thread' => $thread,
            'unreadCount' => $thread->unreadCountFor($request->user()),
            // Phase-67 READ-RECEIPTS-2: other participants' read cursors.
            'read_receipts' => $thread->readReceiptsFor($request->user()),
            // Phase-71 REACTIONS: emoji allow-list for the picker.
            'reactionEmojis' => config('inbox.reactions'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $tenant */
        $tenant = $request->user();

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:1', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:jpeg,jpg,png,webp,pdf',
                'max:5120',
            ],
        ]);

        if (empty($tenant->landlord_id)) {
            abort(403, 'Tenant has no associated landlord.');
        }

        $landlordId = (int) $tenant->landlord_id;

        // Phase-67 ATTACHMENT-SCAN: scan before the transaction opens so a
        // rejected (infected) upload's audit row is not rolled back.
        $files = $request->file('attachments');
        $scanned = is_array($files) && $files !== []
            ? $this->attachments->scan($files, $tenant)
            : [];

        [$thread, $message] = DB::transaction(function () use ($data, $tenant, $landlordId, $scanned) {
            $thread = MessageThread::create([
                'landlord_id' => $landlordId,
                'title' => $data['title'] ?? null,
            ]);

            $thread->participants()->attach($tenant->id, [
                'role' => MessageThread::ROLE_TENANT,
            ]);
            $thread->participants()->attach($landlordId, [
                'role' => MessageThread::ROLE_LANDLORD,
            ]);

            $message = $thread->messages()->create([
                'sender_id' => $tenant->id,
                'body' => $data['body'],
            ]);

            $this->attachments->persist($message, $scanned);

            return [$thread, $message];
        });

        broadcast(new MessagePosted($message))->toOthers();

        return redirect()
            ->route('tenant.inbox.show', $thread)
            ->with('status', __('inbox.thread_created'));
    }

    public function storeMessage(
        StoreMessageRequest $request,
        MessageThread $thread,
    ): RedirectResponse {
        $user = $request->user();

        $files = $request->file('attachments');
        $scanned = is_array($files) && $files !== []
            ? $this->attachments->scan($files, $user, $thread->id)
            : [];

        $message = DB::transaction(function () use ($request, $thread, $user, $scanned) {
            $message = $thread->messages()->create([
                'sender_id' => $user->id,
                'reply_to_id' => $request->input('reply_to_id'),
                'body' => $request->input('body'),
            ]);

            $this->attachments->persist($message, $scanned);

            return $message;
        });

        broadcast(new MessagePosted($message))->toOthers();

        return back()->with('status', __('inbox.message_sent'));
    }
}
