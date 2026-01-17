<?php

use App\Broadcasting\LandlordChannel;
use App\Broadcasting\LeaseChannel;
use App\Broadcasting\TenantChannel;
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
    return $user !== null;
});
