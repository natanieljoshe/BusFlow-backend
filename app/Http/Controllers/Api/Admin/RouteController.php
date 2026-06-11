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
            'total_distance_km' => 'nullable|numeric',
            'time_start' => 'nullable|string',
            'time_end' => 'nullable|string',
            'avg_speed' => 'nullable|numeric',
            'max_freq_per_hour' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);
        $route = Route::create($validated);
        return response()->json(['data' => $route], 201);
    }
    
    public function update(Request $request, $id) {
        $route = Route::findOrFail($id);
        $validated = $request->validate([
            'code' => 'string|unique:routes,code,'.$id,
            'name' => 'string',
            'total_distance_km' => 'nullable|numeric',
            'time_start' => 'nullable|string',
            'time_end' => 'nullable|string',
            'avg_speed' => 'nullable|numeric',
            'max_freq_per_hour' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);
        $route->update($validated);
        return response()->json(['data' => $route]);
    }
    
    public function destroy($id) { 
        Route::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
