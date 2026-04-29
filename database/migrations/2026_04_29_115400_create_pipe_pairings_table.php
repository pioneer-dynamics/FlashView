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
        Schema::create('pipe_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_device_id')->constrained('pipe_devices')->cascadeOnDelete();
            $table->foreignId('receiver_device_id')->constrained('pipe_devices')->cascadeOnDelete();
            $table->text('encrypted_seed');
            $table->boolean('is_accepted')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipe_pairings');
    }
};
