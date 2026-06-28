<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index() { return response()->json(['data' => Driver::with(['user', 'route'])->get()]); }
    
    public function show($id) { return response()->json(['data' => Driver::with(['user', 'route'])->findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id|unique:drivers',
            'employee_id' => 'required|string|unique:drivers',
            'phone' => 'nullable|string|max:20',
            'joined_at' => 'nullable|date',
            'is_available' => 'boolean',
            'route_id' => 'nullable|exists:routes,id',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i'
        ]);
        $driver = Driver::create($validated);
        return response()->json(['data' => $driver], 201);
    }
    
    public function update(Request $request, $id) {
        $driver = Driver::findOrFail($id);
        $validated = $request->validate([
            'user_id' => 'exists:users,id|unique:drivers,user_id,'.$id,
            'employee_id' => 'string|unique:drivers,employee_id,'.$id,
            'phone' => 'nullable|string|max:20',
            'joined_at' => 'nullable|date',
            'is_available' => 'boolean',
            'route_id' => 'nullable|exists:routes,id',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i'
        ]);
        $driver->update($validated);
        return response()->json(['data' => $driver]);
    }
    
    public function destroy($id) { 
        Driver::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
