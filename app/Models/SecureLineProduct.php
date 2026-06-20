<?php

namespace App\Models;

use Database\Factories\SecureLineProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecureLineProduct extends Model
{
    /** @use HasFactory<SecureLineProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'duration_minutes',
        'max_participants',
        'amount_cents',
        'stripe_price_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration_minutes' => 'integer',
            'max_participants' => 'integer',
            'amount_cents' => 'integer',
        ];
    }

    public function amountDollars(): float
    {
        return $this->amount_cents / 100;
    }
}
