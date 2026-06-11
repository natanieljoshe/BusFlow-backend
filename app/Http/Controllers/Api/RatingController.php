<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverRating;
use App\Models\TripBooking;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'trip_booking_id' => 'required|exists:trip_bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string'
        ]);

        $booking = TripBooking::with('trip')
            ->where('id', $request->trip_booking_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'Trip belum selesai, tidak dapat memberikan rating'], 400);
        }

        $rating = DriverRating::updateOrCreate(
            ['trip_booking_id' => $booking->id, 'user_id' => $request->user()->id],
            [
                'driver_id' => $booking->trip->driver_id,
                'rating' => $request->rating,
                'review' => $request->review
            ]
        );

        return response()->json(['message' => 'Rating berhasil disimpan', 'data' => $rating]);
    }
}
