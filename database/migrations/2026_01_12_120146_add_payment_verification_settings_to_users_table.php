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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('require_payment_before_access')->default(true)->after('onboarding_complete');
            $table->boolean('auto_verify_payments')->default(false)->after('require_payment_before_access');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['require_payment_before_access', 'auto_verify_payments']);
        });
    }
};
