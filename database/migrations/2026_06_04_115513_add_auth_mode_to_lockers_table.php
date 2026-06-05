<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lockers', function (Blueprint $table) {
            $table->string('auth_mode', 20)->default('passphrase')->after('expires_at');
            $table->unsignedSmallInteger('key_file_count')->nullable()->after('auth_mode');
        });
    }

    public function down(): void
    {
        Schema::table('lockers', function (Blueprint $table) {
            $table->dropColumn(['auth_mode', 'key_file_count']);
        });
    }
};
