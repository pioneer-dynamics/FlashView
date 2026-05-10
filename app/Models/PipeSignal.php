<?php

namespace App\Models;

use Database\Factories\PipeSignalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipeSignal extends Model
{
    /** @use HasFactory<PipeSignalFactory> */
    use HasFactory;

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
