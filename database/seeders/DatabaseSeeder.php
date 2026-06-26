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
        $this->call(UserSeeder::class);

        $this->call(RealDataSeeder::class);

        if (\Illuminate\Support\Facades\DB::table('drivers')->count() == 0) {
            $this->call(StaffSeeder::class);
        }

        if (\Illuminate\Support\Facades\DB::table('buses')->count() == 0) {
            $this->call(BusSeeder::class);
        }

        if (\Illuminate\Support\Facades\DB::table('routes')->count() == 0) {
            $this->call(RouteSeeder::class);
        }

        $this->call([
            BusMaintenanceLogSeeder::class,
            ScheduleSeeder::class,
            TripSeeder::class,
            TripBookingSeeder::class,
            WalletTransactionSeeder::class,
            NotificationSeeder::class,
            DriverRatingSeeder::class,
        ]);
    }
}
