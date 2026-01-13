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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->string('key'); // e.g., 'ocr_provider', 'ocr_api_key', 'water_rate'
            $table->text('value')->nullable(); // Will be encrypted for sensitive values
            $table->boolean('is_encrypted')->default(false); // Flag for encrypted values
            $table->string('category')->default('general'); // 'ocr', 'payment', 'notification', 'general'
            $table->text('description')->nullable(); // Human-readable description
            $table->timestamps();

            // Unique constraint: one setting per landlord per key
            $table->unique(['landlord_id', 'key']);

            // Index for fast lookups
            $table->index(['landlord_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
