<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index(Request $request)
    {
        $query = Trip::with(['route', 'bus', 'driver.user', 'conductor.user'])
            ->where('is_active', true)
            ->whereHas('schedule', function ($q) {
                $q->where('is_active', true)
                  ->whereDate('valid_from', '<=', now())
                  ->whereDate('valid_until', '>=', now());
            });

        if ($request->has('route_id')) {
            $query->where('route_id', $request->route_id);
        }

        return response()->json(['data' => $query->get()]);
    }
}
