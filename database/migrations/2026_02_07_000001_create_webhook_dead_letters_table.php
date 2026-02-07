<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_dead_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();

            $table->string('provider', 20);
            $table->string('event_type', 50)->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();

            $table->text('error_reason');
            $table->string('error_class', 20)->nullable();

            $table->unsignedInteger('attempts')->default(1);
            $table->unsignedInteger('max_retries')->default(5);
            $table->timestamp('next_retry_at')->nullable();

            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            $table->index(['landlord_id', 'provider']);
            $table->index(['provider', 'resolved_at']);
            $table->index(['error_class', 'resolved_at']);
            $table->index('next_retry_at');
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_dead_letters');
    }
};
