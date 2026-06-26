<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index(Request $request) {
        $query = Trip::with(['schedule', 'route', 'bus', 'driver', 'conductor']);
        if ($request->has('route_id')) {
            $query->where('route_id', $request->route_id);
        }
        return response()->json(['data' => $query->get()]);
    }
    
    public function show($id) { return response()->json(['data' => Trip::with(['schedule', 'route', 'bus', 'driver', 'conductor'])->findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'route_id' => 'required|exists:routes,id',
            'bus_id' => 'required|exists:buses,id',
            'driver_id' => 'required|exists:drivers,id',
            'conductor_id' => 'required|exists:conductors,id',
            'departure_time' => 'required|string',
            'estimated_arrival' => 'nullable|string',
            'is_active' => 'boolean'
        ]);
        $trip = Trip::create($validated);
        return response()->json(['data' => $trip], 201);
    }
    
    public function update(Request $request, $id) {
        $trip = Trip::findOrFail($id);
        $validated = $request->validate([
            'schedule_id' => 'exists:schedules,id',
            'route_id' => 'exists:routes,id',
            'bus_id' => 'exists:buses,id',
            'driver_id' => 'exists:drivers,id',
            'conductor_id' => 'exists:conductors,id',
            'departure_time' => 'string',
            'estimated_arrival' => 'nullable|string',
            'is_active' => 'boolean'
        ]);
        $trip->update($validated);
        return response()->json(['data' => $trip]);
    }
    
    public function destroy($id) { 
        Trip::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
