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
        Schema::create('call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_session_id')->constrained()->cascadeOnDelete();
            $table->uuid('from_participant_id');
            $table->uuid('to_participant_id');
            $table->enum('type', ['offer', 'answer', 'ice-candidate', 'key-exchange']);
            $table->longText('payload'); // longText chosen over json — SDP blobs can exceed MySQL's 65 535-byte json column limit
            $table->timestamp('created_at')->useCurrent();

            // Efficient poll: all signals in a session addressed to me, in order
            $table->index(['call_session_id', 'to_participant_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_signals');
    }
};
