<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentLinkController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Phase-21 DEFER-AUTHZ-4: client-side route-guard landing page. The
// Inertia router.beforeEach hook in resources/js/app.js redirects here
// when the shared abilities map predicts the server will reject the
// visit — so the user sees the 403.vue UX without a wasted round-trip.
// Real server-side 403/404s render the same pages via bootstrap/app.php.
Route::get('/403', fn () => Inertia::render('Errors/403'))->name('errors.403');

// Phase-26 PWA-SHELL-2: branded offline page. The service worker's
// navigation fallback (resources/js/sw-merged.ts NavigationRoute)
// serves this when the network is unreachable. Rendered as an Inertia
// page so it shares layout chrome.
Route::get('/offline', fn () => Inertia::render('Offline'))->name('offline');

// Phase-26 PWA-SHELL-1: service worker at root scope. Vite's
// `vite-plugin-pwa` generates the SW into public/build/sw.js. Browsers
// limit a SW's scope to its own URL path by default — to grant root
// scope from a non-root path we must send `Service-Worker-Allowed: /`.
// Keeping the registration path `/sw.js` (unchanged from pre-Phase-26)
// means no client-side change. If the build hasn't run yet (fresh
// clone), this route 404s and the SW registration in app.js fails
// silently — non-fatal.
Route::get('/sw.js', function () {
    $path = public_path('build/sw.js');
    if (! is_file($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/javascript',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
})->name('sw.js');

// --- PUBLIC INVITATION ROUTES (Guest Access) ---
// Caretaker Invitations
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('throttle:invitation')
    ->name('invitations.accept');

// Phase-95 Water-Client Invitations (deep-link accept)
Route::get('/water-invite/{token}', [\App\Http\Controllers\WaterClientInvitationController::class, 'show'])
    ->middleware('throttle:invitation')
    ->name('water-invite.show');
Route::post('/water-invite/{token}/accept', [\App\Http\Controllers\WaterClientInvitationController::class, 'accept'])
    ->middleware('throttle:invitation')
    ->name('water-invite.accept');

// Phase-102 Owner-Portal Invitations (deep-link accept)
Route::get('/owner-invite/{token}', [\App\Http\Controllers\OwnerInvitationController::class, 'show'])
    ->middleware('throttle:invitation')
    ->name('owner-invite.show');
Route::post('/owner-invite/{token}/accept', [\App\Http\Controllers\OwnerInvitationController::class, 'accept'])
    ->middleware('throttle:invitation')
    ->name('owner-invite.accept');

// Slice-2 PR-2.3c: Owner management-agreement e-signature (public, token + OTP gated)
Route::get('/agreement-signing/{token}', [\App\Http\Controllers\AgreementSigningController::class, 'show'])
    ->middleware('throttle:invitation')
    ->name('agreements.sign.show');
Route::post('/agreement-signing/{token}/otp', [\App\Http\Controllers\AgreementSigningController::class, 'requestOtp'])
    ->middleware('throttle:6,1')
    ->name('agreements.sign.otp');
Route::post('/agreement-signing/{token}', [\App\Http\Controllers\AgreementSigningController::class, 'sign'])
    ->middleware('throttle:6,1')
    ->name('agreements.sign');

// Tenant Invitations
Route::get('/tenant-invite/{token}', [TenantInvitationController::class, 'show'])->name('tenant-invitations.show');
Route::post('/tenant-invite/{token}/accept', [TenantInvitationController::class, 'accept'])
    ->middleware('throttle:invitation')
    ->name('tenant-invitations.accept');

// Payment Links (WhatsApp/SMS clickable links)
Route::get('/pay/{token}', [PaymentLinkController::class, 'show'])
    ->middleware('throttle:payment-link')
    ->name('payment.link');

// --- TWO-FACTOR AUTHENTICATION CHALLENGE (Post-Login) ---
Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])
    ->name('two-factor.challenge');
Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])
    ->middleware('throttle:two-factor')
    ->name('two-factor.verify');

// --- PAYMENT WEBHOOKS (Server-to-Server, CSRF excluded) ---
Route::post('/webhooks/paystack', [PaymentController::class, 'handleWebhook'])
    ->middleware('webhook.paystack')
    ->name('webhooks.paystack');

// Phase-30 INT-MPESA-DEEP-3: HMAC-SHA512 verified Paystack receiver.
Route::post('/webhooks/v2/paystack', [\App\Http\Controllers\Webhooks\PaystackWebhookController::class, 'handle'])
    ->name('webhooks.v2.paystack');

// Phase-40 GATEWAY-WEBHOOK-2: Stripe-Signature verified Stripe receiver.
Route::post('/webhooks/v2/stripe', \App\Http\Controllers\Webhooks\StripeWebhookController::class)
    ->name('webhooks.v2.stripe');

Route::prefix('webhooks/mpesa')->name('webhooks.mpesa.')->middleware('webhook.mpesa')->group(function () {
    Route::post('/stk-callback', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'stkCallback'])
        ->name('stk-callback');
    Route::post('/c2b/validation', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'c2bValidation'])
        ->name('c2b-validation');
    Route::post('/c2b/confirmation', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'c2bConfirmation'])
        ->name('c2b-confirmation');
});

// --- THE DASHBOARD (Role-Based) ---
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'payment.verified', 'kyc.complete'])
    ->name('dashboard');

Route::get('/units/{unit}/detail', [DashboardController::class, 'unitDetail'])
    ->middleware(['auth'])
    ->name('units.detail');

Route::get('/dashboard/stats', \App\Http\Controllers\DashboardStatsController::class)
    ->middleware(['auth', 'role:landlord,manager,caretaker', 'throttle:30,1'])
    ->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class])
    ->name('dashboard.stats');

// --- SIGNED EMAIL ROUTES (no auth required) ---
// RATE-9: signed.once replaces 'signed' so a forwarded email link
// cannot be replayed. The first hit consumes the signature in
// signed_link_uses; subsequent hits return 403.
Route::get('/email/preferences', [\App\Http\Controllers\NotificationsController::class, 'emailPreferences'])
    ->name('email.preferences')
    ->middleware(['signed.once', 'throttle:invitation']);

Route::post('/email/unsubscribe', [\App\Http\Controllers\NotificationsController::class, 'oneClickUnsubscribe'])
    ->name('email.unsubscribe')
    ->middleware(['signed.once', 'throttle:invitation']);

// Phase-59 SIGNED-URLS-1: local-driver fallback target for
// TenantDiskResolver::temporaryUrl. The signed middleware verifies
// the URL hasn't been tampered with; controllers issue these via
// $resolver->temporaryUrl() after their own ownership-validation pass.
Route::get('/files/local-stream', [\App\Http\Controllers\FileLocalStreamController::class, 'stream'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('files.local-stream');

// Phase-73 REPORT-SHARE: public, signed, no-auth read-only report view. The
// signature (bound to the share id + expiry) IS the authz; the controller
// re-checks the row is active and runs the report with its OWN landlord_id.
Route::get('/reports/share/{share}', [\App\Http\Controllers\Reports\ReportShareController::class, 'view'])
    ->whereNumber('share')
    ->middleware(['signed', 'throttle:60,1'])
    ->name('reports.share.view');

// Phase-74 DASH-SHARE: public, signed, no-auth read-only dashboard view.
// Same contract as reports.share.view — signature is the authz; the controller
// re-checks the row is active and builds the dashboard with its OWN landlord_id.
Route::get('/dashboards/share/{share}', [\App\Http\Controllers\Reports\DashboardShareController::class, 'view'])
    ->whereNumber('share')
    ->middleware(['signed', 'throttle:60,1'])
    ->name('dashboards.share.view');

// Phase-54 VENDOR-ONBOARDING-2: signed-URL profile completion for a
// vendor. No auth — the signed URL IS the auth. Outside the
// auth-middleware group so unauthenticated vendors can complete the
// form. throttle:invitation matches the existing one-shot-link cadence.
Route::middleware(['signed', 'throttle:invitation'])->group(function () {
    Route::get('/v/profile/{vendor}', [\App\Http\Controllers\VendorProfileController::class, 'edit'])
        ->name('vendor.profile.edit');
    Route::patch('/v/profile/{vendor}', [\App\Http\Controllers\VendorProfileController::class, 'update'])
        ->name('vendor.profile.update');

    // Phase-70 VENDOR-AUTH-1: signed magic-link that establishes the vendor
    // portal session, then redirects into the portal.
    Route::get('/v/portal/enter/{vendor}', [\App\Http\Controllers\VendorPortalController::class, 'enter'])
        ->name('vendor.portal.enter');
});

// Phase-70 VENDOR-PORTAL: session-guarded portal (no User row — the
// EnsureVendorPortal middleware resolves the vendor from the session
// seeded by the signed enter link). All queries scope to that vendor.
Route::middleware(['vendor.portal', 'throttle:60,1'])->prefix('v/portal')->name('vendor.portal.')->group(function () {
    Route::get('/', [\App\Http\Controllers\VendorPortalController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [\App\Http\Controllers\VendorPortalController::class, 'logout'])->name('logout');

    // Phase-70 TICKET-INBOX: assigned jobs + accept/decline.
    Route::get('/jobs', [\App\Http\Controllers\VendorPortalTicketController::class, 'index'])->name('inbox');
    Route::post('/tickets/{ticket}/accept', [\App\Http\Controllers\VendorPortalTicketController::class, 'accept'])
        ->whereNumber('ticket')->name('tickets.accept');
    Route::post('/tickets/{ticket}/decline', [\App\Http\Controllers\VendorPortalTicketController::class, 'decline'])
        ->whereNumber('ticket')->name('tickets.decline');

    // Phase-70 JOB-ACTIONS: job detail + log time + mark resolved.
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\VendorPortalTicketController::class, 'show'])
        ->whereNumber('ticket')->name('tickets.show');
    Route::post('/tickets/{ticket}/time', [\App\Http\Controllers\VendorPortalTicketController::class, 'logTime'])
        ->whereNumber('ticket')->middleware('throttle:10,1')->name('tickets.time');
    Route::post('/tickets/{ticket}/resolve', [\App\Http\Controllers\VendorPortalTicketController::class, 'resolve'])
        ->whereNumber('ticket')->name('tickets.resolve');

    // Phase-70 PAYOUT-STATEMENT: read-only cost statement + CSV.
    Route::get('/statement', [\App\Http\Controllers\VendorPortalStatementController::class, 'index'])->name('statement');
    Route::get('/statement/export', [\App\Http\Controllers\VendorPortalStatementController::class, 'export'])->name('statement.export');

    // Phase-70 SLA-DASHBOARD: the vendor's own SLA performance.
    Route::get('/sla', [\App\Http\Controllers\VendorPortalSlaController::class, 'index'])->name('sla');
});

// --- AUTHENTICATED ROUTES GROUP ---
Route::middleware('auth')->group(function () {

    require __DIR__.'/web/onboarding.php';
    require __DIR__.'/web/leases-buildings.php';
    require __DIR__.'/web/tenants-billing.php';
    require __DIR__.'/web/reports.php';
    require __DIR__.'/web/notifications.php';
    require __DIR__.'/web/finances.php';
    require __DIR__.'/web/settings-access.php';
    require __DIR__.'/web/agreements.php';
});

require __DIR__.'/web/admin.php';

// Phase-42 CART-2: cart checkout initialization. Auth-gated +
// ownership-checked inside the controller because both tenant and
// landlord may legitimately submit.
Route::middleware(['auth'])->group(function () {
    Route::post('/checkout/sessions/{session}/initialize', [\App\Http\Controllers\CartCheckoutController::class, 'initialize'])
        ->name('checkout.sessions.initialize');
});

// Stop impersonating (available to anyone being impersonated)
Route::post('/admin/stop-impersonating', [AdminController::class, 'stopImpersonating'])
    ->middleware('auth')
    ->name('admin.stopImpersonating');

require __DIR__.'/web/tenant-portal.php';

require __DIR__.'/web/messaging.php';

require __DIR__.'/web/legal-holds.php';

require __DIR__.'/web/nps-renewals.php';

require __DIR__.'/web/gdpr.php';

require __DIR__.'/auth.php';
