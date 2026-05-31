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
        Schema::create('lockers', function (Blueprint $table) {
            $table->id();
            $table->char('account_id', 10)->unique();
            $table->longText('payload');
            $table->string('storage_path')->nullable();
            $table->string('auth_challenge', 64);
            $table->string('auth_verifier', 64);
            $table->string('update_token_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lockers');
    }
};
