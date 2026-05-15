<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-27 BI-BUILDER-1: persistence layer for landlord-configured
 * reports.
 *
 * config JSON shape (validated against the ReportBuilderService
 * allowlist on every write — never trusted on read):
 *   {
 *     "table": "payments|invoices|leases|tenants",
 *     "fields": ["lease.rent_amount", "payment.amount", ...],
 *     "filters": [{"field": "payment.amount", "op": ">=", "value": 1000}],
 *     "group_by": ["building.name"],
 *     "sort_by": [{"field": "payment.amount", "direction": "desc"}]
 *   }
 *
 * landlord_id FK with cascade — when a landlord is hard-deleted (rare,
 * Phase-13 DPA), their saved reports go too. TenantScope on the
 * SavedReport model enforces read-side isolation; the cascade is the
 * write-side cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('saved_reports')) {
            return;
        }

        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->json('config');
            $table->timestamps();

            $table->index('landlord_id'); // composite-friendly leading index
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
