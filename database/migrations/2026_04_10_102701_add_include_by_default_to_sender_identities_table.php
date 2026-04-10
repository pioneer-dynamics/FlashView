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
        Schema::table('sender_identities', function (Blueprint $table) {
            $table->boolean('include_by_default')->default(false)->after('verification_retry_dispatched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sender_identities', function (Blueprint $table) {
            $table->dropColumn('include_by_default');
        });
    }
};
