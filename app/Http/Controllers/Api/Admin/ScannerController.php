<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TripBooking;
use Illuminate\Http\Request;

class ScannerController extends Controller
{
    public function tapIn(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $userId = null;
        if (str_starts_with($request->token, 'USER-')) {
            $userId = str_replace('USER-', '', $request->token);
            if ($userId === 'GUEST' || empty($userId)) {
                return response()->json(['message' => 'Token Guest tidak valid.'], 404);
            }
        } else {
            $booking = TripBooking::where('qr_code_token', $request->token)->first();
            if ($booking) {
                $userId = $booking->user_id;
            }
        }

        if (!$userId) {
            return response()->json(['message' => 'Token tidak valid atau tidak ditemukan.'], 404);
        }
        
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $user) {
            // Check active booking (means they want to tap OUT)
            $activeBooking = TripBooking::where('user_id', $userId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($activeBooking) {
                // Proses TAP OUT
                $wallet = $user->wallet;
                
                $arriveHalteId = \Illuminate\Support\Facades\Cache::get('current_halte_id', \App\Models\Halte::skip(1)->first()->id ?? 2);
                $boardingHalteId = $activeBooking->boarding_stop_id;
                
                $route = $activeBooking->trip->route;
                $farePerKm = (float)($route->fare_per_km ?? 0);
                if ($farePerKm <= 0) {
                    $farePerKm = (float) \App\Models\GlobalSetting::getValue('fee_per_km', 3);
                }
                
                $routeHaltes = $route->haltes()->orderBy('route_haltes.sequence')->get();
                $totalDistance = 0;
                $isCounting = false;
                
                foreach ($routeHaltes as $halte) {
                    if ($isCounting) {
                        $totalDistance += (float)($halte->pivot->distance_from_prev_halte ?? 0);
                    }
                    
                    if ($halte->id == $boardingHalteId) {
                        $isCounting = true;
                    }
                    
                    if ($halte->id == $arriveHalteId && $isCounting) {
                        break;
                    }
                }
                
                // If somehow total distance is 0, we still enforce a minimum fare
                $calculatedFare = $totalDistance * $farePerKm;
                $fare = max($calculatedFare, $farePerKm);
                
                if ($wallet && $wallet->balance < $fare) {
                    return response()->json(['message' => 'Saldo tidak cukup untuk Tap Out! (Sisa: $' . number_format($wallet->balance, 2, '.', ',') . ', Butuh: $' . number_format($fare, 2, '.', ',') . ')'], 400);
                }

                if ($wallet) {
                    $wallet->balance -= $fare;
                    $wallet->save();

                    \App\Models\WalletTransactionHistory::create([
                        'wallet_id' => $wallet->id,
                        'type' => 'fare',
                        'amount' => $fare,
                        'balance_after' => $wallet->balance,
                        'description' => 'Pembayaran tiket bus (Tap Out)',
                    ]);
                }

                $activeBooking->status = 'completed';
                $activeBooking->fare = $fare;
                $activeBooking->tapped_out_at = now();
                if (!$activeBooking->arrive_stop_id) {
                    $activeBooking->arrive_stop_id = \Illuminate\Support\Facades\Cache::get('current_halte_id', \App\Models\Halte::skip(1)->first()->id ?? 2);
                }
                $activeBooking->save();

                return response()->json([
                    'message' => 'Check Out berhasil! Saldo terpotong $' . number_format($fare, 2, '.', ','),
                    'action' => 'checkout',
                    'data' => [
                        'booking_id' => $activeBooking->id,
                        'user_id' => $userId,
                    ]
                ]);
            } else {
                // Proses TAP IN
                $wallet = $user->wallet;
                
                $tripForTapIn = \App\Models\Trip::first();
                $routeForTapIn = $tripForTapIn ? $tripForTapIn->route : null;
                $minBalance = $routeForTapIn ? (float)($routeForTapIn->fare_per_km ?? 0) : 0;
                if ($minBalance <= 0) {
                    $minBalance = (float) \App\Models\GlobalSetting::getValue('fee_per_km', 3);
                }

                if ($wallet && $wallet->balance < $minBalance) {
                    return response()->json(['message' => 'Saldo minimum tidak cukup untuk Check In! (Min. $' . number_format($minBalance, 2, '.', ',') . ')'], 400);
                }

                // Create new booking for tap in
                $booking = new TripBooking();
                $booking->user_id = $userId;
                $booking->trip_id = \App\Models\Trip::first()->id ?? 1;
                $booking->boarding_stop_id = \Illuminate\Support\Facades\Cache::get('current_halte_id', \App\Models\Halte::first()->id ?? 1);
                $booking->arrive_stop_id = null; // belum tap out
                $booking->fare = 0;
                $booking->booked_at = now();
                $booking->qr_code_token = 'MOCK-' . time() . rand(1000, 9999);
                $booking->status = 'active';
                $booking->tapped_in_at = now();
                $booking->save();

                return response()->json([
                    'message' => 'Check In berhasil! Silakan naik.',
                    'action' => 'checkin',
                    'data' => [
                        'booking_id' => $booking->id,
                        'user_id' => $userId,
                    ]
                ]);
            }
        });
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'halte_id' => 'required|integer'
        ]);

        \Illuminate\Support\Facades\Cache::put('current_halte_id', $request->halte_id);

        $trip = \App\Models\Trip::first();
        if ($trip) {
            $trip->current_halte_id = $request->halte_id;
            $trip->save();
        }

        return response()->json(['message' => 'Location updated successfully.']);
    }

    /**
     * Public endpoint: return current halte position + all haltes on the route
     * for a given user (by user_id), so the user's frontend can show their position.
     */
    public function currentPosition(Request $request)
    {
        $trip = \App\Models\Trip::first();
        $currentHalteId = $trip && $trip->current_halte_id ? $trip->current_halte_id : \Illuminate\Support\Facades\Cache::get('current_halte_id');
        $currentHalte = $currentHalteId ? \App\Models\Halte::find($currentHalteId) : null;

        // Optionally load the active booking's route haltes for the user
        $userId = $request->query('user_id');
        $routeHaltes = [];

        if ($userId) {
            $activeBooking = TripBooking::with(['trip.route.haltes'])
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($activeBooking && $activeBooking->trip && $activeBooking->trip->route) {
                $routeHaltes = $activeBooking->trip->route->haltes ?? [];
            }
        }

        return response()->json([
            'current_halte_id' => $currentHalteId,
            'current_halte'    => $currentHalte,
            'route_haltes'     => $routeHaltes,
        ]);
    }
}
