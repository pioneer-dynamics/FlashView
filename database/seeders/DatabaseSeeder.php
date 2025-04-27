<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $plan_seeder = app()->environment('production') ? PlanSeederProd::class : PlanSeederLocal::class;
        
        $this->call([
            $plan_seeder
        ]);
    }
}
