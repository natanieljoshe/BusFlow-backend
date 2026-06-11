<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index() { return response()->json(['data' => Schedule::with('gaParameter')->get()]); }
    
    public function show($id) { return response()->json(['data' => Schedule::with(['trips', 'gaParameter', 'optimizations'])->findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'is_active' => 'boolean'
        ]);
        $schedule = Schedule::create($validated);
        return response()->json(['data' => $schedule], 201);
    }
    
    public function update(Request $request, $id) {
        $schedule = Schedule::findOrFail($id);
        $validated = $request->validate([
            'valid_from' => 'date',
            'valid_until' => 'date|after_or_equal:valid_from',
            'is_active' => 'boolean'
        ]);
        $schedule->update($validated);
        return response()->json(['data' => $schedule]);
    }
    
    public function destroy($id) { 
        Schedule::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
