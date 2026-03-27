<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('type')->default('api')->after('name');
        });

        DB::table('personal_access_tokens')
            ->where('name', 'FlashView CLI')
            ->update(['type' => 'cli']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('personal_access_tokens')
            ->where('type', 'cli')
            ->whereNot('name', 'FlashView CLI')
            ->update(['name' => 'FlashView CLI']);

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
