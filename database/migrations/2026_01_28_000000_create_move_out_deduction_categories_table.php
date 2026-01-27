<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('move_out_deduction_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('default_amount', 10, 2)->default(0);

            $table->boolean('always_apply')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['landlord_id', 'building_id', 'is_active'], 'deduction_cat_landlord_bldg_active');
            $table->index(['building_id', 'is_active'], 'deduction_cat_bldg_active');

            $table->unique(['landlord_id', 'building_id', 'name'], 'deduction_cat_unique_name');
        });

        Schema::table('move_out_deductions', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('move_out_id')
                ->constrained('move_out_deduction_categories')
                ->nullOnDelete();

            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('move_out_deductions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('move_out_deduction_categories');
    }
};
