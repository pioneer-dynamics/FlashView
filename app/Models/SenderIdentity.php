<?php

namespace App\Models;

use Database\Factories\SenderIdentityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenderIdentity extends Model
{
    /** @use HasFactory<SenderIdentityFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'company_name',
        'domain',
        'email',
        'verification_token',
        'verified_at',
        'verification_retry_dispatched_at',
        'include_by_default',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'verification_retry_dispatched_at' => 'datetime',
            'include_by_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isDomainType(): bool
    {
        return $this->type === 'domain';
    }

    public function isEmailType(): bool
    {
        return $this->type === 'email';
    }

    public function hasActiveRetry(): bool
    {
        return $this->verification_retry_dispatched_at !== null;
    }
}
