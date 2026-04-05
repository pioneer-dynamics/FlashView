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
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
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
}
