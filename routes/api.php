<?php

use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\TenantInvoiceController;
use App\Http\Controllers\Api\TenantLeaseController;
use App\Http\Controllers\Api\TenantNotificationController;
use App\Http\Controllers\Api\TenantPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| API routes for mobile app and third-party integrations.
| All routes use Laravel Sanctum for authentication.
|
*/

// Health checks
Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => '1.0']));
Route::get('/health/payments', [HealthCheckController::class, 'payments'])
    ->middleware('throttle:api');

// API v1 routes
Route::prefix('v1')->group(function () {

    // Public routes
    Route::post('/auth/login', [\App\Http\Controllers\Api\AuthController::class, 'login'])
        ->middleware('throttle:login');
    Route::post('/auth/register', [\App\Http\Controllers\Api\AuthController::class, 'register'])
        ->middleware('throttle:register');

    // Authenticated routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        // Auth
        Route::post('/auth/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
        Route::get('/auth/user', [\App\Http\Controllers\Api\AuthController::class, 'user']);

        // Tenant routes (mobile app)
        Route::prefix('tenant')->middleware('ability:tenant:read')->group(function () {
            // Current lease
            Route::get('/lease', [TenantLeaseController::class, 'current']);
            Route::get('/lease/history', [TenantLeaseController::class, 'history']);

            // Invoices
            Route::get('/invoices', [TenantInvoiceController::class, 'index']);
            Route::get('/invoices/{invoice}', [TenantInvoiceController::class, 'show']);
            Route::get('/invoices/{invoice}/download', [TenantInvoiceController::class, 'download']);

            // Payments
            Route::get('/payments', [TenantPaymentController::class, 'index']);
            Route::get('/payments/{payment}', [TenantPaymentController::class, 'show']);
            Route::get('/payments/{payment}/receipt', [TenantPaymentController::class, 'receipt']);
            Route::post('/payments/mpesa/initiate', [TenantPaymentController::class, 'initiateMpesa'])
                ->middleware('throttle:payment');
            Route::post('/payments/mpesa/status', [TenantPaymentController::class, 'checkMpesaStatus']);
            Route::post('/payments/paystack/initiate', [TenantPaymentController::class, 'initiatePaystack'])
                ->middleware('throttle:payment');
            Route::post('/payments/intasend/initiate', [TenantPaymentController::class, 'initiateIntaSend'])
                ->middleware('throttle:payment')
                ->name('api.v1.tenant.payments.intasend.initiate');

            // Notifications
            Route::get('/notifications', [TenantNotificationController::class, 'index']);
            Route::patch('/notifications/{id}/read', [TenantNotificationController::class, 'markAsRead']);
            Route::patch('/notifications/read-all', [TenantNotificationController::class, 'markAllAsRead']);
        });

        // Landlord/Caretaker routes
        Route::prefix('landlord')->middleware('ability:landlord:manage')->group(function () {
            // Properties
            Route::get('/properties', [\App\Http\Controllers\Api\PropertyController::class, 'index']);
            Route::get('/properties/{property}', [\App\Http\Controllers\Api\PropertyController::class, 'show']);

            // Buildings
            Route::get('/buildings', [\App\Http\Controllers\Api\BuildingController::class, 'index']);
            Route::get('/buildings/{building}', [\App\Http\Controllers\Api\BuildingController::class, 'show']);
            Route::get('/buildings/{building}/units', [\App\Http\Controllers\Api\BuildingController::class, 'units']);

            // Units
            Route::get('/units', [\App\Http\Controllers\Api\UnitController::class, 'index']);
            Route::get('/units/{unit}', [\App\Http\Controllers\Api\UnitController::class, 'show']);
            Route::patch('/units/{unit}/status', [\App\Http\Controllers\Api\UnitController::class, 'updateStatus']);

            // Invoices
            Route::get('/invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'index']);
            Route::get('/invoices/{invoice}', [\App\Http\Controllers\Api\InvoiceController::class, 'show']);

            // Payments
            Route::get('/payments', [\App\Http\Controllers\Api\PaymentController::class, 'index']);
            Route::get('/payments/{payment}', [\App\Http\Controllers\Api\PaymentController::class, 'show']);

            // Reports
            Route::get('/reports/occupancy', [\App\Http\Controllers\Api\ReportController::class, 'occupancy']);
            Route::get('/reports/revenue', [\App\Http\Controllers\Api\ReportController::class, 'revenue']);
            Route::get('/reports/arrears', [\App\Http\Controllers\Api\ReportController::class, 'arrears']);
        });

        // Third-party integration routes
        Route::prefix('integrations')->middleware('ability:integration:webhook')->group(function () {
            Route::get('/reports/occupancy', [\App\Http\Controllers\Api\ReportController::class, 'occupancy']);
            Route::get('/reports/revenue', [\App\Http\Controllers\Api\ReportController::class, 'revenue']);
        });
    });

    // M-Pesa payment initiation and status check (authenticated tenant)
    Route::post('/mpesa/stk-push', [\App\Http\Controllers\Api\MpesaController::class, 'initiateStkPush'])
        ->middleware(['auth:sanctum', 'throttle:payment']);
    Route::post('/mpesa/status', [\App\Http\Controllers\Api\MpesaController::class, 'checkStatus'])
        ->middleware(['auth:sanctum', 'throttle:payment']);
});

// API v2 routes - Optimized endpoints with pagination
Route::prefix('v2')->group(function () {

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        // Landlord/Caretaker routes
        Route::prefix('landlord')->middleware('ability:landlord:manage')->group(function () {
            // Reports - v2 with pagination and DB-level aggregation
            Route::get('/reports/arrears', [\App\Http\Controllers\Api\ReportController::class, 'arrearsV2']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Webhook Routes (No Authentication - IP Validated)
|--------------------------------------------------------------------------
|
| External payment processor webhooks. These routes do not use Sanctum
| authentication. Security is handled via IP whitelisting and/or
| signature validation within the controllers.
|
*/

Route::prefix('webhooks')->group(function () {

    // IntaSend M-Pesa STK Push callback (challenge-based validation)
    Route::post('/intasend/mpesa', [\App\Http\Controllers\Api\IntaSendWebhookController::class, 'handleMpesaWebhook']);

    // M-Pesa webhooks (IP + timestamp validated via middleware)
    Route::middleware('webhook.mpesa')->group(function () {
        Route::post('/mpesa/c2b/validation', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'c2bValidation']);
        Route::post('/mpesa/c2b/confirmation', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'c2bConfirmation']);
        Route::post('/mpesa/till/validation', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'tillValidation']);
        Route::post('/mpesa/till/confirmation', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'tillConfirmation']);
        Route::post('/mpesa/stk-callback', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'stkCallback']);
        Route::post('/mpesa/b2c/result', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'b2cResult']);
        Route::post('/mpesa/b2c/timeout', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'b2cTimeout']);
    });

    // Bank webhooks (signature validated)
    Route::post('/bank/equity', [\App\Http\Controllers\Api\BankWebhookController::class, 'equityWebhook']);
    Route::post('/bank/kcb', [\App\Http\Controllers\Api\BankWebhookController::class, 'kcbWebhook']);
    Route::post('/bank/coop', [\App\Http\Controllers\Api\BankWebhookController::class, 'coopWebhook']);

    // WhatsApp webhooks (Twilio signature validated)
    Route::post('/whatsapp/status', [\App\Http\Controllers\Api\WhatsAppWebhookController::class, 'statusCallback']);
    Route::post('/whatsapp/inbound', [\App\Http\Controllers\Api\WhatsAppWebhookController::class, 'inboundMessage']);
});
