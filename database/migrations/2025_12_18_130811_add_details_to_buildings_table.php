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
            // Location details (building_type, features, coordinates already exist from previous migration)
            $table->string('address')->nullable()->after('building_type');
            $table->text('description')->nullable()->after('address');

            // Photo gallery
            $table->json('photos')->nullable()->after('features');
        });

        // Rename 'features' to 'amenities' for clarity
        Schema::table('buildings', function (Blueprint $table) {
            $table->renameColumn('features', 'amenities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename back to features
        Schema::table('buildings', function (Blueprint $table) {
            $table->renameColumn('amenities', 'features');
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['address', 'description', 'photos']);
        });
    }
};
