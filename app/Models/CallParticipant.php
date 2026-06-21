<?php

namespace App\Models;

use Database\Factories\CallParticipantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallParticipant extends Model
{
    /** @use HasFactory<CallParticipantFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'call_session_id',
        'public_key',
        'joined_at',
        'left_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'ip_address' => 'encrypted',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CallSession::class, 'call_session_id');
    }
}
