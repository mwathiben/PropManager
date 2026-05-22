<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-91 PRODUCTION-COST: borehole running costs (pump electricity, maintenance,
 * permits) have no home — the cost-of-production-vs-revenue margin metric needs
 * them. A small explicit log (per landlord, optionally per building) is cleaner
 * than matching free-text expense categories. building_id null = whole-portfolio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_production_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->date('cost_date');
            $table->decimal('amount', 12, 2);
            $table->string('category')->default('other');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'cost_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_production_costs');
    }
};
