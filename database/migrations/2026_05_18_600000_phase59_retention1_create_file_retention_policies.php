<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-59 FILE-RETENTION-1: per-subject file retention windows.
 * Platform defaults seeded by Phase59FileRetentionPolicySeeder;
 * landlords can override per-subject via landlord_id rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 64);
            $table->unsignedInteger('retention_days');
            $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['subject', 'landlord_id'], 'frp_unique_subject_per_landlord');
            $table->index('subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_retention_policies');
    }
};
