<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use Illuminate\Http\Request;

class BusController extends Controller
{
    public function index() { return response()->json(['data' => Bus::with(['drivers.user', 'conductors.user', 'route'])->get()]); }
    
    public function show($id) { return response()->json(['data' => Bus::with(['drivers.user', 'conductors.user', 'route'])->findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'plate_number' => 'required|string|unique:buses',
            'capacity' => 'required|integer|min:1',
            'year' => 'nullable|integer',
            'brand' => 'nullable|string',
            'status' => 'boolean',
            'total_distance' => 'numeric',
            'driver_ids' => 'nullable|array',
            'driver_ids.*' => 'exists:drivers,id',
            'conductor_ids' => 'nullable|array',
            'conductor_ids.*' => 'exists:conductors,id',
            'route_id' => 'nullable|exists:routes,id'
        ]);

        $bus = Bus::create($validated);
        
        if ($request->has('driver_ids')) {
            \App\Models\Driver::whereIn('id', $request->driver_ids)->update(['bus_id' => $bus->id]);
        }
        if ($request->has('conductor_ids')) {
            \App\Models\Conductor::whereIn('id', $request->conductor_ids)->update(['bus_id' => $bus->id]);
        }

        return response()->json(['data' => $bus->load(['drivers.user', 'conductors.user', 'route'])], 201);
    }
    
    public function update(Request $request, $id) {
        $bus = Bus::findOrFail($id);
        $validated = $request->validate([
            'plate_number' => 'string|unique:buses,plate_number,'.$id,
            'capacity' => 'integer|min:1',
            'year' => 'nullable|integer',
            'brand' => 'nullable|string',
            'status' => 'boolean',
            'total_distance' => 'numeric',
            'driver_ids' => 'nullable|array',
            'driver_ids.*' => 'exists:drivers,id',
            'conductor_ids' => 'nullable|array',
            'conductor_ids.*' => 'exists:conductors,id',
            'route_id' => 'nullable|exists:routes,id'
        ]);

        $bus->update($validated);

        if ($request->has('driver_ids')) {
            \App\Models\Driver::where('bus_id', $bus->id)->update(['bus_id' => null]);
            \App\Models\Driver::whereIn('id', $request->driver_ids)->update(['bus_id' => $bus->id]);
        }
        if ($request->has('conductor_ids')) {
            \App\Models\Conductor::where('bus_id', $bus->id)->update(['bus_id' => null]);
            \App\Models\Conductor::whereIn('id', $request->conductor_ids)->update(['bus_id' => $bus->id]);
        }

        return response()->json(['data' => $bus->load(['drivers.user', 'conductors.user', 'route'])]);
    }
    
    public function destroy($id) { 
        Bus::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
