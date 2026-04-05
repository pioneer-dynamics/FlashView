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
            $table->text('sender_company_name')->nullable()->after('masked_recipient_email');
            $table->text('sender_domain')->nullable()->after('sender_company_name');
            $table->text('sender_email')->nullable()->after('sender_domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('secrets', function (Blueprint $table) {
            $table->dropColumn(['sender_company_name', 'sender_domain', 'sender_email']);
        });
    }
};
