<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();                                                // auto-increment bigint — encoded as hash_id, never exposed as integer
            $table->text('public_key');                                  // Ed25519 public key (base64, 32 bytes) — server never sees the password
            $table->string('key_salt', 64);                             // PBKDF2 salt (base64, 32 bytes) — returned by challenge endpoint
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedTinyInteger('max_participants')->default(2);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
