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
            $table->text('filename')->nullable()->change();
            $table->unsignedBigInteger('file_size')->nullable()->after('filename');
            $table->string('file_mime_type', 100)->nullable()->after('file_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('secrets', function (Blueprint $table) {
            $table->string('filename')->nullable()->change();
            $table->dropColumn(['file_size', 'file_mime_type']);
        });
    }
};
