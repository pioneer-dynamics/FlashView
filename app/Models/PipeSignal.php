<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipeSignal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pipe_session_id',
        'role',
        'type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function pipeSession(): BelongsTo
    {
        return $this->belongsTo(PipeSession::class);
    }
}
