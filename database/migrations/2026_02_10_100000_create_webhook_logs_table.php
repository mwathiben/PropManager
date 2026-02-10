<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('event_id', 255);
            $table->string('event_type', 50)->nullable();
            $table->string('payload_hash', 64);
            $table->unsignedInteger('retry_count')->default(1);
            $table->timestamp('first_received_at');
            $table->timestamp('last_received_at');
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'webhook_logs_provider_event_unique');
            $table->index(['landlord_id', 'provider']);
            $table->index(['provider', 'status']);
            $table->index('retry_count');
            $table->index('last_received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
