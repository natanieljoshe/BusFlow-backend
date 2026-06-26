<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;

class RunOptimization extends Command
{
    protected $signature = 'busflow:optimize {job_id}';
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

        $scriptPath = app_path('Algorithms/src/db_integrated.py');
        $payloadPath = app_path('Algorithms/src/payload.JSON');
        $payload = file_get_contents($payloadPath);

        $env = [
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'PATH' => getenv('PATH')
        ];

        $process = new Process([$pythonPath, '-u', $scriptPath], null, $env);
        $process->setInput($payload);
        $process->setTimeout(3600); // 1 hour max

        try {
            $totalGenerations = 1;
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
                        
                        $statusData = json_decode(File::get($statusFile), true) ?? [];
                        $statusData['progress'] = $progress;
                        $statusData['message'] = "Generasi ke: $currentGen / $totalGenerations";
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
                $statusData['result'] = json_decode($process->getOutput(), true);
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
