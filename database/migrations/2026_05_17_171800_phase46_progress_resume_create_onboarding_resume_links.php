<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-46 PROGRESS-RESUME-1: append-only audit of signed onboarding-
 * resume URLs. Each row captures issuance + consumption so re-issuance
 * is observable, and replay detection is mechanical (consumed_at NOT
 * NULL ⇒ consumed once; subsequent hits via the same signed link
 * return 403).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_resume_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onboarding_session_id')->constrained()->cascadeOnDelete();
            $table->string('signature_hash', 128)->index();
            $table->timestamp('signed_until');
            $table->timestamp('generated_at');
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consumed_at')->nullable();
            $table->string('consumed_from_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['onboarding_session_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_resume_links');
    }
};
