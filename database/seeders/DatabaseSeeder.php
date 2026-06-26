<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            StaffSeeder::class,
            BusSeeder::class,
            BusMaintenanceLogSeeder::class,
            RouteSeeder::class,
            ScheduleSeeder::class,
            TripSeeder::class,
            TripBookingSeeder::class,
            WalletTransactionSeeder::class,
            NotificationSeeder::class,
            DriverRatingSeeder::class,
        ]);
    }
}
