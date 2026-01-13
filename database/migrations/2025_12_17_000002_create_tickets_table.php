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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('building_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');

            $table->enum('category', ['issue', 'complaint']);
            $table->string('subcategory');
            $table->string('title');
            $table->text('description');

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'acknowledged', 'in_progress', 'resolved', 'closed', 'cancelled'])->default('open');

            $table->string('location')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['landlord_id', 'status']);
            $table->index(['building_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('category');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
