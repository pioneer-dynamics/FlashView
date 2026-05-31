<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LockerPlan extends Model
{
    protected $fillable = [
        'tier',
        'years',
        'file_size_mb',
        'amount_cents',
        'stripe_price_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function amountDollars(): float
    {
        return $this->amount_cents / 100;
    }
}
