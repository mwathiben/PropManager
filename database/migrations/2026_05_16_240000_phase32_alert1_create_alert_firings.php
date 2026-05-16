<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-32 SRE-ALERT-1: every alert firing keyed on alert_key. Used by:
 *   - alert:quality cron to compute signal-to-noise ratio per alert key
 *   - operator dashboard to surface acknowledgement state
 *   - workflow:health silent-failure detector via LogAlertFiring listener
 *
 * fired_at + resolved_at let us compute alert duration (transient blip
 * vs sustained outage). acknowledged_by_user_id + note are the human
 * audit trail so a "noise" alert can be retired by the operator who
 * verified it was a false positive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_firings', function (Blueprint $table) {
            $table->id();
            $table->string('alert_key', 100);
            $table->string('severity', 16);
            $table->double('value');
            $table->double('threshold');
            $table->timestamp('fired_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgement_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['alert_key', 'fired_at'], 'af_key_fired_idx');
            $table->index(['alert_key', 'resolved_at'], 'af_key_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_firings');
    }
};
