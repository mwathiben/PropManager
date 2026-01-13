<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    /**
     * Display the help center index page.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $role = $user->role;

        $faqs = Faq::published()
            ->forRole($role)
            ->orderBy('category')
            ->orderBy('order')
            ->get()
            ->groupBy('category');

        $articles = HelpArticle::published()
            ->forRole($role)
            ->orderBy('category')
            ->orderBy('order')
            ->get()
            ->groupBy('category');

        $categories = [
            'getting-started' => [
                'name' => 'Getting Started',
                'description' => 'Learn the basics of using PropManager',
                'icon' => 'rocket',
            ],
            'features' => [
                'name' => 'Features & How-To',
                'description' => 'Detailed guides on using features',
                'icon' => 'book',
            ],
            'billing' => [
                'name' => 'Billing & Payments',
                'description' => 'Understand invoices and payment options',
                'icon' => 'credit-card',
            ],
            'troubleshooting' => [
                'name' => 'Troubleshooting',
                'description' => 'Solve common issues',
                'icon' => 'wrench',
            ],
            'notifications' => [
                'name' => 'Notifications',
                'description' => 'Configure email, SMS, WhatsApp & push notifications',
                'icon' => 'bell',
            ],
            'security' => [
                'name' => 'Security & Privacy',
                'description' => 'Protect your account and understand your data rights',
                'icon' => 'shield',
            ],
        ];

        return Inertia::render('Help/Index', [
            'faqs' => $faqs,
            'articles' => $articles,
            'categories' => $categories,
            'supportEmail' => config('app.support_email', 'support@propmanager.com'),
        ]);
    }

    /**
     * Display a specific help article.
     */
    public function show(HelpArticle $article): Response
    {
        $user = auth()->user();

        // Check if user can access this article
        if ($article->roles && ! in_array($user->role, $article->roles)) {
            abort(403, 'You do not have access to this article.');
        }

        // Get related articles in the same category
        $relatedArticles = HelpArticle::published()
            ->forRole($user->role)
            ->where('category', $article->category)
            ->where('id', '!=', $article->id)
            ->orderBy('order')
            ->limit(5)
            ->get();

        return Inertia::render('Help/Show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
        ]);
    }

    /**
     * Search help content.
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $user = auth()->user();

        if (strlen($query) < 2) {
            return response()->json([
                'faqs' => [],
                'articles' => [],
            ]);
        }

        $faqs = Faq::published()
            ->forRole($user->role)
            ->where(function ($q) use ($query) {
                $q->where('question', 'like', "%{$query}%")
                    ->orWhere('answer', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        $articles = HelpArticle::published()
            ->forRole($user->role)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        return response()->json([
            'faqs' => $faqs,
            'articles' => $articles,
        ]);
    }
}
