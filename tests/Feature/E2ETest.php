<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Halte;
use App\Models\Wallet;
use App\Models\Trip;
use App\Models\Schedule;
use App\Models\TripBooking;

class E2ETest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $operator;
    private $passenger;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin User
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@busflow.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        // Operator User
        $this->operator = User::create([
            'name' => 'Operator User',
            'email' => 'operator@busflow.com',
            'password' => bcrypt('password'),
            'role' => 'operator'
        ]);

        // Passenger User
        $this->passenger = User::create([
            'name' => 'Passenger User',
            'email' => 'passenger@busflow.com',
            'password' => bcrypt('password'),
            'role' => 'passenger'
        ]);

        // Passenger Wallet
        Wallet::create([
            'user_id' => $this->passenger->id,
            'balance' => 100000.00
        ]);
    }

    /**
     * Test Role/Access Management API endpoints (Admin vs Operator).
     */
    public function test_role_access_management_permissions()
    {
        // Admin can access users and buses admin endpoints
        $responseAdminUser = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/users');
        $responseAdminUser->assertStatus(200);

        $responseAdminBus = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/buses');
        $responseAdminBus->assertStatus(200);

        // Operator is restricted from admin users and buses endpoints
        $responseOpUser = $this->actingAs($this->operator, 'sanctum')->getJson('/api/admin/users');
        $responseOpUser->assertStatus(403);

        $responseOpBus = $this->actingAs($this->operator, 'sanctum')->getJson('/api/admin/buses');
        $responseOpBus->assertStatus(403);

        // Operator can access allowed operator endpoints (like routes, haltes)
        $responseOpRoute = $this->actingAs($this->operator, 'sanctum')->getJson('/api/admin/routes');
        $responseOpRoute->assertStatus(200);

        $responseOpHalte = $this->actingAs($this->operator, 'sanctum')->getJson('/api/admin/haltes');
        $responseOpHalte->assertStatus(200);
    }

    /**
     * Test Fleet Assignment API endpoints (PUT /api/admin/buses/{id} with driver_id and route_id).
     */
    public function test_fleet_assignment_endpoint()
    {
        // Create mock driver and route
        $driverUser = User::create([
            'name' => 'Driver User',
            'email' => 'driver_test@busflow.com',
            'password' => bcrypt('password'),
            'role' => 'driver'
        ]);
        
        $route = Route::create([
            'code' => 'R_E2E',
            'name' => 'Route E2E'
        ]);

        $bus = Bus::create([
            'plate_number' => 'B 7777 E2E',
            'capacity' => 50
        ]);

        // Attempt assignment
        $response = $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/buses/{$bus->id}", [
            'driver_id' => $driverUser->id,
            'route_id' => $route->id
        ]);

        // Assert contract response
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => 'Bus updated successfully'
        ]);

        $this->assertDatabaseHas('buses', [
            'id' => $bus->id,
            'driver_id' => $driverUser->id,
            'route_id' => $route->id
        ]);
    }

    /**
     * Test Distance/Fare calculation API endpoints during Tap-Out.
     */
    public function test_distance_fare_calculation_tap_out()
    {
        // Setup Route, Haltes, Schedule, Trip
        $route = Route::create([
            'code' => 'R_FARE',
            'name' => 'Route Fare Test',
            'total_distance_km' => 20,
            // 'fare_per_km' => 1000 // To be added in M1
        ]);

        // Halte A (Monas): lat: -6.175392, lng: 106.827153
        $halteA = Halte::create([
            'code' => 'H_MONAS',
            'name' => 'Monas',
            'latitude' => -6.175392,
            'longitude' => 106.827153
        ]);

        // Halte B (Bundaran HI): lat: -6.194982, lng: 106.823055
        // Distance between Monas and Bundaran HI is approx 2.22 km
        $halteB = Halte::create([
            'code' => 'H_BHI',
            'name' => 'Bundaran HI',
            'latitude' => -6.194982,
            'longitude' => 106.823055
        ]);

        $bus = Bus::create([
            'plate_number' => 'B 8888 FAR',
            'capacity' => 45
        ]);

        $schedule = Schedule::create([
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'is_active' => true
        ]);

        $trip = Trip::create([
            'schedule_id' => $schedule->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'departure_time' => '09:00:00',
            'is_active' => true
        ]);

        $booking = TripBooking::create([
            'user_id' => $this->passenger->id,
            'trip_id' => $trip->id,
            'boarding_stop_id' => $halteA->id,
            'arrive_stop_id' => $halteB->id,
            'status' => 'active',
            'fare' => 5000,
            'qr_code_token' => 'qr_fare_test_token',
            'tapped_in_at' => now()->subMinutes(15)
        ]);

        $response = $this->actingAs($this->passenger, 'sanctum')->postJson("/api/bookings/{$booking->id}/tap-out");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'status',
                'fare',
                'tapped_out_at'
            ]
        ]);
        
        $this->assertEquals('completed', $response->json('data.status'));
    }

    /**
     * Test Wallet Top-up API endpoints and validation constraints.
     */
    public function test_wallet_topup_and_validation()
    {
        // Valid top-up
        $responseValid = $this->actingAs($this->passenger, 'sanctum')->postJson('/api/wallet/topup', [
            'amount' => 50000
        ]);

        $responseValid->assertStatus(200);
        $responseValid->assertJsonFragment([
            'message' => 'Top up berhasil'
        ]);

        // Invalid top-up: zero amount
        $responseZero = $this->actingAs($this->passenger, 'sanctum')->postJson('/api/wallet/topup', [
            'amount' => 0
        ]);
        $responseZero->assertStatus(422);

        // Invalid top-up: negative amount
        $responseNeg = $this->actingAs($this->passenger, 'sanctum')->postJson('/api/wallet/topup', [
            'amount' => -15000
        ]);
        $responseNeg->assertStatus(422);

        // Invalid top-up: non-numeric amount
        $responseNonNum = $this->actingAs($this->passenger, 'sanctum')->postJson('/api/wallet/topup', [
            'amount' => 'invalid_amount'
        ]);
        $responseNonNum->assertStatus(422);
    }

    /**
     * Test Halte creation API endpoints saving Latitude/Longitude coordinates.
     */
    public function test_halte_creation_with_coordinates()
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/haltes', [
            'name' => 'Halte E2E Test',
            'code' => 'H_E2E_99',
            'latitude' => -6.1234567,
            'longitude' => 106.7654321,
            'address' => 'E2E Test Road',
            'is_active' => true
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('haltes', [
            'code' => 'H_E2E_99',
            'latitude' => -6.1234567,
            'longitude' => 106.7654321
        ]);
    }

    /**
     * Test Boarding Scanner QR validation API endpoints.
     */
    public function test_boarding_scanner_qr_validation()
    {
        // Setup route/booking
        $route = Route::create([
            'code' => 'R_QR',
            'name' => 'Route QR Test'
        ]);
        $halteA = Halte::create([
            'code' => 'H_QR_A',
            'name' => 'QR Halte A'
        ]);
        $halteB = Halte::create([
            'code' => 'H_QR_B',
            'name' => 'QR Halte B'
        ]);
        $bus = Bus::create([
            'plate_number' => 'B 9999 QR',
            'capacity' => 30
        ]);
        $schedule = Schedule::create([
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'is_active' => true
        ]);
        $trip = Trip::create([
            'schedule_id' => $schedule->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'departure_time' => '10:00:00',
            'is_active' => true
        ]);

        $booking = TripBooking::create([
            'user_id' => $this->passenger->id,
            'trip_id' => $trip->id,
            'boarding_stop_id' => $halteA->id,
            'arrive_stop_id' => $halteB->id,
            'status' => 'booked',
            'fare' => 5000,
            'qr_code_token' => 'token_e2e_scanner_test',
            'booked_at' => now()
        ]);

        // Scanning Endpoint call (accessible by admin/operator)
        $response = $this->actingAs($this->operator, 'sanctum')->postJson('/api/admin/bookings/scan-qr', [
            'qr_code_token' => 'token_e2e_scanner_test'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'booking' => [
                'id',
                'status',
                'tapped_in_at'
            ]
        ]);
        
        $this->assertEquals('Scan boarding berhasil', $response->json('message'));
        $this->assertEquals('active', $response->json('booking.status'));
    }
}
