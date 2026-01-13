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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->enum('type', [
                'rent_reminder',
                'arrears_notice',
                'invoice',
                'receipt',
                'rent_hike',
                'lease_expiry',
                'lease_renewal',
                'maintenance_notice',
                'general',
                'eviction_notice',
            ]);
            $table->string('subject');
            $table->text('body');
            $table->json('available_placeholders')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['landlord_id', 'type']);
            $table->unique(['landlord_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
