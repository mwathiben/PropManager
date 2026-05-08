<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->decimal('rate_per_unit', 10, 2)->default(150.00);
            $table->unsignedTinyInteger('billing_day')->default(1);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique('landlord_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_settings');
    }
};
