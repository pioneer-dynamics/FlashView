<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lockers', function (Blueprint $table) {
            // Use text (not longText) — JWK base64 is ~500 chars, well within text limit
            $table->text('public_key')->nullable()->after('update_token_hash');

            // Make legacy auth columns nullable — ECDSA lockers don't populate these.
            // Without this, store() throws a DB integrity violation for new ECDSA lockers.
            $table->string('auth_challenge', 64)->nullable()->change();
            $table->string('auth_verifier', 64)->nullable()->change();
            $table->string('update_token_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        // WARNING: Restoring NOT NULL constraints will fail if any ECDSA lockers
        // exist (they have NULL in these columns). Only run this rollback before
        // any ECDSA lockers have been created, or after deleting/backfilling them.
        Schema::table('lockers', function (Blueprint $table) {
            $table->dropColumn('public_key');
            $table->string('auth_challenge', 64)->nullable(false)->change();
            $table->string('auth_verifier', 64)->nullable(false)->change();
            $table->string('update_token_hash', 64)->nullable(false)->change();
        });
    }
};
