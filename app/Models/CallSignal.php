<?php

namespace App\Models;

use Database\Factories\CallSignalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallSignal extends Model
{
    /** @use HasFactory<CallSignalFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'call_session_id',
        'from_participant_id',
        'to_participant_id',
        'type',
        'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CallSession::class, 'call_session_id');
    }
}
