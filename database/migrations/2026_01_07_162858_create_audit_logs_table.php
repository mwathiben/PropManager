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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50); // created, updated, deleted, restored, exported, etc.
            $table->string('auditable_type'); // Model class name
            $table->unsignedBigInteger('auditable_id'); // Model ID
            $table->json('old_values')->nullable(); // Previous state (for updates/deletes)
            $table->json('new_values')->nullable(); // New state (for creates/updates)
            $table->json('changed_fields')->nullable(); // List of changed field names
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            // Indexes for performance
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['landlord_id', 'created_at']);
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
