<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipe_sessions', function (Blueprint $table): void {
            $table->foreignId('sender_device_id')->nullable()->constrained('pipe_devices')->nullOnDelete()->after('user_id');
            $table->foreignId('receiver_device_id')->nullable()->constrained('pipe_devices')->nullOnDelete()->after('sender_device_id');
            $table->text('encrypted_transfer_key')->nullable()->after('receiver_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('pipe_sessions', function (Blueprint $table): void {
            $table->dropForeign(['sender_device_id']);
            $table->dropForeign(['receiver_device_id']);
            $table->dropColumn(['sender_device_id', 'receiver_device_id', 'encrypted_transfer_key']);
        });
    }
};
