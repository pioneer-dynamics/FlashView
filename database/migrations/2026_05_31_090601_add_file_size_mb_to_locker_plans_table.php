<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locker_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('file_size_mb')->nullable()->after('years');
        });
    }

    public function down(): void
    {
        Schema::table('locker_plans', function (Blueprint $table) {
            $table->dropColumn('file_size_mb');
        });
    }
};
