<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function index() { return response()->json(['data' => Route::with('haltes')->get()]); }
    
    public function show($id) { return response()->json(['data' => Route::with('haltes')->findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'code' => 'required|string|unique:routes',
            'name' => 'required|string',
            'fare_per_km' => 'nullable|numeric|min:5000',
            'total_distance_km' => 'nullable|numeric',
            'time_start' => 'nullable|string',
            'time_end' => 'nullable|string',
            'avg_speed' => 'nullable|numeric',
            'max_freq_per_hour' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);
        if (isset($validated['fare_per_km'])) {
            $validated['fare_per_km'] = max((float)$validated['fare_per_km'], 5000);
        }
        $route = Route::create($validated);
        return response()->json(['data' => $route], 201);
    }
    
    public function update(Request $request, $id) {
        $route = Route::findOrFail($id);
        $validated = $request->validate([
            'code' => 'string|unique:routes,code,'.$id,
            'name' => 'string',
            'fare_per_km' => 'nullable|numeric|min:5000',
            'total_distance_km' => 'nullable|numeric',
            'time_start' => 'nullable|string',
            'time_end' => 'nullable|string',
            'avg_speed' => 'nullable|numeric',
            'max_freq_per_hour' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);
        if (isset($validated['fare_per_km'])) {
            $validated['fare_per_km'] = max((float)$validated['fare_per_km'], 5000);
        }
        $route->update($validated);
        return response()->json(['data' => $route]);
    }
    
    public function destroy($id) { 
        Route::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }

    public function attachHalte(Request $request, $id) {
        $route = Route::findOrFail($id);
        $validated = $request->validate([
            'halte_id' => 'required|exists:haltes,id',
            'sequence' => 'required|integer',
            'distance_from_prev_halte' => 'nullable|numeric'
        ]);
        
        // Detach if already exists to update or prevent duplicates
        $route->haltes()->detach($validated['halte_id']);
        
        $route->haltes()->attach($validated['halte_id'], [
            'sequence' => $validated['sequence'],
            'distance_from_prev_halte' => $validated['distance_from_prev_halte'] ?? 0
        ]);
        return response()->json(['message' => 'Halte attached successfully']);
    }

    public function detachHalte($id, $halteId) {
        $route = Route::findOrFail($id);
        $route->haltes()->detach($halteId);
        return response()->json(['message' => 'Halte detached successfully']);
    }
}
