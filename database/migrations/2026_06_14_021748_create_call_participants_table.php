<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('call_session_id')->constrained()->cascadeOnDelete();
            $table->text('public_key')->nullable(); // ECDH public key — populated in PIO-115 key exchange
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->text('ip_address'); // encrypted via model cast — text required for ciphertext length
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_participants');
    }
};
