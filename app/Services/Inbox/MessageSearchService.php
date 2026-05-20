<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\MetricsService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

/**
 * Phase-67 MESSAGE-SEARCH-1: participant-scoped message search.
 *
 * Isolation is the headline property: the result set is confined to the
 * user's OWN threads (the message_thread_participants pivot via
 * MessageThread::scopeForUser) BEFORE any matching, so a participant can
 * never surface a message from a thread they don't belong to — not even
 * one under the same landlord.
 *
 * Matching is a per-word LIKE within that bounded thread set. A FULLTEXT
 * index was prototyped but InnoDB's FULLTEXT cache does not see
 * uncommitted rows inside a transaction, leaving the match path
 * untestable under RefreshDatabase; LIKE over a user's own (bounded)
 * threads is reliable, fully testable, and adequate at this scale. Each
 * word is wildcard-escaped + bound (no SQL/LIKE injection), and the term
 * is operator-sanitised first.
 */
class MessageSearchService
{
    public const MIN_TERM_LENGTH = 3;

    public function __construct(private MetricsService $metrics) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function search(User $user, string $term, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 50));
        $page = Paginator::resolveCurrentPage();
        $sanitised = $this->sanitise($term);

        if (mb_strlen($sanitised) < self::MIN_TERM_LENGTH) {
            return $this->emptyResult($perPage, $page);
        }

        // Visibility gate FIRST — only the user's own threads.
        $visibleThreadIds = MessageThread::query()->forUser($user)->pluck('id');
        if ($visibleThreadIds->isEmpty()) {
            return $this->emptyResult($perPage, $page);
        }

        $this->metrics->increment('inbox_search_queries_count');

        $words = array_values(array_filter(explode(' ', $sanitised), fn (string $w) => $w !== ''));

        $matches = Message::query()
            ->whereIn('thread_id', $visibleThreadIds)
            ->where(function ($builder) use ($words) {
                // All words must appear (AND). Wildcards are escaped so a
                // user's literal % or _ can't broaden the match.
                foreach ($words as $word) {
                    $builder->where('body', 'like', '%'.addcslashes($word, '%_\\').'%');
                }
            })
            ->orderByDesc('created_at')
            ->get(['id', 'thread_id', 'body', 'created_at']);

        // Most-recent matching message per thread.
        $bestPerThread = [];
        foreach ($matches as $message) {
            if (! isset($bestPerThread[$message->thread_id])) {
                $bestPerThread[$message->thread_id] = $message;
            }
        }

        $threads = MessageThread::query()
            ->whereIn('id', array_keys($bestPerThread))
            ->get(['id', 'title', 'status', 'last_message_at'])
            ->keyBy('id');

        $rows = [];
        foreach ($bestPerThread as $threadId => $message) {
            $thread = $threads->get($threadId);
            if ($thread === null) {
                continue;
            }
            $rows[] = [
                'thread_id' => (int) $threadId,
                'title' => $thread->title,
                'status' => $thread->status,
                'last_message_at' => $thread->last_message_at?->toISOString(),
                'snippet' => Str::limit($message->body, 160),
                'matched_at' => $message->created_at?->toISOString(),
            ];
        }

        // Most recent thread activity first.
        usort($rows, fn (array $a, array $b) => ($b['last_message_at'] ?? '') <=> ($a['last_message_at'] ?? ''));

        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($slice, count($rows), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    /**
     * Neutralise BOOLEAN-mode operators (+ - > < ( ) ~ * " @) so a crafted
     * query can't alter match semantics; keep words, digits, and spaces.
     */
    private function sanitise(string $term): string
    {
        $clean = preg_replace('/[+\-><()~*"@]+/u', ' ', $term) ?? '';
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';

        return trim($clean);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyResult(int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }
}
