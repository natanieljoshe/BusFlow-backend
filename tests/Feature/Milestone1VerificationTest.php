<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Bus;

class Milestone1VerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that saving a Bus with an assigned driver and route is successful.
     */
    public function test_save_bus_with_assigned_driver_and_route()
    {
        // 1. Create a user for the driver
        $user = User::create([
            'name' => 'Driver Name',
            'email' => 'driver@example.com',
            'password' => bcrypt('password'),
            'role' => 'driver',
        ]);

        // 2. Create the driver
        $driver = Driver::create([
            'user_id' => $user->id,
            'employee_id' => 'DRV001',
            'phone' => '08123456789',
            'is_available' => true,
            'joined_at' => now(),
        ]);

        // 3. Create a route
        $route = Route::create([
            'code' => 'R001',
            'name' => 'Route 1',
            'total_distance_km' => 15.5,
            'fare_per_km' => 1500.00,
        ]);

        // 4. Create and save a bus with driver and route assigned
        $bus = Bus::create([
            'plate_number' => 'B 1234 ABC',
            'capacity' => 40,
            'year' => 2022,
            'brand' => 'Hino',
            'status' => true,
            'total_distance' => 0.00,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
        ]);

        // 5. Assert database has the record
        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'plate_number' => 'B 1234 ABC',
            'driver_id' => $driver->id,
            'route_id' => $route->id,
        ]);

        // 6. Test Eloquent relationships
        $this->assertEquals($driver->id, $bus->driver->id);
        $this->assertEquals($route->id, $bus->route->id);

        $this->assertTrue($driver->buses->contains($bus));
        $this->assertTrue($route->buses->contains($bus));
    }

    /**
     * Verify that deleting a Driver sets driver_id in buses table to null.
     */
    public function test_delete_driver_sets_driver_id_to_null()
    {
        $user = User::create([
            'name' => 'Driver Name',
            'email' => 'driver@example.com',
            'password' => bcrypt('password'),
            'role' => 'driver',
        ]);

        $driver = Driver::create([
            'user_id' => $user->id,
            'employee_id' => 'DRV001',
            'phone' => '08123456789',
            'is_available' => true,
            'joined_at' => now(),
        ]);

        $bus = Bus::create([
            'plate_number' => 'B 1234 ABC',
            'capacity' => 40,
            'driver_id' => $driver->id,
        ]);

        $this->assertEquals($driver->id, $bus->driver_id);

        // Delete Driver
        $driver->delete();

        // Refresh bus from DB
        $bus->refresh();

        $this->assertNull($bus->driver_id);
    }

    /**
     * Verify that deleting a Route sets route_id in buses table to null.
     */
    public function test_delete_route_sets_route_id_to_null()
    {
        $route = Route::create([
            'code' => 'R001',
            'name' => 'Route 1',
        ]);

        $bus = Bus::create([
            'plate_number' => 'B 1234 ABC',
            'capacity' => 40,
            'route_id' => $route->id,
        ]);

        $this->assertEquals($route->id, $bus->route_id);

        // Delete Route
        $route->delete();

        // Refresh bus from DB
        $bus->refresh();

        $this->assertNull($bus->route_id);
    }

    /**
     * Verify that creating a Route sets the default fare_per_km to 0.00.
     */
    public function test_default_fare_per_km_is_zero()
    {
        $route = Route::create([
            'code' => 'R002',
            'name' => 'Route 2',
        ]);

        $this->assertEquals(0.00, (float) $route->fare_per_km);
    }

    /**
     * Verify that fetching the global fee_per_km from config matches the .env value.
     */
    public function test_global_fee_per_km_matches_env()
    {
        // Get the value from config
        $configValue = config('app.fee_per_km');
        
        // Assert it is set and equals to 1000.00 (from .env)
        $this->assertNotNull($configValue);
        $this->assertEquals(1000.00, (float) $configValue);
    }
}
