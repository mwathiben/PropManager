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
        // documenso_recipient_token is a live signing-session bearer credential and
        // is now stored with the model's `encrypted` cast — the ciphertext is far
        // longer than the original string(100), so widen to text. (Column is empty
        // on main; 2.4b-ii is the first writer.)
        Schema::table('agreement_signatures', function (Blueprint $table) {
            $table->text('documenso_recipient_token')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreement_signatures', function (Blueprint $table) {
            $table->string('documenso_recipient_token', 100)->nullable()->change();
        });
    }
};
