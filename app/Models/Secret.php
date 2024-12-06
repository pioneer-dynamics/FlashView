<?php

namespace App\Models;

use App\Traits\HasHashId;
use App\Models\Scopes\ActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[ScopedBy([ActiveScope::class])]
class Secret extends Model
{
    /** @use HasFactory<\Database\Factories\SecretFactory> */
    use HasFactory;
    use HasHashId;

    protected $fillable = [
        'message',
        'filepath',
        'filename',
        'expires_at',
    ];

    protected function casts()
    {
        return [
            'expires_at' => 'datetime',
            'retrieved_at' => 'datetime',
            'message' => 'encrypted',
            'ip_address_sent' => 'encrypted',
            'ip_address_retrieved' => 'encrypted',
        ];
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeReadyToPrune($query)
    {
        return $query->where('expires_at', '<', now()->subDays(config('secrets.prune_after')))->where('message', null);
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>=', now())->where('message', '!=', null);
    }

    public static function booted()
    {
        static::creating(function (Secret $secret) {
            $secret->expires_at = $secret->expires_at ?? now()->addMinutes(config('secrets.expiry'));
            $secret->ip_address_sent = request()->ip();
        });

        static::retrieved(function (Secret $secret) {
            $secret->forceFill([
                'retrieved_at' => now(),
                'ip_address_retrieved' => request()->ip(),
            ])->save();
        });
    }
}
