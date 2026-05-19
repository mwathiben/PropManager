<?php

use App\Broadcasting\LandlordChannel;
use App\Broadcasting\LeaseChannel;
use App\Broadcasting\TenantChannel;
use App\Models\IntaSendTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('landlord.{landlordId}', LandlordChannel::class);

Broadcast::channel('tenant.{tenantId}', TenantChannel::class);

Broadcast::channel('lease.{leaseId}', LeaseChannel::class);

Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('mpesa.{checkoutRequestId}', function ($user, $checkoutRequestId) {
    // Find the payment by checkout request ID and verify ownership
    $payment = Payment::where('mpesa_checkout_request_id', $checkoutRequestId)->first();

    if (! $payment) {
        return false;
    }

    // User must be the landlord who owns this payment
    return (int) $user->id === (int) $payment->landlord_id;
});

Broadcast::channel('intasend.{intasendInvoiceId}', function ($user, $intasendInvoiceId) {
    // Find the IntaSend transaction and verify ownership
    $transaction = IntaSendTransaction::where('intasend_invoice_id', $intasendInvoiceId)->first();

    if (! $transaction) {
        return false;
    }

    // User must be the landlord who owns this transaction
    return (int) $user->id === (int) $transaction->landlord_id;
});

// Phase-63 INBOX-REALTIME-1: thread subscription is gated by the
// message_thread_participants pivot — NOT by landlord_id — so two
// tenants under the same landlord cannot eavesdrop on each other.
Broadcast::channel('inbox.thread.{threadId}', function ($user, $threadId) {
    return \Illuminate\Support\Facades\DB::table('message_thread_participants')
        ->where('thread_id', $threadId)
        ->where('user_id', $user->id)
        ->exists();
});
