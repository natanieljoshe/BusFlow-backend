<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BusMaintenanceLog;
use App\Models\Bus;

class BusMaintenanceLogSeeder extends Seeder
{
    public function run(): void
    {
        $bus = Bus::first();
        if ($bus) {
            BusMaintenanceLog::create([
                'bus_id' => $bus->id,
                'scheduled_at' => now()->subDays(10)->toDateString(),
                'completed_at' => now()->subDays(9)->toDateString(),
                'km_at_service' => 15000.50,
            ]);
            
            BusMaintenanceLog::create([
                'bus_id' => $bus->id,
                'scheduled_at' => now()->subMonths(2)->toDateString(),
                'completed_at' => now()->subMonths(2)->addDays(1)->toDateString(),
                'km_at_service' => 12000.00,
            ]);
        }
    }
}
