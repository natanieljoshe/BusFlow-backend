<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Conductor;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $route = Route::first();
        $bus = Bus::first();
        $driver = Driver::first();
        $conductor = Conductor::first();

        $schedule = \App\Models\Schedule::first();

        if ($route && $bus && $driver && $conductor && $schedule) {
            Trip::create([
                'schedule_id' => $schedule->id,
                'route_id' => $route->id,
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'conductor_id' => $conductor->id,
                'departure_time' => '06:00:00',
                'estimated_arrival' => '07:30:00',
                'is_active' => false,
            ]);

            Trip::create([
                'schedule_id' => $schedule->id,
                'route_id' => $route->id,
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'conductor_id' => $conductor->id,
                'departure_time' => '08:00:00',
                'estimated_arrival' => '09:30:00',
                'is_active' => true,
            ]);

            Trip::create([
                'schedule_id' => $schedule->id,
                'route_id' => $route->id,
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'conductor_id' => $conductor->id,
                'departure_time' => '10:00:00',
                'estimated_arrival' => '11:30:00',
                'is_active' => true,
            ]);
        }
    }
}
