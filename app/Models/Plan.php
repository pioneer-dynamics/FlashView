<?php

namespace App\Models;

use Laravel\Cashier\Cashier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'features',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'stripe_product_id',
        'price_per_month',
        'price_per_year',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
        ];
    }
}
