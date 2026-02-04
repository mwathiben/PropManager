<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique();
            $table->string('request_hash', 64)->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('response_data')->nullable();
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();

            $table->index(['status', 'expires_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
