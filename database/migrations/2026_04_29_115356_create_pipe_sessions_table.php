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
        Schema::create('pipe_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 40)->unique()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_complete')->default(false);
            $table->unsignedInteger('total_chunks')->nullable();
            $table->enum('transfer_mode', ['relay', 'p2p'])->default('relay');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipe_sessions');
    }
};
