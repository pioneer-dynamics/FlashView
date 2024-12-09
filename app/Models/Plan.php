<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'price_per_month',
        'price_per_year',
        'features',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'stripe_product_id',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
        ];
    }
}
