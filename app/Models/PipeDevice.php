<?php

namespace App\Models;

use Database\Factories\PipeDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipeDevice extends Model
{
    /** @use HasFactory<PipeDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'public_key',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pairings(): HasMany
    {
        return $this->hasMany(PipePairing::class, 'receiver_device_id');
    }

    public function sentSessions(): HasMany
    {
        return $this->hasMany(PipeSession::class, 'sender_device_id');
    }

    public function receivedSessions(): HasMany
    {
        return $this->hasMany(PipeSession::class, 'receiver_device_id');
    }
}
