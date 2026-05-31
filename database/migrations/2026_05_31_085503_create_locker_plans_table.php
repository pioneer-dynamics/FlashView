<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locker_plans', function (Blueprint $table) {
            $table->id();
            $table->enum('tier', ['text', 'file']);
            $table->tinyInteger('years');
            $table->unsignedInteger('amount_cents');
            $table->string('stripe_price_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tier', 'years']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locker_plans');
    }
};
