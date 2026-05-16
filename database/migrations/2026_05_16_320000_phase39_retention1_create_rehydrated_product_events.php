<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehydrated_product_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('original_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('landlord_id')->nullable();
            $table->string('event_name', 64);
            $table->json('properties')->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('rehydrated_at');
            $table->string('source_path', 255);
            $table->index(['landlord_id', 'original_created_at'], 'rpe_landlord_created_idx');
            $table->index(['rehydrated_at'], 'rpe_rehydrated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rehydrated_product_events');
    }
};
