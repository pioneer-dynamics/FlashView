<?php

namespace App\Models;

use Database\Factories\LockerFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Locker extends Model
{
    /** @use HasFactory<LockerFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'payload',
        'storage_path',
        'wrapped_file_key',
        'auth_challenge',
        'auth_verifier',
        'update_token_hash',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'wrapped_file_key' => 'encrypted',
        ];
    }

    #[Scope]
    protected function active($query): void
    {
        $query->where('expires_at', '>', now());
    }

    #[Scope]
    protected function expired($query): void
    {
        $query->where('expires_at', '<=', now());
    }

    public function isFileLocker(): bool
    {
        return filled($this->storage_path);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function verifyUpdateToken(string $token): bool
    {
        return hash_equals($this->update_token_hash, hash('sha256', $token));
    }

    public function verifyAuthVerifier(string $verifier): bool
    {
        return hash_equals($this->auth_verifier, $verifier);
    }
}
