<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Halte;
use Illuminate\Http\Request;

class HalteController extends Controller
{
    public function index() { return response()->json(['data' => Halte::all()]); }
    
    public function show($id) { return response()->json(['data' => Halte::findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string',
            'code' => 'nullable|string|unique:haltes',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'address' => 'nullable|string',
            'is_active' => 'boolean'
        ]);
        
        if (empty($validated['code'])) {
            $validated['code'] = 'HLT-' . strtoupper(substr(uniqid(), -6));
        }
        
        $halte = Halte::create($validated);
        return response()->json(['data' => $halte], 201);
    }
    
    public function update(Request $request, $id) {
        $halte = Halte::findOrFail($id);
        $validated = $request->validate([
            'name' => 'string',
            'code' => 'nullable|string|unique:haltes,code,'.$id,
            'latitude' => 'numeric',
            'longitude' => 'numeric',
            'address' => 'nullable|string',
            'is_active' => 'boolean'
        ]);
        
        if (array_key_exists('code', $validated) && empty($validated['code'])) {
            $validated['code'] = 'HLT-' . strtoupper(substr(uniqid(), -6));
        }

        $halte->update($validated);
        return response()->json(['data' => $halte]);
    }
    
    public function destroy($id) { 
        Halte::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
