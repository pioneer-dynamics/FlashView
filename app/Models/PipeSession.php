<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipeSession extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'is_complete',
        'total_chunks',
        'transfer_mode',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_complete' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(PipeChunk::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(PipeSignal::class);
    }
}
