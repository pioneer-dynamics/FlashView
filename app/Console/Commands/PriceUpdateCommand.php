<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Laravel\Cashier\Cashier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PriceUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear pricing cache and update cache from Stripe pricing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Plan::get()->each(function ($plan) {
            $plan->update([
                'price_per_month' => !blank($plan->stripe_monthly_price_id) ? Cashier::stripe()->prices->retrieve($plan->stripe_monthly_price_id)->unit_amount / 100 : 0,
                'price_per_year' => !blank($plan->stripe_yearly_price_id) ? Cashier::stripe()->prices->retrieve($plan->stripe_yearly_price_id)->unit_amount / 100 : 0
            ]);
        });
    }
}
