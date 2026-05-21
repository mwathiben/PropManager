<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-78 AMENITY-DEPTH-1: per-amenity operational detail for a building —
 * quantity (e.g. parking spaces), provider (e.g. wifi ISP), account reference,
 * and a monthly cost. One row per (building, amenity_key); the amenity must be
 * one of Building::getAllAmenityKeys() AND currently selected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_amenity_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('amenity_key', 64);
            $table->unsignedInteger('quantity')->nullable();
            $table->string('provider', 120)->nullable();
            $table->string('account_ref', 120)->nullable();
            $table->unsignedBigInteger('monthly_cost_cents')->nullable();
            $table->timestamps();

            $table->unique(['building_id', 'amenity_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_amenity_details');
    }
};
