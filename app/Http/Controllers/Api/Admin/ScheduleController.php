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

    public function generate(Request $request) {
        $validated = $request->validate([
            'route' => 'required',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'fleet_count' => 'required|numeric',
            'bus_capacity' => 'required|numeric',
        ]);

        $jobId = uniqid('opt_');
        $statusFile = storage_path("app/optimizations/{$jobId}.json");
        
        if (!\Illuminate\Support\Facades\File::exists(storage_path('app/optimizations'))) {
            \Illuminate\Support\Facades\File::makeDirectory(storage_path('app/optimizations'), 0755, true);
        }

        $initialStatus = [
            'status' => 'running',
            'progress' => 0,
            'message' => 'Memuat model AI (Random Forest) 2.9GB...',
            'data' => $validated
        ];
        \Illuminate\Support\Facades\File::put($statusFile, json_encode($initialStatus));

        $artisanPath = base_path('artisan');
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B php \"$artisanPath\" busflow:optimize $jobId", "r"));
        } else {
            exec("php \"$artisanPath\" busflow:optimize $jobId > /dev/null 2>&1 &");
        }

        return response()->json([
            'message' => 'Proses optimasi dimulai di background',
            'job_id' => $jobId
        ]);
    }

    public function status($id) {
        $file = storage_path("app/optimizations/{$id}.json");
        if (!\Illuminate\Support\Facades\File::exists($file)) return response()->json(['status' => 'not_found'], 404);
        return response()->json(json_decode(\Illuminate\Support\Facades\File::get($file), true));
    }

    public function cancel($id) {
        $file = storage_path("app/optimizations/{$id}.json");
        if (!\Illuminate\Support\Facades\File::exists($file)) return response()->json(['status' => 'not_found'], 404);
        
        $data = json_decode(\Illuminate\Support\Facades\File::get($file), true);
        $data['status'] = 'cancelled';
        $data['message'] = 'Dibatalkan oleh pengguna.';
        \Illuminate\Support\Facades\File::put($file, json_encode($data));
        return response()->json(['message' => 'Berhasil membatalkan optimasi']);
    }
}
