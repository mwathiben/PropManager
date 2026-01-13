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
        Schema::create('billing_model_changes', function (Blueprint $table) {
            $table->id();
            $table->enum('from_model', ['transaction_fee', 'subscription', 'hybrid'])->nullable();
            $table->enum('to_model', ['transaction_fee', 'subscription', 'hybrid']);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('effective_date');
            $table->text('reason')->nullable();
            $table->json('settings_snapshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_model_changes');
    }
};
