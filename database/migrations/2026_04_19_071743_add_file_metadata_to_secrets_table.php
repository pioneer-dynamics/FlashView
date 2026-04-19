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
            // filename is intentionally left as text — reverting to string(255)
            // would truncate encrypted filename ciphertext values already stored.
            $table->dropColumn(['file_size', 'file_mime_type']);
        });
    }
};
