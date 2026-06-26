<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index() { return response()->json(['data' => Driver::with('user')->get()]); }
    
    public function show($id) { return response()->json(['data' => Driver::with('user')->findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id|unique:drivers',
            'employee_id' => 'required|string|unique:drivers',
            'phone' => 'nullable|string|max:20',
            'joined_at' => 'nullable|date',
            'is_available' => 'boolean'
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
            'is_available' => 'boolean'
        ]);
        $driver->update($validated);
        return response()->json(['data' => $driver]);
    }
    
    public function destroy($id) { 
        Driver::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
