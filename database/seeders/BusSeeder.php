<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bus;

class BusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 6. Create Bus
        Bus::create([
            'plate_number' => 'B 1234 XYZ',
            'capacity' => 45,
            'year' => 2022,
            'brand' => 'Mercedes-Benz',
            'status' => true,
            'total_distance' => 15000.5,
        ]);
    }
}
