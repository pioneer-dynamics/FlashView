<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Plan::all()->each(function (Plan $plan) {
            $features = $plan->features ?? [];

            $type = $plan->name === 'Prime' ? 'feature' : 'missing';

            $features['sender_identity'] = [
                'order' => 7,
                'label' => 'Verified Sender Identity',
                'config' => [],
                'type' => $type,
            ];

            $plan->update(['features' => $features]);
        });
    }

    public function down(): void
    {
        Plan::all()->each(function (Plan $plan) {
            $features = $plan->features ?? [];
            unset($features['sender_identity']);
            $plan->update(['features' => $features]);
        });
    }
};
