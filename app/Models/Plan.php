<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'features',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'stripe_product_id',
        'price_per_month',
        'price_per_year',
        'is_free_plan',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_free_plan' => 'boolean',
        ];
    }
}
