<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RealDataSeeder extends Seeder
{
    public function run(): void
    {
        $payloadPath = __DIR__ . '/payload.JSON';
        if (!file_exists($payloadPath)) {
            $this->command->error("payload.JSON not found at $payloadPath");
            return;
        }

        $json = file_get_contents($payloadPath);
        $data = json_decode($json, true);

        // 1. Seed Routes
        foreach ($data['routes'] ?? [] as $route) {
            DB::table('routes')->updateOrInsert(
                ['code' => $route['id']],
                [
                    'name' => $route['name'],
                    'estimated_travel_time_min' => $route['estimated_travel_time_min'],
                    'total_distance_km' => $route['total_distance_km'],
                    'avg_speed' => $route['avg_speed'],
                    'origin_stop_id' => $route['origin_stop_id'],
                    'destination_stop_id' => $route['destination_stop_id'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 2. Seed Haltes & Route_Haltes
        foreach ($data['route_stops'] ?? [] as $stop) {
            DB::table('haltes')->updateOrInsert(
                ['code' => $stop['stop_id']],
                [
                    'name' => $stop['stop_name'],
                    'latitude' => $stop['latitude'],
                    'longitude' => $stop['longitude'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $routeDb = DB::table('routes')->where('code', $stop['route_id'])->first();
            $halteDb = DB::table('haltes')->where('code', $stop['stop_id'])->first();

            if ($routeDb && $halteDb) {
                DB::table('route_haltes')->updateOrInsert(
                    [
                        'route_id' => $routeDb->id,
                        'halte_id' => $halteDb->id,
                        'sequence' => $stop['sequence']
                    ],
                    [
                        'distance_from_prev_halte' => $stop['distance_from_prev_stop'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // 3. Seed Buses
        foreach ($data['buses'] ?? [] as $bus) {
            DB::table('buses')->updateOrInsert(
                ['plate_number' => $bus['id']],
                [
                    'capacity' => $bus['capacity'],
                    'total_distance' => $bus['total_distance'],
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 4. Seed Drivers
        foreach ($data['drivers'] ?? [] as $driver) {
            $email = strtolower($driver['id']) . '@busflow.com';
            $userDb = DB::table('users')->where('email', $email)->first();
            if (!$userDb) {
                $userId = DB::table('users')->insertGetId([
                    'name' => 'Driver ' . $driver['id'],
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => 'driver',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $userId = $userDb->id;
            }

            DB::table('drivers')->updateOrInsert(
                ['employee_id' => $driver['id']],
                [
                    'user_id' => $userId,
                    'is_available' => $driver['status'] === 'active' ? 1 : 0,
                    'shift_start' => $driver['shift_start'] ?? null,
                    'shift_end' => $driver['shift_end'] ?? null,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 5. Seed Conductors
        foreach ($data['conductors'] ?? [] as $cond) {
            $email = strtolower($cond['id']) . '@busflow.com';
            $userDb = DB::table('users')->where('email', $email)->first();
            if (!$userDb) {
                $userId = DB::table('users')->insertGetId([
                    'name' => 'Conductor ' . $cond['id'],
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => 'conductor',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $userId = $userDb->id;
            }

            DB::table('conductors')->updateOrInsert(
                ['employee_id' => $cond['id']],
                [
                    'user_id' => $userId,
                    'is_available' => $cond['status'] === 'active' ? 1 : 0,
                    'shift_start' => $cond['shift_start'] ?? null,
                    'shift_end' => $cond['shift_end'] ?? null,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 6. Random Assignments (Buses to Routes, Drivers/Conductors to Buses)
        $routeIds = DB::table('routes')->pluck('id')->toArray();
        if (!empty($routeIds)) {
            $buses = DB::table('buses')->get();
            foreach ($buses as $bus) {
                if (rand(1, 100) <= 80) { // 80% assigned
                    DB::table('buses')->where('id', $bus->id)->update(['route_id' => $routeIds[array_rand($routeIds)]]);
                }
            }
        }

        $busIds = DB::table('buses')->pluck('id')->toArray();
        if (!empty($busIds)) {
            $drivers = DB::table('drivers')->get();
            foreach ($drivers as $driver) {
                if (rand(1, 100) <= 80) {
                    DB::table('drivers')->where('id', $driver->id)->update(['bus_id' => $busIds[array_rand($busIds)]]);
                }
            }

            $conductors = DB::table('conductors')->get();
            foreach ($conductors as $cond) {
                if (rand(1, 100) <= 80) {
                    DB::table('conductors')->where('id', $cond->id)->update(['bus_id' => $busIds[array_rand($busIds)]]);
                }
            }
        }

        $this->command->info('RealDataSeeder ran successfully!');
    }
}
