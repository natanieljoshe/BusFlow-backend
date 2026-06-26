<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
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
    }
}
