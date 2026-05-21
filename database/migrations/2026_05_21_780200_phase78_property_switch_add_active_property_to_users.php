<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-78 PROPERTY-SWITCH-1: the landlord's persistent "active property" so
 * multi-property landlords have a current property that sticks across requests.
 * nullOnDelete — deleting the active property falls back to the first property.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_property_id')->nullable()->after('landlord_id')
                ->constrained('properties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_property_id');
        });
    }
};
