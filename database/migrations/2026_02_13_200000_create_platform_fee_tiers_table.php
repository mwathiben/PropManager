<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('min_volume', 12, 2);
            $table->decimal('max_volume', 12, 2)->nullable();
            $table->decimal('fee_percentage', 5, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'min_volume']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_tiers');
    }
};
