<?php

namespace App\Models;

use App\Traits\HasHashId;
use Database\Factories\CallSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallSession extends Model
{
    /** @use HasFactory<CallSessionFactory> */
    use HasFactory, HasHashId;

    protected $fillable = [
        'public_key',
        'key_salt',
        'starts_at',
        'ends_at',
        'max_participants',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_participants' => 'integer',
            'metadata' => 'array',
        ];
    }

    #[Scope]
    protected function active($query): void
    {
        $query->where('starts_at', '<=', now())->where('ends_at', '>=', now());
    }

    #[Scope]
    protected function joinable($query): void
    {
        // TODO(PIO-118): Add participant count enforcement with pessimistic locking
        $query->active();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }

    public function isActive(): bool
    {
        return now()->between($this->starts_at, $this->ends_at);
    }

    public function isFull(): bool
    {
        return $this->participants()->count() >= $this->max_participants;
    }
}
