<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-75 VENDOR-ROUTING-1: a vendor's trade competencies (ticket
 * subcategories they handle). Drives pool suggestion + auto-routing. Scoped to
 * the vendor (which is landlord-scoped); category is allow-list-gated in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('category', 64);
            $table->timestamps();

            $table->unique(['vendor_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_specialties');
    }
};
