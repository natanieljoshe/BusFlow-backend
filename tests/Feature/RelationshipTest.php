<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\User;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Eloquent relationships and schema additions for Bus, Driver, and Route models.
     */
    public function test_bus_driver_route_relationships()
    {
        // 1. Create a route with fare_per_km
        $route = Route::create([
            'code' => 'R_TEST_REL',
            'name' => 'Test Relationship Route',
            'total_distance_km' => 15.50,
            'fare_per_km' => 1500.00,
        ]);

        // 2. Create a driver (with corresponding user)
        $user = User::create([
            'name' => 'Driver J',
            'email' => 'driverj@test.com',
            'password' => bcrypt('password'),
            'role' => 'driver'
        ]);

        $driver = Driver::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP1001',
            'phone' => '081234567890',
            'is_available' => true,
        ]);

        // 3. Create a bus with driver and route assignment
        $bus = Bus::create([
            'plate_number' => 'B 1001 REL',
            'capacity' => 40,
            'year' => 2025,
            'brand' => 'Hino',
            'status' => true,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
        ]);

        // 4. Verify Bus relationships
        $this->assertNotNull($bus->driver);
        $this->assertEquals($driver->id, $bus->driver->id);
        $this->assertEquals('EMP1001', $bus->driver->employee_id);

        $this->assertNotNull($bus->route);
        $this->assertEquals($route->id, $bus->route->id);
        $this->assertEquals('R_TEST_REL', $bus->route->code);

        // 5. Verify Driver relationship
        $this->assertTrue($driver->buses->contains('id', $bus->id));

        // 6. Verify Route relationship
        $this->assertTrue($route->buses->contains('id', $bus->id));

        // 7. Verify fare_per_km attributes
        $this->assertEquals(1500.00, $bus->route->fare_per_km);
    }

    /**
     * Test global config setting for fee_per_km.
     */
    public function test_global_fee_per_km_config()
    {
        $fee = config('app.fee_per_km');
        $this->assertNotNull($fee);
        $this->assertEquals(1000.00, $fee);
    }
}
