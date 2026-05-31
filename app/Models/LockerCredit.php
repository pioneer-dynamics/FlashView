<?php

namespace App\Models;

use Database\Factories\LockerCreditFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LockerCredit extends Model
{
    /** @use HasFactory<LockerCreditFactory> */
    use HasFactory;

    protected $fillable = [
        'token',
        'tier',
        'years',
        'stripe_session_id',
        'locker_id',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(Locker::class);
    }

    public function isUsed(): bool
    {
        return filled($this->used_at);
    }

    #[Scope]
    protected function unused($query): void
    {
        $query->whereNull('used_at');
    }
}
