<?php

use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

// 12. Notifications
Route::get('/notifications', [\App\Http\Controllers\NotificationsController::class, 'index'])->name('notifications.index');
Route::get('/notifications/overview', [\App\Http\Controllers\NotificationsController::class, 'overview'])->name('notifications.overview');
// RATE-3: bulk-notify limiter caps fan-out spam vectors. 2/min and
// 20/hour per landlord; per-row cap separately enforced in
// SendBulkNotificationRequest.
Route::post('/notifications/send', [\App\Http\Controllers\NotificationsController::class, 'send'])
    ->middleware('throttle:bulk-notify')
    ->name('notifications.send');
Route::post('/notifications/send-bulk', [\App\Http\Controllers\NotificationsController::class, 'sendBulk'])
    ->middleware('throttle:bulk-notify')
    ->name('notifications.sendBulk');
Route::post('/notifications/rent-reminders', [\App\Http\Controllers\NotificationsController::class, 'sendRentReminders'])
    ->middleware('throttle:bulk-notify')
    ->name('notifications.sendRentReminders');
Route::post('/notifications/arrears-notices', [\App\Http\Controllers\NotificationsController::class, 'sendArrearsNotices'])
    ->middleware('throttle:bulk-notify')
    ->name('notifications.sendArrearsNotices');
Route::get('/notifications/preferences', [\App\Http\Controllers\NotificationsController::class, 'getPreferences'])->name('notifications.preferences');
Route::post('/notifications/preferences', [\App\Http\Controllers\NotificationsController::class, 'updatePreferences'])->name('notifications.updatePreferences');
Route::post('/notifications/{notification}/mark-read', [\App\Http\Controllers\NotificationsController::class, 'markAsRead'])->name('notifications.markAsRead');
// RATE-11: retry hits the SMS/email provider — bound to 'sensitive'.
Route::post('/notifications/{notification}/retry', [\App\Http\Controllers\NotificationsController::class, 'retry'])
    ->middleware('throttle:sensitive')
    ->name('notifications.retry');
Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationsController::class, 'destroy'])->name('notifications.destroy');

// Notification Templates
Route::get('/notifications/templates', [\App\Http\Controllers\NotificationTemplateController::class, 'templates'])->name('notifications.templates');
Route::post('/notifications/templates', [\App\Http\Controllers\NotificationTemplateController::class, 'storeTemplate'])->name('notifications.templates.store');
Route::put('/notifications/templates/{template}', [\App\Http\Controllers\NotificationTemplateController::class, 'updateTemplate'])->name('notifications.templates.update');
Route::delete('/notifications/templates/{template}', [\App\Http\Controllers\NotificationTemplateController::class, 'destroyTemplate'])->name('notifications.templates.destroy');
Route::post('/notifications/templates/{template}/preview', [\App\Http\Controllers\NotificationTemplateController::class, 'previewTemplate'])
    ->middleware('throttle:provider-test')
    ->name('notifications.templates.preview');

// Notification Schedules
Route::get('/notifications/schedules', [\App\Http\Controllers\NotificationScheduleController::class, 'schedules'])->name('notifications.schedules');
Route::post('/notifications/schedules', [\App\Http\Controllers\NotificationScheduleController::class, 'storeSchedule'])->name('notifications.schedules.store');
Route::put('/notifications/schedules/{schedule}', [\App\Http\Controllers\NotificationScheduleController::class, 'updateSchedule'])->name('notifications.schedules.update');
Route::delete('/notifications/schedules/{schedule}', [\App\Http\Controllers\NotificationScheduleController::class, 'destroySchedule'])->name('notifications.schedules.destroy');
Route::post('/notifications/schedules/{schedule}/toggle', [\App\Http\Controllers\NotificationScheduleController::class, 'toggleSchedule'])->name('notifications.schedules.toggle');
// RATE-11: run-now triggers an immediate broadcast — sensitive bound.
Route::post('/notifications/schedules/{schedule}/run', [\App\Http\Controllers\NotificationScheduleController::class, 'runScheduleNow'])
    ->middleware('throttle:sensitive')
    ->name('notifications.schedules.run');

// Notification Settings
Route::get('/notifications/settings', [\App\Http\Controllers\NotificationsController::class, 'settings'])->name('notifications.settings');
Route::post('/notifications/settings/provider/{provider}', [\App\Http\Controllers\NotificationsController::class, 'updateProviderSettings'])->name('notifications.settings.provider');
Route::post('/notifications/settings/test/{provider}', [\App\Http\Controllers\NotificationsController::class, 'testProvider'])
    ->middleware('throttle:provider-test')
    ->name('notifications.settings.test');
Route::post('/notifications/settings/complete-setup', [\App\Http\Controllers\NotificationsController::class, 'completeSetup'])->name('notifications.settings.complete-setup');
Route::post('/notifications/push/generate-keys', [\App\Http\Controllers\NotificationPushController::class, 'generateVapidKeys'])->name('notifications.push.generate-keys');
Route::get('/notifications/settings/status', [\App\Http\Controllers\NotificationsController::class, 'checkSetupStatus'])->name('notifications.settings.status');
Route::post('/notifications/settings/vapid', [\App\Http\Controllers\NotificationPushController::class, 'generateVapidKeys'])->name('notifications.settings.vapid');
Route::get('/notifications/settings/global', [\App\Http\Controllers\NotificationsController::class, 'getGlobalPreferences'])->name('notifications.settings.global.get');
Route::post('/notifications/settings/global', [\App\Http\Controllers\NotificationsController::class, 'updateGlobalPreferences'])->name('notifications.settings.global');
Route::post('/notifications/settings/whatsapp-templates', [\App\Http\Controllers\NotificationsController::class, 'updateWhatsAppTemplates'])->name('notifications.settings.whatsapp-templates');

// Push Notifications
Route::post('/notifications/push/subscribe', [\App\Http\Controllers\NotificationPushController::class, 'subscribePush'])->name('notifications.push.subscribe');
Route::post('/notifications/push/unsubscribe', [\App\Http\Controllers\NotificationPushController::class, 'unsubscribePush'])->name('notifications.push.unsubscribe');
Route::get('/notifications/push/key', [\App\Http\Controllers\NotificationPushController::class, 'getVapidPublicKey'])->name('notifications.push.key');

// 13. Bulk Operations
Route::get('/bulk-operations', [\App\Http\Controllers\BulkOperationsController::class, 'index'])->name('bulk.index');
// RATE-4: bulk-ops limiter (3/min/user) + per-controller Cache::lock
// serialization in BulkOperationsController so concurrent calls per
// landlord don't race.
Route::post('/bulk-operations/adjust-rent', [\App\Http\Controllers\BulkOperationsController::class, 'adjustRent'])
    ->middleware('throttle:bulk-ops')
    ->name('bulk.adjustRent');
Route::post('/bulk-operations/update-unit-status', [\App\Http\Controllers\BulkOperationsController::class, 'updateUnitStatus'])
    ->middleware('throttle:bulk-ops')
    ->name('bulk.updateUnitStatus');
Route::post('/bulk-operations/terminate-leases', [\App\Http\Controllers\BulkOperationsController::class, 'terminateLeases'])
    ->middleware('throttle:bulk-ops')
    ->name('bulk.terminateLeases');
Route::post('/bulk-operations/extend-leases', [\App\Http\Controllers\BulkOperationsController::class, 'extendLeases'])
    ->middleware('throttle:bulk-ops')
    ->name('bulk.extendLeases');
Route::post('/bulk-operations/adjust-deposits', [\App\Http\Controllers\BulkOperationsController::class, 'adjustDeposits'])
    ->middleware('throttle:bulk-ops')
    ->name('bulk.adjustDeposits');
Route::post('/bulk-operations/update-target-rent', [\App\Http\Controllers\BulkOperationsController::class, 'updateTargetRent'])
    ->middleware('throttle:bulk-ops')
    ->name('bulk.updateTargetRent');
Route::post('/bulk-operations/update-meter-numbers', [\App\Http\Controllers\BulkOperationsController::class, 'updateMeterNumbers'])
    ->middleware('throttle:bulk-ops')
    ->name('bulk.updateMeterNumbers');

// 14. Tickets (Issues & Complaints)
Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
Route::post('/tickets', [TicketController::class, 'store'])
    ->middleware('throttle:file-upload')
    ->name('tickets.store');
Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
Route::post('/tickets/{ticket}/assign-vendor', [\App\Http\Controllers\TicketVendorAssignmentController::class, 'store'])
    ->middleware('role:landlord')
    ->name('tickets.assign-vendor');
// Phase-75 VENDOR-ROUTING-2: suggested vendor pool for a ticket.
Route::get('/tickets/{ticket}/vendor-pool', [\App\Http\Controllers\TicketVendorAssignmentController::class, 'suggest'])
    ->middleware('role:landlord')
    ->name('tickets.vendor-pool');
Route::post('/tickets/{ticket}/comment', [TicketController::class, 'addComment'])->name('tickets.comment');
Route::post('/tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
Route::post('/tickets/{ticket}/feedback', [TicketController::class, 'submitFeedback'])->name('tickets.feedback');
Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
// Phase-45 TICKET-PHOTOS-1/2: annotated copy of a photo attachment.
Route::post('/tickets/{ticket}/attachments/{document}/annotation', [\App\Http\Controllers\TicketAnnotationController::class, 'store'])
    ->name('tickets.attachments.annotation');
Route::get('/buildings/{building}/units', [TicketController::class, 'getUnits'])->name('buildings.units');

// Phase-80 TASK-BOARD: caretaker mobile-first daily board + inline actions + escalate.
Route::middleware('role:caretaker')->group(function () {
    Route::get('/my-tasks', [\App\Http\Controllers\CaretakerTaskController::class, 'index'])->name('tasks.index');
    Route::post('/my-tasks/{ticket}/transition', [\App\Http\Controllers\CaretakerTaskController::class, 'transition'])
        ->whereNumber('ticket')->name('tasks.transition');
    Route::post('/my-tasks/{ticket}/escalate', [\App\Http\Controllers\CaretakerTaskController::class, 'escalate'])
        ->whereNumber('ticket')->name('tasks.escalate');
});
// Phase-80 ESCALATION-VIEW: landlord acknowledges an open escalation.
Route::post('/tickets/{ticket}/escalation/acknowledge', [TicketController::class, 'acknowledgeEscalation'])
    ->middleware('role:landlord')->whereNumber('ticket')->name('tickets.escalation.acknowledge');

// 14b. Complaints (Alias to Tickets with category filter)
Route::get('/complaints', [TicketController::class, 'index'])->name('complaints.index')->defaults('category', 'complaint');
