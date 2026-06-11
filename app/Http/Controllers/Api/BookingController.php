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
            'arrive_stop_id' => 'required|exists:haltes,id',
            'fare' => 'required|numeric|min:0'
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        if (!$wallet || $wallet->balance < $request->fare) {
            return response()->json(['message' => 'Saldo tidak mencukupi'], 400);
        }

        $booking = DB::transaction(function () use ($request, $user, $wallet) {
            $wallet->balance -= $request->fare;
            $wallet->save();

            WalletTransactionHistory::create([
                'wallet_id' => $wallet->id,
                'type' => 'fare',
                'amount' => $request->fare,
                'balance_after' => $wallet->balance,
                'description' => 'Pembayaran tiket bus',
            ]);

            return TripBooking::create([
                'user_id' => $user->id,
                'trip_id' => $request->trip_id,
                'boarding_stop_id' => $request->boarding_stop_id,
                'arrive_stop_id' => $request->arrive_stop_id,
                'qr_code_token' => Str::random(32),
                'status' => 'booked',
                'fare' => $request->fare,
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
        $booking = TripBooking::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($booking->status !== 'active') {
            return response()->json(['message' => 'Status tiket tidak valid untuk Tap Out'], 400);
        }

        $booking->status = 'completed';
        $booking->tapped_out_at = now();
        $booking->save();

        return response()->json(['message' => 'Tap Out berhasil', 'data' => $booking]);
    }
}
