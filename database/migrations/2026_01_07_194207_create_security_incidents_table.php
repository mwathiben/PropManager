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
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // data_breach, unauthorized_access, malware, etc.
            $table->string('severity', 20); // low, medium, high, critical
            $table->text('description');
            $table->json('affected_data_types')->nullable();
            $table->integer('estimated_affected_users')->default(0);
            $table->text('mitigation_measures')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at');
            $table->timestamp('notification_deadline'); // 72 hours from report
            $table->timestamp('odpc_notified_at')->nullable(); // Kenya DPA: ODPC notification
            $table->timestamp('users_notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('status', 20)->default('reported');
            $table->text('resolution_notes')->nullable();
            $table->json('compliance_references')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('severity');
            $table->index('status');
            $table->index('notification_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
    }
};
