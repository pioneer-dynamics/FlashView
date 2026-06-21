<?php

namespace App\Models;

use Database\Factories\SecureLineCreditFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecureLineCredit extends Model
{
    /** @use HasFactory<SecureLineCreditFactory> */
    use HasFactory;

    protected $fillable = [
        'token',
        'stripe_session_id',
        'secure_line_product_id',
        'call_session_id',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function secureLineProduct(): BelongsTo
    {
        return $this->belongsTo(SecureLineProduct::class);
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
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
