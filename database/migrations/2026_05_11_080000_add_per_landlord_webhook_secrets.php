<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRYPTO-11: move bank webhook secrets off platform-wide env values to
 * per-landlord encrypted columns. A single secret leak today compromises
 * EVERY landlord's bank webhook authenticity; per-landlord secrets
 * contain blast radius to the affected landlord only.
 *
 * Columns are nullable text — the cast on the model is 'encrypted', so
 * ciphertext can run long. Null means "fall back to env secret" during
 * cutover; once every landlord with a bank configured has populated
 * their own secret, the env value can be retired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->text('coop_webhook_secret')->nullable()->after('bank_branch');
            $table->text('equity_webhook_secret')->nullable()->after('coop_webhook_secret');
            $table->text('kcb_webhook_secret')->nullable()->after('equity_webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->dropColumn(['coop_webhook_secret', 'equity_webhook_secret', 'kcb_webhook_secret']);
        });
    }
};
