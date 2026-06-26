<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Driver;
use App\Models\Conductor;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
    }
}
