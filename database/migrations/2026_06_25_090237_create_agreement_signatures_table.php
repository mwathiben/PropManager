<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice-2 PR-2.3c: the owner's in-house e-signature — both the signing
 * invitation (a single-use token emailed to the owner) AND the tamper-evident
 * evidence bundle filled on signing (who, when, from where, which snapshot, and
 * that an SMS OTP was verified). One row per send; signing it drives the
 * agreement Sent -> Signed -> active (AgreementApplicator).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('pending');

            // Signer identity, snapshotted from the owner contact at send time so
            // the evidence is self-contained even if the owner record later changes.
            $table->string('signer_name');
            $table->string('signer_email')->nullable();
            $table->string('signer_phone')->nullable();

            // Evidence, filled on signing.
            $table->string('content_hash')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->string('signed_user_agent')->nullable();

            $table->timestamps();

            $table->index(['landlord_id', 'status']);
            $table->index('management_agreement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_signatures');
    }
};
