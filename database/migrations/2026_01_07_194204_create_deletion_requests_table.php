<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending, processing, completed, cancelled, failed
            $table->timestamp('requested_at');
            $table->timestamp('scheduled_deletion_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('anonymized_email')->nullable(); // Store original email after anonymization
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_deletion_at');
            $table->index(['status', 'scheduled_deletion_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deletion_requests');
    }
};
