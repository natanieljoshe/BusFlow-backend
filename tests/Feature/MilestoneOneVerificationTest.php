<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Bus;

class MilestoneOneVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_milestone_one_database_relationships_and_defaults()
    {


        // 2. Creating a Route sets the default fare_per_km to 0.00
        $route = Route::create([
            'code' => 'TEST-R1',
            'name' => 'Test Route 1',
            'total_distance_km' => 10.0,
            'time_start' => '06:00',
            'time_end' => '22:00',
            'avg_speed' => 25.0,
            'max_freq_per_hour' => 4,
            'is_active' => true,
        ]);
        $this->assertEquals(0.00, $route->fare_per_km);

        // 3. Create a Driver
        $user = User::create([
            'name' => 'Driver One',
            'email' => 'driver1@test.com',
            'password' => bcrypt('password'),
            'role' => 'driver',
            'is_active' => true,
        ]);
        $driver = Driver::create([
            'user_id' => $user->id,
            'employee_id' => 'DRV-TEST-001',
            'phone' => '081234567890',
            'is_available' => true,
            'joined_at' => now(),
        ]);

        // 4. Saving a Bus with an assigned driver and route is successful
        $bus = Bus::create([
            'plate_number' => 'B 7777 ABC',
            'capacity' => 40,
            'year' => 2025,
            'brand' => 'Hino',
            'status' => true,
            'total_distance' => 0.0,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
        ]);

        $this->assertNotNull($bus->id);
        $this->assertEquals($driver->id, $bus->driver_id);
        $this->assertEquals($route->id, $bus->route_id);
        $this->assertEquals($driver->id, $bus->driver->id);
        $this->assertEquals($route->id, $bus->route->id);

        // 5. Deleting a Driver sets driver_id in buses table to null
        $driver->delete();
        $bus->refresh();
        $this->assertNull($bus->driver_id);
        $this->assertNull($bus->driver);

        // 6. Deleting a Route sets route_id in buses table to null
        $route->delete();
        $bus->refresh();
        $this->assertNull($bus->route_id);
        $this->assertNull($bus->route);
    }
}
