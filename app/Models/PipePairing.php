<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipePairing extends Model
{
    protected $fillable = [
        'sender_device_id',
        'receiver_device_id',
        'encrypted_seed',
        'is_accepted',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_accepted' => 'boolean',
        ];
    }

    public function senderDevice(): BelongsTo
    {
        return $this->belongsTo(PipeDevice::class, 'sender_device_id');
    }

    public function receiverDevice(): BelongsTo
    {
        return $this->belongsTo(PipeDevice::class, 'receiver_device_id');
    }
}
