<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('status', 20);
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('local_count')->default(0);
            $table->unsignedInteger('remote_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('discrepancy_count')->default(0);
            $table->json('result_data')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->boolean('alert_sent')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'provider', 'reconciled_at']);
            $table->index(['status', 'reconciled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_reports');
    }
};
