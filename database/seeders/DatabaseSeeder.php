<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Models\Driver;
use App\Models\Conductor;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Halte;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@busflow.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // 2. Create Operator
        User::create([
            'name' => 'Operator Pusat',
            'email' => 'operator@busflow.com',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        // 3. Create Passenger
        $passenger = User::create([
            'name' => 'Budi Penumpang',
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
            'role' => 'passenger',
            'is_active' => true,
        ]);
        
        UserProfile::create([
            'user_id' => $passenger->id,
            'phone' => '081234567890',
            'birthdate' => '1995-05-15',
        ]);
        
        Wallet::create([
            'user_id' => $passenger->id,
            'balance' => 100000,
        ]);

        // 4. Create Driver
        $driverUser = User::create([
            'name' => 'Sopir Santoso',
            'email' => 'driver@busflow.com',
            'password' => Hash::make('password123'),
            'role' => 'driver',
            'is_active' => true,
        ]);
        
        Driver::create([
            'user_id' => $driverUser->id,
            'employee_id' => 'DRV-001',
            'phone' => '081234567891',
            'is_available' => true,
            'joined_at' => now(),
        ]);

        // 5. Create Conductor
        $conductorUser = User::create([
            'name' => 'Kenek Kardi',
            'email' => 'conductor@busflow.com',
            'password' => Hash::make('password123'),
            'role' => 'conductor',
            'is_active' => true,
        ]);
        
        Conductor::create([
            'user_id' => $conductorUser->id,
            'employee_id' => 'CND-001',
            'phone' => '081234567892',
            'is_available' => true,
            'joined_at' => now(),
        ]);

        // 6. Create Bus
        Bus::create([
            'plate_number' => 'B 1234 XYZ',
            'capacity' => 45,
            'year' => 2022,
            'brand' => 'Mercedes-Benz',
            'status' => true,
            'total_distance' => 15000.5,
        ]);

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
        
        $this->command->info('✅ Database telah berhasil diisi dengan Seeder dasar (User, Armada, Rute, Halte)!');
    }
}
