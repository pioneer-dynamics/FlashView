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
        Schema::create('pipe_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipe_session_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['sender', 'receiver']);
            $table->string('type');
            $table->longText('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pipe_session_id', 'role', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipe_signals');
    }
};
