<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conductor;
use Illuminate\Http\Request;

class ConductorController extends Controller
{
    public function index() { return response()->json(['data' => Conductor::with('user')->get()]); }
    
    public function show($id) { return response()->json(['data' => Conductor::with('user')->findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id|unique:conductors',
            'employee_id' => 'required|string|unique:conductors',
            'status' => 'boolean'
        ]);
        $conductor = Conductor::create($validated);
        return response()->json(['data' => $conductor], 201);
    }
    
    public function update(Request $request, $id) {
        $conductor = Conductor::findOrFail($id);
        $validated = $request->validate([
            'user_id' => 'exists:users,id|unique:conductors,user_id,'.$id,
            'employee_id' => 'string|unique:conductors,employee_id,'.$id,
            'status' => 'boolean'
        ]);
        $conductor->update($validated);
        return response()->json(['data' => $conductor]);
    }
    
    public function destroy($id) { 
        Conductor::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
