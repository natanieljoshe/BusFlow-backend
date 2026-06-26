<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TripBooking;
use App\Models\Trip;
use App\Models\User;
use App\Models\Route;

class TripBookingSeeder extends Seeder
{
    public function run(): void
    {
        $passenger = User::where('role', 'passenger')->first();
        $completedTrip = Trip::where('is_active', false)->first();
        $activeTrip = Trip::where('is_active', true)->first();
        $route = Route::with('haltes')->first();

        if ($passenger && $route && count($route->haltes) >= 2) {
            $halteStart = $route->haltes[0];
            $halteEnd = $route->haltes[count($route->haltes) - 1];

            if ($completedTrip) {
                TripBooking::create([
                    'user_id' => $passenger->id,
                    'trip_id' => $completedTrip->id,
                    'boarding_stop_id' => $halteStart->id,
                    'arrive_stop_id' => $halteEnd->id,
                    'qr_code_token' => 'QR-COMPLETED-12345',
                    'fare' => 3500,
                    'status' => 'completed',
                    'booked_at' => now()->subHours(6),
                    'tapped_in_at' => now()->subHours(5)->addMinutes(5),
                    'tapped_out_at' => now()->subHours(3)->addMinutes(10),
                ]);
            }

            if ($activeTrip) {
                TripBooking::create([
                    'user_id' => $passenger->id,
                    'trip_id' => $activeTrip->id,
                    'boarding_stop_id' => $halteStart->id,
                    'arrive_stop_id' => $halteEnd->id,
                    'qr_code_token' => 'QR-ACTIVE-67890',
                    'fare' => 0,
                    'status' => 'active',
                    'booked_at' => now()->subHours(1),
                    'tapped_in_at' => now()->subMinutes(25),
                    'tapped_out_at' => null,
                ]);
            }
        }
    }
}
