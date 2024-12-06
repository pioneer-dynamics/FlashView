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
        Schema::table('secrets', function (Blueprint $table) {
            $table->text('ip_address_sent')->nullable();
            $table->text('ip_address_retrieved')->nullable();
            $table->datetime('retrieved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('secrets', function (Blueprint $table) {
            $table->dropColumn('ip_address_sent');
            $table->dropColumn('ip_address_retrieved');
            $table->dropColumn('retrieved_at');
        });
    }
};
