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
        Schema::table('buildings', function (Blueprint $table) {
            $table->json('features')->nullable(); // Stores array like ['wifi', 'parking', 'gym']
            $table->json('coordinates')->nullable(); // Stores {lat: x, lng: y} or similar
            $table->string('building_type')->nullable(); // 'residential', 'commercial', etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['features', 'coordinates', 'building_type']);
        });
    }
};
