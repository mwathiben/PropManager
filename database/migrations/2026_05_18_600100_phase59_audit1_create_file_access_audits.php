<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-59 ACCESS-AUDIT-1: PII-bearing file downloads need an audit
 * trail. Polymorphic subject FK so KYC docs, lease docs, water-
 * reading photos, tenant docs all log through one table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_access_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 191)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action', 32);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('accessed_path', 1024)->nullable();
            $table->timestamp('accessed_at')->index();
            $table->timestamps();

            $table->index(['landlord_id', 'accessed_at'], 'faa_landlord_accessed');
            $table->index(['user_id', 'accessed_at'], 'faa_user_accessed');
            $table->index(['subject_type', 'subject_id'], 'faa_subject_polymorphic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_access_audits');
    }
};
