<?php

namespace App\Models;

use Database\Factories\PipeChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipeChunk extends Model
{
    /** @use HasFactory<PipeChunkFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'pipe_session_id',
        'chunk_index',
        'payload',
    ];

    public function pipeSession(): BelongsTo
    {
        return $this->belongsTo(PipeSession::class);
    }
}
