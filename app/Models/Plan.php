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
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_free_plan' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Returns true when today falls within the plan's availability window.
     * Comparison uses app.timezone (config). Dates are entered as bare YYYY-MM-DD
     * and stored without time. `startOfDay()` anchors the comparison to midnight
     * in the server timezone so the full calendar day is included.
     * Note: if the server is UTC and the admin is in a UTC+N timezone, a plan set
     * to expire on "2026-05-01" will close at midnight UTC — earlier than local midnight.
     */
    public function isCurrentlyAvailable(): bool
    {
        $today = now()->startOfDay();

        if ($this->start_date && $today->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $today->gt($this->end_date)) {
            return false;
        }

        return true;
    }
}
