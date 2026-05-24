<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-101 OWNER-FOUNDATION: a property OWNER as a first-class contact entity — the
 * party a property manager manages on behalf of. A contact, NOT a login user (a later
 * OWNER-PORTAL phase mints a User linked via user_id, mirroring WaterConnection.user_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_owners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            // Forward-compat for the later owner-portal login (unused this phase).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('id_number')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['landlord_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_owners');
    }
};
