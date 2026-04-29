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
        Schema::create('pipe_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipe_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['pipe_session_id', 'chunk_index']);
            $table->index(['pipe_session_id', 'chunk_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipe_chunks');
    }
};
