<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-61 PAUSE-1: temporary lease pause (rent vacation / hardship
 * grace). Auto-resumes via cron when pause_end < now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->date('pause_start');
            $table->date('pause_end');
            $table->enum('reason', ['tenant_hardship', 'landlord_renovation', 'mutual', 'other']);
            $table->text('reason_text')->nullable();
            $table->boolean('auto_resumed')->default(false);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['lease_id', 'status'], 'lease_pauses_lease_status');
            $table->index(['status', 'pause_end'], 'lease_pauses_resume_scan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_pauses');
    }
};
