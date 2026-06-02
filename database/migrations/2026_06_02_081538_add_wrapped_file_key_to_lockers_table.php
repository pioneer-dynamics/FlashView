<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lockers', function (Blueprint $table) {
            $table->text('wrapped_file_key')->nullable()->after('storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('lockers', function (Blueprint $table) {
            $table->dropColumn('wrapped_file_key');
        });
    }
};
