<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LockerRenewal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stripe_session_id',
        'account_id',
        'years',
        'processed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
