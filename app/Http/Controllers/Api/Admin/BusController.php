<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use Illuminate\Http\Request;

class BusController extends Controller
{
    public function index() { return response()->json(['data' => Bus::all()]); }
    
    public function show($id) { return response()->json(['data' => Bus::findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'plate_number' => 'required|string|unique:buses',
            'capacity' => 'required|integer|min:1',
            'year' => 'nullable|integer',
            'brand' => 'nullable|string',
            'status' => 'boolean',
            'total_distance' => 'numeric'
        ]);
        $bus = Bus::create($validated);
        return response()->json(['data' => $bus], 201);
    }
    
    public function update(Request $request, $id) {
        $bus = Bus::findOrFail($id);
        $validated = $request->validate([
            'plate_number' => 'string|unique:buses,plate_number,'.$id,
            'capacity' => 'integer|min:1',
            'year' => 'nullable|integer',
            'brand' => 'nullable|string',
            'status' => 'boolean',
            'total_distance' => 'numeric'
        ]);
        $bus->update($validated);
        return response()->json(['data' => $bus]);
    }
    
    public function destroy($id) { 
        Bus::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
