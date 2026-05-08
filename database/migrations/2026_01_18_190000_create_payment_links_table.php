<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('clicked_at')->nullable();
            $table->string('clicked_ip', 45)->nullable();
            $table->string('utm_source', 50)->nullable();
            $table->string('utm_medium', 50)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->index(['invoice_id', 'is_revoked']);
            $table->index(['landlord_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
