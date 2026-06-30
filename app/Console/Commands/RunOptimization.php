<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;

class RunOptimization extends Command
{
    protected $signature = 'busflow:optimize {job_id} {--mode=live}';
    protected $description = 'Run optimization background job';

    public function handle()
    {
        $jobId = $this->argument('job_id');
        $statusFile = storage_path("app/optimizations/{$jobId}.json");
        
        if (!File::exists($statusFile)) {
            $this->error("Job not found.");
            return;
        }

        $pythonPath = 'C:\\Users\\NATAN\\AppData\\Local\\Programs\\Python\\Python313\\python.exe'; 
        if (!file_exists($pythonPath)) {
            $pythonPath = 'python';
        }

        $scriptPath = app_path('Algorithms/src/ga_optimizer.py');
        $mode = $this->option('mode');
        
        if ($mode === 'demo') {
            $statusData = json_decode(File::get($statusFile), true) ?? [];
            $payloadPath = app_path('Algorithms/src/payload.JSON');
            $payloadArr = json_decode(file_get_contents($payloadPath), true);
            $payloadArr['_target_route'] = $statusData['data']['route'] ?? 'Q114';
            
            $payloadArr['ga_parameters'] = [
                'population_size' => 200,    // Aslinya 200
                'generations' => 500,        // Aslinya 500
                'crossover_rate' => 0.8,
                'mutation_rate' => 0.12,
                'elitism_count' => 8,       // Aslinya 8
                'min_driver_rest_min' => 30
            ];

            $payload = json_encode($payloadArr);
        } else {

            $statusData = json_decode(File::get($statusFile), true) ?? [];
            $jobData = $statusData['data'] ?? [];
            
            $routeId = $jobData['route'] ?? 1;
            $fleetCount = $jobData['fleet_count'] ?? 10;
            $driverCount = $jobData['driver_count'] ?? 10;
            $conductorCount = $jobData['conductor_count'] ?? 10;

            $routes = \App\Models\Route::where('id', $routeId)->get()->map(function($r) {
                return [
                    'id' => (string) $r->id,
                    'name' => $r->name,
                    'estimated_travel_time_min' => (int) $r->estimated_travel_time_min,
                    'total_distance_km' => (float) $r->total_distance_km,
                    'avg_speed' => (float) $r->avg_speed,
                    'origin_stop_id' => (string) $r->origin_stop_id,
                    'destination_stop_id' => (string) $r->destination_stop_id,
                ];
            });

            $buses = \App\Models\Bus::where('status', true)
                ->where(function($q) use ($routeId) {
                    $q->whereNull('route_id')->orWhere('route_id', $routeId);
                })->limit($fleetCount)->get()->map(function($b) use ($routeId) {
                return [
                    'id' => (string) $b->id,
                    'capacity' => (int) $b->capacity,
                    'total_distance' => (float) $b->total_distance,
                    'route_id' => $b->route_id ? (string) $b->route_id : (string) $routeId,
                ];
            });

            $drivers = \App\Models\Driver::where('is_available', true)
                ->limit($driverCount)->get()->map(function($d) use ($routeId) {
                return [
                    'id' => (string) $d->id,
                    'employee_id' => (string) ($d->employee_id ?? $d->id),
                    'shift_start' => substr($d->shift_start, 0, 5),
                    'shift_end' => substr($d->shift_end, 0, 5),
                    'status' => 'active',
                    'route_id' => $d->route_id ? (string) $d->route_id : (string) $routeId,
                ];
            });

            $conductors = \App\Models\Conductor::where('is_available', true)
                ->limit($conductorCount)->get()->map(function($c) use ($routeId) {
                return [
                    'id' => (string) $c->id,
                    'employee_id' => (string) ($c->employee_id ?? $c->id),
                    'shift_start' => substr($c->shift_start, 0, 5),
                    'shift_end' => substr($c->shift_end, 0, 5),
                    'status' => 'active',
                    'route_id' => $c->route_id ? (string) $c->route_id : (string) $routeId,
                ];
            });

            $routeStops = \App\Models\RouteHalte::with('halte')->where('route_id', $routeId)->orderBy('sequence')->get()->map(function($rs) {
                return [
                    'route_id' => (string) $rs->route_id,
                    'stop_id' => (string) $rs->halte_id,
                    'stop_name' => $rs->halte ? $rs->halte->name : '',
                    'latitude' => $rs->halte ? (float) $rs->halte->latitude : 0.0,
                    'longitude' => $rs->halte ? (float) $rs->halte->longitude : 0.0,
                    'sequence' => (int) $rs->sequence,
                    'distance_from_prev_stop' => (float) $rs->distance_from_prev_halte,
                ];
            });

            $payloadObj = [
                'routes' => $routes,    
                'buses' => $buses,
                'drivers' => $drivers,
                'conductors' => $conductors,
                'passenger_predictions' => [],
                'route_stops' => $routeStops,
                'min_rest' => 15,
                'total_bus' => (int) $fleetCount,
                'reserve_count' => max(1, floor($fleetCount * 0.15)),
                'schedule_date' => $statusData['data']['date'] ?? date('Y-m-d'),
                'daily_leaves' => [],
                'ga_parameters' => [
                    'population_size' => 200,
                    'generations' => 500,
                    'crossover_rate' => 0.8,
                    'mutation_rate' => 0.12,
                    'elitism_count' => 8,
                    'min_driver_rest_min' => 30
                ],
            ];

            $payload = json_encode($payloadObj);
        }

        $env = [
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'PATH' => getenv('PATH')
        ];

        $process = new Process([$pythonPath, '-u', $scriptPath], null, $env);
        $process->setInput($payload);
        $process->setTimeout(3600); // 1 hour max

        try {
            $totalGenerations = 1;
            $startTime = time();
            $process->start();

            while ($process->isRunning()) {
                // Check if user cancelled
                if (File::exists($statusFile)) {
                    $statusData = json_decode(File::get($statusFile), true);
                    if (($statusData['status'] ?? '') === 'cancelled') {
                        $process->stop(3, SIGINT);
                        return;
                    }
                }

                $incrementalError = $process->getIncrementalErrorOutput();
                if ($incrementalError) {
                    File::append(storage_path("app/optimizations/debug_{$jobId}.log"), $incrementalError);
                    if (preg_match('/Gen=(\d+)/', $incrementalError, $matches)) {
                        $totalGenerations = (int)$matches[1];
                    }
                    if (preg_match_all('/Gen\s+(\d+)\s+\|/', $incrementalError, $matches)) {
                        $currentGen = (int)end($matches[1]);
                        $progress = min(99, round(($currentGen / max(1, $totalGenerations)) * 100));
                        
                        $etaText = "";
                        if ($currentGen > 0) {
                            $elapsed = time() - $startTime;
                            $timePerGen = $elapsed / $currentGen;
                            $remainingGens = $totalGenerations - $currentGen;
                            $etaSeconds = $remainingGens * $timePerGen;
                            if ($etaSeconds > 60) {
                                $etaText = " (~" . ceil($etaSeconds / 60) . " menit tersisa)";
                            } else {
                                $etaText = " (~" . ceil($etaSeconds) . " detik tersisa)";
                            }
                        }

                        $statusData = json_decode(File::get($statusFile), true) ?? [];
                        $statusData['progress'] = $progress;
                        $statusData['message'] = "Generasi ke: $currentGen / $totalGenerations" . $etaText;
                        File::put($statusFile, json_encode($statusData));
                    }
                }
                
                usleep(500000); // Wait 0.5s before next check
            }

            if (!$process->isSuccessful()) {
                $statusData = json_decode(File::get($statusFile), true) ?? [];
                if (($statusData['status'] ?? '') !== 'cancelled') {
                    $statusData['status'] = 'error';
                    $statusData['error'] = $process->getErrorOutput();
                    File::put($statusFile, json_encode($statusData));
                }
                return;
            }

            // Success
            $statusData = json_decode(File::get($statusFile), true) ?? [];
            if (($statusData['status'] ?? '') !== 'cancelled') {
                $statusData['status'] = 'completed';
                $statusData['progress'] = 100;
                $statusData['message'] = 'Optimasi selesai!';
                
                $result = json_decode($process->getOutput(), true);
                
                // Inject routes and route_stops into result for the frontend map
                if (isset($payloadObj)) {
                    $result['routes'] = $payloadObj['routes'] ?? [];
                    $result['route_stops'] = $payloadObj['route_stops'] ?? [];
                    $result['schedule_data']['date'] = $payloadObj['schedule_date'] ?? date('Y-m-d');
                } elseif (isset($payload)) {
                    $payloadArr = json_decode($payload, true) ?? [];
                    $result['routes'] = $payloadArr['routes'] ?? [];
                    $result['route_stops'] = $payloadArr['route_stops'] ?? [];
                    $result['schedule_data']['date'] = $payloadArr['schedule_date'] ?? date('Y-m-d');
                }
                
                $statusData['result'] = $result;
                File::put($statusFile, json_encode($statusData));
            }

        } catch (\Exception $e) {
            $statusData = json_decode(File::get($statusFile), true) ?? [];
            $statusData['status'] = 'error';
            $statusData['error'] = $e->getMessage();
            File::put($statusFile, json_encode($statusData));
        }
    }
}
