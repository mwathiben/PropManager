<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-25 API-WEBHOOK-1 + WEBHOOK-2 + WEBHOOK-3: landlord-facing
 * outbound webhook self-serve.
 *
 *   - GET    /settings/webhooks                       — list subscriptions
 *   - POST   /settings/webhooks                       — register a new one
 *   - DELETE /settings/webhooks/{subscription}        — revoke
 *   - PATCH  /settings/webhooks/{subscription}        — toggle active
 *   - GET    /settings/webhooks/{subscription}        — delivery log
 *   - POST   /settings/webhooks/{subscription}/test   — dispatch a test payload
 *   - POST   /settings/webhooks/deliveries/{delivery}/retry — manual retry
 */
class WebhookSubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $landlordId = $user->landlord_id ?? $user->id;

        $subscriptions = WebhookSubscription::query()
            ->where('landlord_id', $landlordId)
            ->orderByDesc('created_at')
            ->get(['id', 'url', 'events', 'active', 'last_delivery_at', 'created_at'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'url' => $s->url,
                'events' => $s->events,
                'active' => $s->active,
                'last_delivery_at' => $s->last_delivery_at?->toIso8601String(),
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Webhooks/Index', [
            'subscriptions' => $subscriptions,
            'availableEvents' => config('webhooks.events', []),
            'plaintextSecret' => $request->session()->pull('plaintextSecret'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $availableEvents = array_keys(config('webhooks.events', []));

        $validated = $request->validate([
            'url' => ['required', 'url', 'starts_with:https://', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in($availableEvents)],
        ]);

        $user = $request->user();
        $landlordId = $user->landlord_id ?? $user->id;
        $secret = Str::random(48);

        WebhookSubscription::create([
            'landlord_id' => $landlordId,
            'url' => $validated['url'],
            'secret' => $secret,
            'events' => $validated['events'],
            'active' => true,
        ]);

        return back()->with('plaintextSecret', $secret);
    }

    public function update(Request $request, WebhookSubscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $subscription->update(['active' => $validated['active']]);

        return back()->with('success', $validated['active'] ? 'Webhook enabled.' : 'Webhook paused.');
    }

    public function destroy(WebhookSubscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with('success', 'Webhook subscription deleted.');
    }

    public function show(Request $request, WebhookSubscription $subscription): Response
    {
        $deliveries = WebhookDelivery::query()
            ->where('webhook_subscription_id', $subscription->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'event_type', 'attempt', 'http_status', 'error', 'dispatched_at', 'completed_at', 'dead_lettered']);

        return Inertia::render('Webhooks/Show', [
            'subscription' => [
                'id' => $subscription->id,
                'url' => $subscription->url,
                'events' => $subscription->events,
                'active' => $subscription->active,
                'last_delivery_at' => $subscription->last_delivery_at?->toIso8601String(),
                'created_at' => $subscription->created_at?->toIso8601String(),
            ],
            'deliveries' => $deliveries->map(fn ($d) => [
                'id' => $d->id,
                'event_type' => $d->event_type,
                'attempt' => $d->attempt,
                'http_status' => $d->http_status,
                'error' => $d->error,
                'dispatched_at' => $d->dispatched_at?->toIso8601String(),
                'completed_at' => $d->completed_at?->toIso8601String(),
                'dead_lettered' => $d->dead_lettered,
                'can_retry' => ! $d->dead_lettered && ! ($d->http_status >= 200 && $d->http_status < 300),
            ]),
        ]);
    }

    public function test(WebhookSubscription $subscription): RedirectResponse
    {
        DeliverWebhookJob::dispatch(
            $subscription->id,
            'webhook.test',
            ['message' => 'Test event from PropManager — your subscription is reachable.'],
        );

        return back()->with('success', 'Test event queued — check the delivery log in a few seconds.');
    }

    public function retry(WebhookDelivery $delivery): RedirectResponse
    {
        if (! $delivery->canRetry()) {
            return back()->withErrors(['delivery' => 'This delivery is not retryable.']);
        }

        DeliverWebhookJob::dispatch(
            $delivery->webhook_subscription_id,
            $delivery->event_type,
            $delivery->payload,
            $delivery->attempt + 1,
        );

        return back()->with('success', 'Retry queued.');
    }
}
