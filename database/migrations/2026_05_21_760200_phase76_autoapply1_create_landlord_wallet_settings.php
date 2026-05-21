<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-76 WALLET-DEEP AUTO-APPLY-1: per-landlord wallet auto-apply mode.
 * Absent row => config('wallet.default_auto_apply_mode').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_wallet_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('auto_apply_mode', 32)->default('on_invoice_create');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_wallet_settings');
    }
};
