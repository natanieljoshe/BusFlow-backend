<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Schedule;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Conductor;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $route = Route::first();
        $bus = Bus::first();
        $driver = Driver::first();
        $conductor = Conductor::first();

        if ($route && $bus && $driver && $conductor) {
            Schedule::create([
                'valid_from' => now()->subMonths(1)->toDateString(),
                'valid_until' => now()->addMonths(5)->toDateString(),
                'is_active' => true,
                'fitness_score' => 0.95,
                'total_penalty' => 0.05,
                'avg_wait_min' => 10.5,
                'passenger_served' => 1500,
            ]);
        }
    }
}
