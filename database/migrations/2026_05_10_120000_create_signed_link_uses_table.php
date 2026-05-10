<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RATE-9: signed-link replay-protection nonce table.
     *
     * Pre-fix the email/preferences and email/unsubscribe routes used
     * Laravel's Url::signedRoute, which is HMAC-validated but reusable
     * up to the route's expiry. A leaked URL (e.g. forwarded mail) was
     * a click-jacking opportunity for the configured TTL.
     *
     * Each row records sha256(signature). The signed.once middleware
     * inserts on first use and rejects any later request with the same
     * signature. expires_at lets a daily janitor prune consumed rows.
     */
    public function up(): void
    {
        Schema::create('signed_link_uses', function (Blueprint $table) {
            $table->id();
            $table->string('signature_hash', 64)->unique();
            $table->string('route', 191)->nullable();
            $table->timestamp('consumed_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signed_link_uses');
    }
};
