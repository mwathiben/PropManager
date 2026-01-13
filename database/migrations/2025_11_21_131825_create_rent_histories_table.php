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
        Schema::create('rent_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->onDelete('cascade');
            $table->decimal('old_amount', 10, 2);
            $table->decimal('new_amount', 10, 2);
            $table->date('effective_date');
            $table->string('reason')->nullable(); // e.g. "Annual Increase"
            $table->boolean('notification_sent')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rent_histories');
    }
};
