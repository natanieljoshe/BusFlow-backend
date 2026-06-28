<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Route;
use App\Models\User;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Conductor;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class FleetAndCrewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        DB::transaction(function () use ($faker) {
            $routes = Route::all();

            foreach ($routes as $route) {
                // 1. Insert 5 Bus untuk rute saat ini
                for ($i = 0; $i < 5; $i++) {
                    Bus::create([
                        'plate_number' => strtoupper($faker->bothify('? #### ??')),
                        'capacity' => 40,
                        'year' => $faker->numberBetween(2015, 2024),
                        'brand' => $faker->randomElement(['Mercedes-Benz', 'Hino', 'Scania', 'Volvo']),
                        'status' => true,
                        'total_distance' => $faker->randomFloat(2, 1000, 50000),
                        'route_id' => $route->id,
                        // driver_id dibiarkan null secara default (atau tidak perlu di-set karena sudah dihapus dari skema)
                    ]);
                }

                // 2. Insert 10 Sopir (Driver) untuk rute saat ini
                for ($i = 0; $i < 10; $i++) {
                    // Buat User untuk Sopir
                    $driverUser = User::create([
                        'name' => $faker->name('male'),
                        'email' => 'driver_' . $route->id . '_' . $i . '_' . uniqid() . '@busflow.com',
                        'password' => Hash::make('password'),
                        'role' => 'driver',
                        'is_active' => true,
                    ]);

                    // Atur shift: 5 pertama pagi, 5 sisanya siang
                    if ($i < 5) {
                        $shiftStart = '06:00:00';
                        $shiftEnd = '14:00:00';
                    } else {
                        $shiftStart = '14:00:00';
                        $shiftEnd = '22:00:00';
                    }

                    Driver::create([
                        'user_id' => $driverUser->id,
                        'employee_id' => 'DRV-' . strtoupper($faker->unique()->lexify('?????')) . rand(100, 999),
                        'phone' => $faker->phoneNumber(),
                        'is_available' => true,
                        'joined_at' => $faker->date(),
                        'route_id' => $route->id,
                        'shift_start' => $shiftStart,
                        'shift_end' => $shiftEnd,
                    ]);
                }

                // 3. Insert 10 Kondektur (Conductor) untuk rute saat ini
                for ($i = 0; $i < 10; $i++) {
                    // Buat User untuk Kondektur
                    $conductorUser = User::create([
                        'name' => $faker->name('male'),
                        'email' => 'conductor_' . $route->id . '_' . $i . '_' . uniqid() . '@busflow.com',
                        'password' => Hash::make('password'),
                        'role' => 'conductor',
                        'is_active' => true,
                    ]);

                    // Atur shift: 5 pertama pagi, 5 sisanya siang
                    if ($i < 5) {
                        $shiftStart = '06:00:00';
                        $shiftEnd = '14:00:00';
                    } else {
                        $shiftStart = '14:00:00';
                        $shiftEnd = '22:00:00';
                    }

                    Conductor::create([
                        'user_id' => $conductorUser->id,
                        'employee_id' => 'CND-' . strtoupper($faker->unique()->lexify('?????')) . rand(100, 999),
                        'phone' => $faker->phoneNumber(),
                        'is_available' => true,
                        'joined_at' => $faker->date(),
                        'route_id' => $route->id,
                        'shift_start' => $shiftStart,
                        'shift_end' => $shiftEnd,
                    ]);
                }
            }
        });
    }
}
