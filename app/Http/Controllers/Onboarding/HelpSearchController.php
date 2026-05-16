<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-31 ONB-HELP-2/3: backing JSON API for the HelpDrawer.
 *
 *   - GET /api/help/contextual?key=X — returns articles whose help_key
 *     matches the page's contextual key (per-route surface).
 *   - GET /api/help/search?q=X      — returns up to 10 articles
 *     matching the title or content, scoped by user role.
 *
 * Both return the same item shape so the drawer's render code is
 * uniform: { id, title, slug, excerpt, category, help_key }.
 */
class HelpSearchController extends Controller
{
    public function contextual(Request $request): JsonResponse
    {
        $key = (string) $request->query('key', '');
        $role = $request->user()->role ?? null;

        if ($key === '') {
            return response()->json(['articles' => []]);
        }

        $articles = HelpArticle::query()
            ->where('help_key', $key)
            ->where('is_published', true)
            ->where(function ($q) use ($role) {
                $q->whereNull('roles')->orWhereJsonContains('roles', $role);
            })
            ->orderBy('order')
            ->limit(10)
            ->get(['id', 'title', 'slug', 'content', 'category', 'help_key']);

        return response()->json(['articles' => $this->shape($articles)]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->user()->role ?? null;

        if (mb_strlen($q) < 2) {
            return response()->json(['articles' => []]);
        }

        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

        $articles = HelpArticle::query()
            ->where('is_published', true)
            ->where(function ($builder) use ($needle) {
                $builder->where('title', 'like', $needle)
                    ->orWhere('content', 'like', $needle);
            })
            ->where(function ($q) use ($role) {
                $q->whereNull('roles')->orWhereJsonContains('roles', $role);
            })
            ->orderBy('order')
            ->limit(10)
            ->get(['id', 'title', 'slug', 'content', 'category', 'help_key']);

        return response()->json(['articles' => $this->shape($articles)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shape($articles): array
    {
        return $articles->map(function (HelpArticle $a): array {
            $clean = strip_tags((string) $a->content);

            return [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
                'help_key' => $a->help_key,
                'category' => $a->category,
                'excerpt' => mb_substr($clean, 0, 180),
            ];
        })->all();
    }
}
