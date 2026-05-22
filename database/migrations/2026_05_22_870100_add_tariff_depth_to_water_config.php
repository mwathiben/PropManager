<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-87 WATER-TARIFF-ENGINE (TARIFF-MODEL-1): tariff depth on the canonical
 * water config (global PaymentConfiguration + per-building Building override,
 * Building NULL = inherit). All nullable + default-off so the biller is
 * unchanged until a landlord configures them.
 */
return new class extends Migration
{
    private array $tables = ['payment_configurations', 'buildings'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('tiered_tariffs')->nullable();
                $t->decimal('water_standing_charge', 10, 2)->nullable();
                $t->decimal('water_minimum_charge', 10, 2)->nullable();
                $t->decimal('water_sewerage_percent', 5, 2)->nullable();
                $t->decimal('water_vat_percent', 5, 2)->nullable();
                $t->string('water_source', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn([
                    'tiered_tariffs', 'water_standing_charge', 'water_minimum_charge',
                    'water_sewerage_percent', 'water_vat_percent', 'water_source',
                ]);
            });
        }
    }
};
