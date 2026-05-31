<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lockers', function (Blueprint $table) {
            $table->longText('payload')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lockers', function (Blueprint $table) {
            $table->longText('payload')->nullable(false)->change();
        });
    }
};
