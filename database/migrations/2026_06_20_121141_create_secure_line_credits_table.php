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
        Schema::create('secure_line_credits', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('stripe_session_id')->unique();
            $table->foreignId('secure_line_product_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('call_session_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secure_line_credits');
    }
};
