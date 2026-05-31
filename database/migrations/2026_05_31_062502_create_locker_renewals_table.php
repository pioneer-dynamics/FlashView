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
        Schema::create('locker_renewals', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_session_id')->unique();
            $table->char('account_id', 10);
            $table->tinyInteger('years');
            $table->timestamp('processed_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locker_renewals');
    }
};
