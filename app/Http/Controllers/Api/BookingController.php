<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TripBooking;
use App\Models\WalletTransactionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = TripBooking::with(['trip.route', 'boardingStop', 'arriveStop'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json(['data' => $bookings]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'trip_id' => 'required|exists:trips,id',
            'boarding_stop_id' => 'required|exists:haltes,id',
            'arrive_stop_id' => 'required|exists:haltes,id'
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        if (!$wallet) {
            return response()->json(['message' => 'Wallet tidak ditemukan'], 400);
        }

        $booking = DB::transaction(function () use ($request, $user) {
            return TripBooking::create([
                'user_id' => $user->id,
                'trip_id' => $request->trip_id,
                'boarding_stop_id' => $request->boarding_stop_id,
                'arrive_stop_id' => $request->arrive_stop_id,
                'qr_code_token' => Str::random(32),
                'status' => 'booked',
                'fare' => 0,
                'booked_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Booking berhasil',
            'data' => $booking
        ], 201);
    }

    public function tapIn(Request $request, $id)
    {
        $booking = TripBooking::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($booking->status !== 'booked') {
            return response()->json(['message' => 'Status tiket tidak valid untuk Tap In'], 400);
        }

        $booking->status = 'active';
        $booking->tapped_in_at = now();
        $booking->save();

        return response()->json(['message' => 'Tap In berhasil', 'data' => $booking]);
    }

    public function tapOut(Request $request, $id)
    {
        $booking = TripBooking::with(['boardingStop', 'arriveStop'])->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($booking->status !== 'active') {
            return response()->json(['message' => 'Status tiket tidak valid untuk Tap Out'], 400);
        }

        $user = $request->user();
        $wallet = $user->wallet;

        // Calculate Distance using Haversine
        $lat1 = $booking->boardingStop->latitude;
        $lon1 = $booking->boardingStop->longitude;
        $lat2 = $booking->arriveStop->latitude;
        $lon2 = $booking->arriveStop->longitude;

        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;

        $feePerKm = \App\Models\GlobalSetting::getValue('fee_per_km', 1500);
        $finalFare = round($distance * $feePerKm, 2);

        DB::transaction(function() use ($booking, $wallet, $finalFare) {
            if($wallet) {
                $wallet->balance -= $finalFare;
                $wallet->save();

                WalletTransactionHistory::create([
                    'wallet_id' => $wallet->id,
                    'type' => 'fare',
                    'amount' => $finalFare,
                    'balance_after' => $wallet->balance,
                    'description' => 'Pembayaran tiket bus (Tap Out)',
                ]);
            }

            $booking->status = 'completed';
            $booking->fare = $finalFare;
            $booking->tapped_out_at = now();
            $booking->save();
        });

        return response()->json([
            'message' => 'Tap Out berhasil', 
            'data' => $booking,
            'distance_km' => round($distance, 2),
            'fare_deducted' => $finalFare
        ]);
    }
}
