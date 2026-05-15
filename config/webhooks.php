<?php

/**
 * Phase-25 API-WEBHOOK-3: outbound webhook event catalog.
 *
 * The single source of truth for "what event types can a landlord
 * subscribe to" — the WebhookSubscriptionController's create UI
 * sources its checkbox list from here, the consumer-facing docs at
 * docs/api/webhook-events.md document the payload shape per event,
 * and the dispatch sites that fire events (PaymentReceived listener,
 * InvoiceObserver, etc.) reference these constants when calling
 * DeliverWebhookJob::dispatch.
 *
 * Adding a new event type:
 *   1. Add the entry to `events` below with a short description.
 *   2. Document the payload shape in docs/api/webhook-events.md.
 *   3. Wire the dispatch site (the model event / listener that fires
 *      the webhook) to call DeliverWebhookJob with the new event_type.
 *   4. Phase25WebhookTest's event-catalog test auto-picks the new
 *      entry; the watchdog asserts every event in this config has a
 *      matching docs entry.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Event catalog
    |--------------------------------------------------------------------------
    |
    | Every event type a landlord can subscribe to. The key is the
    | event_type string sent in the X-PropManager-Event header AND
    | in the payload body's `event` field. Description is shown in
    | the subscription UI's event-picker.
    |
    */

    'events' => [
        'payment.received' => 'A tenant payment has been recorded and applied to an invoice.',
        'payment.refunded' => 'A payment has been (partially or fully) refunded to the tenant.',
        'invoice.created' => 'A new invoice has been generated for a tenant.',
        'invoice.paid' => 'An invoice has been fully paid off.',
        'invoice.overdue' => 'An invoice has passed its due date without being fully paid.',
        'lease.signed' => 'A tenant has accepted and signed a lease invitation.',
        'lease.expired' => 'A lease has reached its end date.',
        'tenant.invited' => 'A landlord has issued a tenant invitation.',
    ],

];
