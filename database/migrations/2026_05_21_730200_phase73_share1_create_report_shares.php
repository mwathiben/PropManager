<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-73 REPORT-SHARE: a landlord-minted, time-boxed, revocable link to one
 * of their saved reports. The shareable URL is a Laravel signed route carrying
 * this row's id (signature = authz, expiry-bound); the row adds revocation +
 * an access trail. landlord_id is the owner; the view runs the report with the
 * row's own landlord_id, never a request param.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('saved_report_id')->constrained('saved_reports')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();

            $table->index(['landlord_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_shares');
    }
};
