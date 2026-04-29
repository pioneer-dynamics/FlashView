<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipeDevice extends Model
{
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
}
