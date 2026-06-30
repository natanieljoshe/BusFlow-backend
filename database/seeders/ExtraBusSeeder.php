<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bus;
use App\Models\Route;
use Faker\Factory as Faker;

class ExtraBusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        
        // Coba dapatkan beberapa ID route yang sudah ada (jika ada)
        $routeIds = Route::pluck('id')->toArray();

        for ($i = 0; $i < 20; $i++) {
            $brand = $faker->randomElement(['Hino', 'Mercedes-Benz', 'Scania', 'Volvo', 'Isuzu']);
            $plateNumber = 'B ' . $faker->randomNumber(4, true) . ' ' . strtoupper($faker->lexify('??'));
            
            Bus::create([
                'plate_number' => $plateNumber,
                'capacity' => $faker->randomElement([40, 45, 50, 60]),
                'year' => $faker->numberBetween(2015, 2024),
                'brand' => $brand,
                'status' => $faker->boolean(80), // 80% kemungkinan aktif
                'total_distance' => $faker->randomFloat(2, 0, 500000),
                'route_id' => !empty($routeIds) ? $faker->randomElement($routeIds) : null,
            ]);
        }
    }
}
