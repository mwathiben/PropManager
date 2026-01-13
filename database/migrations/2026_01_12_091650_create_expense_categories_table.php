<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('color', 7)->default('#6B7280');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['landlord_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
