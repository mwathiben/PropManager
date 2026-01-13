<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_fee_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->unsignedSmallInteger('grace_period_days')->default(5);
            $table->enum('fee_type', ['percentage', 'flat_amount']);
            $table->decimal('fee_percentage', 5, 2)->nullable();
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->boolean('is_compounding')->default(false);
            $table->enum('compounding_frequency', ['daily', 'weekly', 'monthly'])->nullable();
            $table->decimal('max_fee_cap', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('priority')->default(10);

            $table->timestamps();

            $table->index(['landlord_id', 'is_active']);
            $table->index(['property_id', 'building_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_fee_policies');
    }
};
