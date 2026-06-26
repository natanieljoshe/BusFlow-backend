<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DriverRating;
use App\Models\Trip;
use App\Models\User;
use App\Models\Driver;

class DriverRatingSeeder extends Seeder
{
    public function run(): void
    {
        $passenger = User::where('role', 'passenger')->first();
        $booking = \App\Models\TripBooking::where('status', 'completed')->first();
        $driver = Driver::first();

        if ($passenger && $booking && $driver) {
            DriverRating::create([
                'trip_booking_id' => $booking->id,
                'user_id' => $passenger->id,
                'driver_id' => $driver->id,
                'rating' => 5,
                'review' => 'Sopir sangat ramah, mengendarai bus dengan aman, dan tepat waktu!',
                'created_at' => now()->subHours(2),
            ]);
        }
    }
}
