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
            'message' => 'encrypted',
        ];
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>=', now());
    }

    public static function booted()
    {
        static::creating(function (Secret $secret) {
            $secret->expires_at = $secret->expires_at ?? now()->addMinutes(config('secrets.expiry'));
        });

        static::retrieved(function (Secret $secret) {
            $secret->delete();
        });
    }
}
