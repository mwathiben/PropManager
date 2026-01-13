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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50)->index(); // login, logout, login_failed, password_change, etc.
            $table->string('severity', 20)->default('info'); // info, warning, error, critical
            $table->string('ip_address', 45)->nullable(); // Supports IPv6
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable(); // GET, POST, etc.
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->string('session_id')->nullable()->index();
            $table->string('country', 2)->nullable(); // ISO country code from IP
            $table->string('city')->nullable();
            $table->boolean('is_suspicious')->default(false)->index();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'event_type']);
            $table->index(['event_type', 'created_at']);
            $table->index(['ip_address', 'event_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
