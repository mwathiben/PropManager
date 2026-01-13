<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add parent_building_id for self-referential wing relationships.
     * - Parent buildings: parent_building_id = NULL
     * - Wings: parent_building_id = parent building's ID
     * - Standalone buildings: no children, no parent
     */
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->foreignId('parent_building_id')
                ->nullable()
                ->after('property_id')
                ->constrained('buildings')
                ->nullOnDelete();

            $table->boolean('is_wing')
                ->default(false)
                ->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropForeign(['parent_building_id']);
            $table->dropColumn(['parent_building_id', 'is_wing']);
        });
    }
};
