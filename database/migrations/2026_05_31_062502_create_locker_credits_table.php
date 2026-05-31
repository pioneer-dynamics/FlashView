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
        Schema::create('locker_credits', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->enum('tier', ['text', 'file']);
            $table->tinyInteger('years');
            $table->string('stripe_session_id')->nullable()->unique();
            $table->foreignId('locker_id')->nullable()->constrained('lockers')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locker_credits');
    }
};
