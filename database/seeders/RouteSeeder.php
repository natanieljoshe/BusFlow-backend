<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Route;
use App\Models\Halte;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 7. Create Route
        $route = Route::create([
            'code' => 'K1',
            'name' => 'Koridor 1: Blok M - Kota',
            'total_distance_km' => 15.5,
            'time_start' => '05:00',
            'time_end' => '22:00',
            'avg_speed' => 20.0,
            'max_freq_per_hour' => 6,
            'is_active' => true,
        ]);

        // 8. Create Haltes
        $halte1 = Halte::create(['code' => 'H-BLOKM', 'name' => 'Halte Blok M', 'latitude' => -6.2435, 'longitude' => 106.8016, 'address' => 'Terminal Blok M', 'is_active' => true]);
        $halte2 = Halte::create(['code' => 'H-SDIR', 'name' => 'Halte Sudirman', 'latitude' => -6.2223, 'longitude' => 106.8066, 'address' => 'Jalan Jenderal Sudirman', 'is_active' => true]);
        $halte3 = Halte::create(['code' => 'H-KOTA', 'name' => 'Halte Kota', 'latitude' => -6.1376, 'longitude' => 106.8146, 'address' => 'Stasiun Jakarta Kota', 'is_active' => true]);

        // 9. Attach Haltes to Route
        $route->haltes()->attach([
            $halte1->id => ['sequence' => 1, 'distance_from_prev_halte' => 0],
            $halte2->id => ['sequence' => 2, 'distance_from_prev_halte' => 4.5],
            $halte3->id => ['sequence' => 3, 'distance_from_prev_halte' => 11.0],
        ]);
    }
}
