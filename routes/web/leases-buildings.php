<?php

use App\Http\Controllers\ArchiveHubController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\LeaseCoTenantController;
use App\Http\Controllers\LeaseGuarantorController;
use App\Http\Controllers\MaintenanceHubController;
use App\Http\Controllers\OperationsHubController;
use App\Http\Controllers\RentEscalationController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantEmergencyContactController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\TenantNoteController;
use App\Http\Controllers\TenantsHubController;
use App\Http\Controllers\WaterHubController;
use App\Http\Controllers\WaterReadingController;
use Illuminate\Support\Facades\Route;

// 2. Leases (Adding Tenants & Rent Hikes)
Route::get('/units/{unit}/lease/create', [LeaseController::class, 'create'])->name('leases.create');
Route::post('/units/{unit}/lease', [LeaseController::class, 'store'])->name('leases.store');
Route::post('/leases/{lease}/adjust-rent', [LeaseController::class, 'adjustRent'])->name('leases.adjust-rent');
Route::post('/leases/batch-adjust', [LeaseController::class, 'batchAdjustRent'])->name('leases.batch-adjust');
Route::post('/leases/{lease}/wallet-adjustment', [LeaseController::class, 'walletAdjustment'])->name('leases.wallet-adjustment');
Route::get('/leases/{lease}/wallet-history', fn ($lease) => redirect()->route('leases.show', $lease))->name('leases.wallet-history');
Route::get('/leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');
Route::get('/leases/{lease}/download', [LeaseController::class, 'download'])
    ->middleware('throttle:pdf-render')
    ->name('leases.download');
// Phase-61 TERMINATION-3: lease early-termination route.
Route::post('/leases/{lease}/terminate', [LeaseController::class, 'terminate'])
    ->name('leases.terminate');
// Phase-61 TRANSFER-3: lease assignment / sublet route.
Route::post('/leases/{lease}/transfer', [LeaseController::class, 'transfer'])
    ->name('leases.transfer');
// Phase-61 PAUSE-3: temporary lease pause route.
Route::post('/leases/{lease}/pause', [LeaseController::class, 'pause'])
    ->name('leases.pause');
// Phase-61 RENEWAL-AUTO-3: per-lease auto-renew toggle.
Route::patch('/leases/{lease}/auto-renew', [LeaseController::class, 'toggleAutoRenew'])
    ->name('leases.auto-renew');
// Phase-83 RENT-ESCALATION-3: schedule / cancel future rent increases.
Route::post('/leases/{lease}/escalations', [RentEscalationController::class, 'store'])
    ->middleware('role:landlord,caretaker')
    ->whereNumber('lease')->name('rent-escalations.store');
Route::delete('/escalations/{escalation}', [RentEscalationController::class, 'destroy'])
    ->middleware('role:landlord,caretaker')
    ->whereNumber('escalation')->name('rent-escalations.destroy');
// Phase-83 CO-TENANT-2: manage co-tenants on a joint tenancy.
Route::post('/leases/{lease}/co-tenants', [LeaseCoTenantController::class, 'store'])
    ->middleware('role:landlord,caretaker')
    ->whereNumber('lease')->name('lease-co-tenants.store');
Route::delete('/co-tenants/{coTenant}', [LeaseCoTenantController::class, 'destroy'])
    ->middleware('role:landlord,caretaker')
    ->whereNumber('coTenant')->name('lease-co-tenants.destroy');
// Phase-83 GUARANTOR-2: manage guarantors standing behind a lease.
Route::post('/leases/{lease}/guarantors', [LeaseGuarantorController::class, 'store'])
    ->middleware('role:landlord,caretaker')
    ->whereNumber('lease')->name('lease-guarantors.store');
Route::post('/guarantors/{guarantor}/release', [LeaseGuarantorController::class, 'release'])
    ->middleware('role:landlord,caretaker')
    ->whereNumber('guarantor')->name('lease-guarantors.release');

// 3. The Architect (Building Configuration)
Route::get('/buildings/{building}/configure', [BuildingController::class, 'edit'])->name('buildings.edit');
Route::put('/buildings/{building}/settings', [BuildingController::class, 'updateSettings'])->name('buildings.update-settings');
Route::post('/buildings/{building}/update-units', [BuildingController::class, 'updateUnits'])->name('buildings.update-units');
Route::post('/buildings/{building}/add-unit', [BuildingController::class, 'addUnit'])->name('buildings.add-unit');

// Route for adding a new wing/building
Route::post('/properties/{property}/buildings', [BuildingController::class, 'store'])->name('buildings.store');

// Route for adding a wing to an existing building
Route::post('/buildings/{building}/wings', [BuildingController::class, 'storeWing'])->name('buildings.store-wing');

// Buildings (Landlord Home - New Primary Navigation)
Route::get('/buildings', [BuildingController::class, 'index'])->name('buildings.index');
Route::post('/buildings', [BuildingController::class, 'storeStandalone'])->name('buildings.storeStandalone');

// Properties (Legacy redirect + store for backward compatibility)
Route::post('/properties', [\App\Http\Controllers\PropertyController::class, 'store'])->name('properties.store');
// Phase-78 PROPERTY-VIEW/SWITCH: the property tier is landlord/caretaker only
// (CodeRabbit H1 — explicit role gate, not just getLandlordId()'s 403).
Route::middleware('role:landlord,caretaker')->group(function () {
    Route::get('/properties', [\App\Http\Controllers\PropertyController::class, 'index'])->name('properties.index');
    // PROPERTY-BENCHMARK: cross-property ranking (no id — before the {property} route).
    Route::get('/properties/benchmark', [\App\Http\Controllers\PropertyController::class, 'benchmark'])->name('properties.benchmark');
    // PROPERTY-SWITCH-3: the resolved active property (no id).
    Route::get('/properties/current', [\App\Http\Controllers\PropertyController::class, 'current'])->name('properties.current');
    // PROPERTY-SWITCH-1: persist the active property. Landlord-only —
    // active-property is a per-landlord concept and the switcher UI is
    // landlord-only (CodeRabbit M1: caretakers must not write it).
    Route::post('/properties/{property}/switch', [\App\Http\Controllers\PropertyController::class, 'switchTo'])
        ->middleware('role:landlord')->whereNumber('property')->name('properties.switch');
    // PROPERTY-VIEW-1: single-property dashboard.
    Route::get('/properties/{property}', [\App\Http\Controllers\PropertyController::class, 'show'])
        ->whereNumber('property')->name('properties.show');
    // Phase-101 OWNER-FOUNDATION: assign/clear the owner a property is managed for.
    Route::put('/properties/{property}/owner/{owner}', [\App\Http\Controllers\PropertyOwnerController::class, 'assign'])
        ->whereNumber('property')->whereNumber('owner')->name('properties.owner.assign');
    Route::delete('/properties/{property}/owner', [\App\Http\Controllers\PropertyOwnerController::class, 'unassign'])
        ->whereNumber('property')->name('properties.owner.unassign');
});

// Building Details & Dashboard
Route::get('/buildings/{building}', [BuildingController::class, 'show'])->name('buildings.show');
Route::get('/buildings/{building}/dashboard', [BuildingController::class, 'dashboard'])->name('buildings.dashboard');

// Water Settings (Per-Building Configuration)
Route::get('/buildings/{building}/water-settings', [BuildingController::class, 'waterSettings'])->name('buildings.water-settings');
Route::put('/buildings/{building}/water-settings', [BuildingController::class, 'updateWaterSettings'])->name('buildings.water-settings.update');

// Invoice Automation Settings (Per-Building Configuration)
Route::put('/buildings/{building}/automation-settings', [BuildingController::class, 'updateAutomationSettings'])->name('buildings.automation-settings.update');

// Building Deletion
Route::delete('/buildings/{building}', [BuildingController::class, 'destroy'])->name('buildings.destroy');

// ========================================
// CONSOLIDATED HUB ROUTES (Navigation Optimization)
// ========================================

// Tenants Hub - Consolidates: Tenants, Invitations, Verifications, Payment Verifications, Move-Outs, History
Route::get('/tenants-hub', [TenantsHubController::class, 'index'])->name('tenants.hub');

// Maintenance Hub - Consolidates: Tickets (Issues), Complaints
Route::get('/maintenance', [MaintenanceHubController::class, 'index'])->name('maintenance.hub');

// Phase-75 VENDOR-PERF-2: landlord-side vendor performance comparison.
Route::middleware('role:landlord')
    ->get('/maintenance/vendor-performance', [\App\Http\Controllers\MaintenanceVendorPerformanceController::class, 'index'])
    ->name('maintenance.vendor-performance');

// Phase-80 CARETAKER-PERF-2: landlord-side caretaker performance comparison.
Route::middleware('role:landlord')
    ->get('/maintenance/caretaker-performance', [\App\Http\Controllers\MaintenanceCaretakerPerformanceController::class, 'index'])
    ->name('maintenance.caretaker-performance');

// Phase-75 PHOTO-ROLLUP: landlord-wide maintenance photo gallery + PDF export.
Route::middleware('role:landlord')->group(function () {
    Route::get('/maintenance/photos', [\App\Http\Controllers\MaintenancePhotoGalleryController::class, 'index'])
        ->name('maintenance.photos');
    Route::get('/maintenance/photos/export-pdf', [\App\Http\Controllers\MaintenancePhotoGalleryController::class, 'exportPdf'])
        ->middleware('throttle:pdf-render')
        ->name('maintenance.photos.export-pdf');
});

// Water Hub - Consolidates: Readings, History, Settings.
// Phase-79 WATER-GATE-3: conditional module — gated on the landlord
// actually charging for water (water.module), not just the plan flag.
Route::get('/water', [WaterHubController::class, 'index'])
    ->middleware('water.module')->name('water.hub');

// Archive Hub - Consolidates: Documents, Leases, Activity Logs
Route::get('/archive', [ArchiveHubController::class, 'index'])->name('archive.hub');

// Operations Hub - Consolidates: Notifications, Bulk Operations, Team, Imports
Route::get('/operations', [OperationsHubController::class, 'index'])->name('operations.hub');

// ========================================
// END CONSOLIDATED HUB ROUTES
// ========================================

// 4. Tenant Management (Viewing/Editing Profiles)
// PRIV-15: defense-in-depth role guard. Controllers already verify
// landlord scope inline, but the route-level role:landlord,caretaker
// means a misconfigured controller (or a future regression) cannot
// accidentally expose these endpoints to a tenant role token.
Route::middleware('role:landlord,caretaker')->group(function () {
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::get('/tenants/{tenant}/modal-data', [TenantController::class, 'modalData'])->name('tenants.modal-data');
    Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    // Tenant Notes
    Route::post('/tenants/{tenant}/notes', [TenantNoteController::class, 'store'])->name('tenants.notes.store');
    Route::put('/tenant-notes/{note}', [TenantNoteController::class, 'update'])->name('tenants.notes.update');
    Route::delete('/tenant-notes/{note}', [TenantNoteController::class, 'destroy'])->name('tenants.notes.destroy');
    // Emergency Contacts
    Route::post('/tenants/{tenant}/emergency-contacts', [TenantEmergencyContactController::class, 'store'])->name('tenants.emergency-contacts.store');
    Route::put('/emergency-contacts/{contact}', [TenantEmergencyContactController::class, 'update'])->name('tenants.emergency-contacts.update');
    Route::delete('/emergency-contacts/{contact}', [TenantEmergencyContactController::class, 'destroy'])->name('tenants.emergency-contacts.destroy');
    // Tenant API for payment recording
    Route::get('/tenants/search', [TenantController::class, 'search'])
        ->middleware('throttle:search')
        ->name('tenants.search');
    Route::get('/tenants/{tenant}/outstanding-invoices', [TenantController::class, 'outstandingInvoices'])->name('tenants.outstanding-invoices');
    Route::get('/tenants/{tenant}/refundable-payments', [TenantController::class, 'refundablePayments'])->name('tenants.refundable-payments');
    // Tenant Ledger/Statement
    Route::get('/tenants/{tenant}/ledger', [TenantController::class, 'ledger'])->name('tenants.ledger');
    Route::get('/tenants/{tenant}/ledger/pdf', [TenantController::class, 'ledgerPdf'])
        ->middleware('throttle:pdf-render')
        ->name('tenants.ledger.pdf');
    Route::post('/tenants/{tenant}/ledger/email', [TenantController::class, 'ledgerEmail'])->name('tenants.ledger.email');
});

// 5. Water Readings (The Water Guy) — Phase-79 WATER-GATE-3: whole
// surface gated on the conditional water module.
Route::middleware('water.module')->group(function () {
    Route::get('/readings', [WaterReadingController::class, 'index'])->name('readings.index');
    Route::post('/readings', [WaterReadingController::class, 'store'])
        ->middleware('throttle:file-upload')
        ->name('readings.store');
    Route::get('/readings/history', [WaterReadingController::class, 'history'])->name('readings.history');
    Route::get('/readings/review', [WaterReadingController::class, 'review'])->name('readings.review');
    Route::post('/readings/{reading}/approve', [WaterReadingController::class, 'approve'])->name('readings.approve');
    Route::post('/readings/{reading}/reject', [WaterReadingController::class, 'reject'])->name('readings.reject');
    Route::post('/readings/{reading}/request-reread', [WaterReadingController::class, 'requestReread'])->whereNumber('reading')->name('readings.request-reread');
    Route::get('/readings/{reading}/photo', [WaterReadingController::class, 'photo'])->name('readings.photo');
    Route::put('/readings/{reading}', [WaterReadingController::class, 'update'])->name('readings.update');
    Route::delete('/readings/{reading}', [WaterReadingController::class, 'destroy'])->name('readings.destroy');

    // Phase-86 METER-LIFECYCLE: landlord-only meter fleet management
    // (register with a non-zero baseline, replace preserving continuity,
    // decommission). Caretakers record readings but never manage meters.
    Route::middleware('role:landlord')->group(function () {
        Route::get('/water/meters', [\App\Http\Controllers\MeterController::class, 'index'])->name('meters.index');
        Route::post('/water/meters', [\App\Http\Controllers\MeterController::class, 'store'])->name('meters.store');
        Route::post('/water/meters/{meter}/replace', [\App\Http\Controllers\MeterController::class, 'replace'])->whereNumber('meter')->name('meters.replace');
        Route::post('/water/meters/{meter}/decommission', [\App\Http\Controllers\MeterController::class, 'decommission'])->whereNumber('meter')->name('meters.decommission');
        Route::post('/water/meters/{meter}/disconnect', [\App\Http\Controllers\MeterController::class, 'disconnect'])->whereNumber('meter')->name('meters.disconnect');
        Route::post('/water/meters/{meter}/reconnect', [\App\Http\Controllers\MeterController::class, 'reconnect'])->whereNumber('meter')->name('meters.reconnect');

        // Phase-91 PRODUCTION-COST: borehole running-cost log feeding the
        // landlord water intelligence margin metric (landlord-only).
        Route::post('/water/production-costs', [\App\Http\Controllers\WaterProductionCostController::class, 'store'])->name('water.production-costs.store');
        Route::delete('/water/production-costs/{productionCost}', [\App\Http\Controllers\WaterProductionCostController::class, 'destroy'])->whereNumber('productionCost')->name('water.production-costs.destroy');

        // Phase-92 WATER-COMPLIANCE: set a borehole building's annual WRA
        // abstraction limit (landlord-only). Permit/cert files upload via
        // documents.store (documentable_type=Building).
        Route::put('/water/compliance/buildings/{building}/limit', [\App\Http\Controllers\WaterComplianceController::class, 'updateLimit'])->whereNumber('building')->name('water.compliance.limit');

        // Phase-94 WATER-CLIENTS-FOUNDATION: opt-in setup + water-line (connection)
        // management for non-tenant water clients (landlord-only).
        Route::put('/water/clients/setup', [\App\Http\Controllers\WaterConnectionController::class, 'setup'])->name('water.clients.setup');
        Route::post('/water/connections', [\App\Http\Controllers\WaterConnectionController::class, 'store'])->name('water.connections.store');
        Route::put('/water/connections/{waterConnection}', [\App\Http\Controllers\WaterConnectionController::class, 'update'])->whereNumber('waterConnection')->name('water.connections.update');
        Route::delete('/water/connections/{waterConnection}', [\App\Http\Controllers\WaterConnectionController::class, 'destroy'])->whereNumber('waterConnection')->name('water.connections.destroy');

        // Phase-95 WATER-CLIENT-ONBOARDING: invite the client for a connection.
        Route::post('/water/connections/{waterConnection}/invite', [\App\Http\Controllers\WaterClientInvitationController::class, 'store'])->whereNumber('waterConnection')->name('water-client-invitations.store');
    });
});

// 6. Invitations (Caretaker Management)
// Caretaker removal (Operations → Team). landlord-only; controller
// re-checks the caretaker belongs to this landlord (404 otherwise).
Route::middleware('role:landlord')
    ->delete('/caretakers/{caretaker}', [\App\Http\Controllers\CaretakerController::class, 'destroy'])
    ->whereNumber('caretaker')
    ->name('caretakers.destroy');
Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

// 6a. Caretaker Invitation Accept/Decline (In-App for existing users)
Route::post('/invitations/{invitation}/accept-authenticated', [InvitationController::class, 'acceptAuthenticated'])->name('invitations.accept-authenticated');
Route::post('/invitations/{invitation}/decline-authenticated', [InvitationController::class, 'declineAuthenticated'])->name('invitations.decline-authenticated');

// 6b. Tenant Invitations (Tenant Onboarding)
Route::get('/tenant-invitations', [TenantInvitationController::class, 'index'])->name('tenant-invitations.index');
Route::post('/tenant-invitations', [TenantInvitationController::class, 'store'])->name('tenant-invitations.store');
Route::put('/tenant-invitations/{invitation}', [TenantInvitationController::class, 'update'])->name('tenant-invitations.update');
Route::post('/tenant-invitations/{invitation}/resend', [TenantInvitationController::class, 'resend'])->name('tenant-invitations.resend');
Route::delete('/tenant-invitations/{invitation}', [TenantInvitationController::class, 'destroy'])->name('tenant-invitations.destroy');

// 6c. Tenant Invitation Accept/Decline (In-App for existing users)
Route::post('/tenant-invitations/{invitation}/accept-authenticated', [TenantInvitationController::class, 'acceptAuthenticated'])->name('tenant-invitations.accept-authenticated');
Route::post('/tenant-invitations/{invitation}/decline-authenticated', [TenantInvitationController::class, 'declineAuthenticated'])->name('tenant-invitations.decline-authenticated');
