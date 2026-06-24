<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice-2 PR-2.1: a management agreement (owner ↔ manager). landlord_id is the
 * managing account (TenantScope key — landlord OR manager). rendered_body +
 * content_hash are the canonical signed snapshot; the structured clause params
 * live on agreement_clauses. The fee thread (apply + lock) wires in PR 2.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_owner_id')->constrained('property_owners')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('title')->nullable();
            $table->longText('rendered_body')->nullable();
            $table->string('content_hash')->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_agreements');
    }
};
