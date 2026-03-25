<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Plan::all()->each(function (Plan $plan) {
            $features = $plan->features;

            if (isset($features['notification']['config']['notifications'])) {
                $features['notification']['config']['email'] = $features['notification']['config']['notifications'];
                unset($features['notification']['config']['notifications']);
            }

            if (isset($features['notification']['config']) && ! isset($features['notification']['config']['webhook'])) {
                $features['notification']['config']['webhook'] = false;
            }

            if ($features !== $plan->features) {
                $plan->update(['features' => $features]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Plan::all()->each(function (Plan $plan) {
            $features = $plan->features;

            if (isset($features['notification']['config']['email'])) {
                $features['notification']['config']['notifications'] = $features['notification']['config']['email'];
                unset($features['notification']['config']['email']);
            }

            if (isset($features['notification']['config']['webhook'])) {
                unset($features['notification']['config']['webhook']);
            }

            if ($features !== $plan->features) {
                $plan->update(['features' => $features]);
            }
        });
    }
};
