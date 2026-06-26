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
            'driver_count' => 'required|numeric',
            'conductor_count' => 'required|numeric',
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

        $mode = $request->input('mode', 'live');
        $artisanPath = base_path('artisan');
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B php \"$artisanPath\" busflow:optimize $jobId --mode=$mode", "r"));
        } else {
            exec("php \"$artisanPath\" busflow:optimize $jobId --mode=$mode > /dev/null 2>&1 &");
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

    public function apply(Request $request) {
        $validated = $request->validate(['job_id' => 'required']);
        $jobId = $validated['job_id'];
        
        $file = storage_path("app/optimizations/{$jobId}.json");
        if (!\Illuminate\Support\Facades\File::exists($file)) {
            return response()->json(['message' => 'Job data not found'], 404);
        }
        
        $data = json_decode(\Illuminate\Support\Facades\File::get($file), true);
        if (($data['status'] ?? '') !== 'completed' || !isset($data['result']['trips'])) {
            return response()->json(['message' => 'Invalid or incomplete job data'], 400);
        }

        $trips = $data['result']['trips'];
        if (empty($trips)) {
            return response()->json(['message' => 'No trips to apply'], 400);
        }

        $routeId = $trips[0]['route_id'];
        $inputDate = $data['data']['date'] ?? date('Y-m-d');
        
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $schedule = \App\Models\Schedule::create([
                'valid_from' => $inputDate,
                'valid_until' => $inputDate,
                'is_active' => true,
                'fitness_score' => $data['result']['schedule_data']['fitness_score'] ?? 0,
                'total_penalty' => $data['result']['schedule_data']['total_penalty'] ?? 0,
                'avg_wait_min' => $data['result']['schedule_data']['avg_wait_min'] ?? 0,
                'passenger_served' => $data['result']['schedule_data']['passenger_served'] ?? 0,
            ]);

            $existingScheduleIds = \App\Models\Schedule::whereDate('valid_from', '<=', $inputDate)
                ->whereDate('valid_until', '>=', $inputDate)
                ->pluck('id');
                
            if ($existingScheduleIds->isNotEmpty()) {
                \App\Models\Trip::whereIn('schedule_id', $existingScheduleIds)
                    ->where('route_id', $routeId)
                    ->delete();
            }

            $insertData = [];
            foreach ($trips as $trip) {
                $insertData[] = [
                    'schedule_id' => $schedule->id,
                    'route_id' => $trip['route_id'],
                    'bus_id' => null,
                    'driver_id' => null,
                    'conductor_id' => null,
                    'departure_time' => $trip['departure_time'],
                    'estimated_arrival' => $trip['estimated_arrival'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            \App\Models\Trip::insert($insertData);

            \Illuminate\Support\Facades\DB::commit();
            return response()->json(['message' => 'Successfully applied new schedule']);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
