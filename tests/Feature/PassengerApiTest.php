<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Route;
use App\Models\Halte;
use App\Models\Bus;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\Driver;
use App\Models\Conductor;
use App\Models\Notification;

class PassengerApiTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::create([
            'name' => 'Passenger Test',
            'email' => 'passenger@test.com',
            'password' => bcrypt('password'),
            'role' => 'passenger'
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance' => 50000
        ]);
    }

    public function test_can_get_routes()
    {
        Route::create(['code' => 'R1', 'name' => 'Route 1', 'is_active' => true]);
        Route::create(['code' => 'R2', 'name' => 'Route 2', 'is_active' => false]);

        $response = $this->getJson('/api/routes');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.code', 'R1');
    }

    public function test_user_can_topup_wallet()
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/wallet/topup', [
            'amount' => 20000
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $this->user->id,
            'balance' => 70000
        ]);
        
        $this->assertDatabaseHas('wallet_transaction_histories', [
            'type' => 'top_up',
            'amount' => 20000
        ]);
    }

    public function test_user_can_book_and_complete_trip()
    {
        $route = Route::create(['code' => 'R1', 'name' => 'Route 1']);
        $halte1 = Halte::create(['code' => 'H1', 'name' => 'Halte 1']);
        $halte2 = Halte::create(['code' => 'H2', 'name' => 'Halte 2']);
        $bus = Bus::create(['plate_number' => 'B 1234 CD', 'capacity' => 40]);
        $driverUser = User::create(['name' => 'Driver', 'email' => 'driver@test.com', 'password' => bcrypt('pw')]);
        $driver = Driver::create(['user_id' => $driverUser->id, 'employee_id' => 'D001']);
        $conductorUser = User::create(['name' => 'Cond', 'email' => 'cond@test.com', 'password' => bcrypt('pw')]);
        $conductor = Conductor::create(['user_id' => $conductorUser->id, 'employee_id' => 'C001']);
        
        $schedule = Schedule::create([
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'is_active' => true
        ]);

        $trip = Trip::create([
            'schedule_id' => $schedule->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'conductor_id' => $conductor->id,
            'departure_time' => '08:00:00',
            'is_active' => true
        ]);

        // Booking
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/bookings', [
            'trip_id' => $trip->id,
            'boarding_stop_id' => $halte1->id,
            'arrive_stop_id' => $halte2->id,
            'fare' => 10000
        ]);

        $response->assertStatus(201);
        $bookingId = $response->json('data.id');
        
        // Cek wallet balance berkurang
        $this->assertDatabaseHas('wallets', ['user_id' => $this->user->id, 'balance' => 40000]);

        // Tap In
        $tapInResponse = $this->actingAs($this->user, 'sanctum')->postJson("/api/bookings/{$bookingId}/tap-in");
        $tapInResponse->assertStatus(200);
        $this->assertDatabaseHas('trip_bookings', ['id' => $bookingId, 'status' => 'active']);

        // Tap Out
        $tapOutResponse = $this->actingAs($this->user, 'sanctum')->postJson("/api/bookings/{$bookingId}/tap-out");
        $tapOutResponse->assertStatus(200);
        $this->assertDatabaseHas('trip_bookings', ['id' => $bookingId, 'status' => 'completed']);
        
        // Rating
        $ratingResponse = $this->actingAs($this->user, 'sanctum')->postJson('/api/ratings', [
            'trip_booking_id' => $bookingId,
            'rating' => 5,
            'review' => 'Sopirnya ramah'
        ]);
        $ratingResponse->assertStatus(200);
        $this->assertDatabaseHas('driver_ratings', ['rating' => 5]);
    }

    public function test_user_can_manage_favourites()
    {
        $route = Route::create(['code' => 'R1', 'name' => 'Route 1']);
        
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/favourites', [
            'route_id' => $route->id,
            'label' => 'Kantor'
        ]);
        
        $response->assertStatus(201);
        $favId = $response->json('data.id');
        
        $this->assertDatabaseHas('favourite_routes', ['route_id' => $route->id, 'label' => 'Kantor']);
        
        $deleteResponse = $this->actingAs($this->user, 'sanctum')->deleteJson("/api/favourites/{$favId}");
        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('favourite_routes', ['id' => $favId]);
    }

    public function test_user_can_read_notifications()
    {
        $notif = Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Test Notif',
            'body' => 'Halo ini notif',
            'is_read' => false
        ]);
        
        $response = $this->actingAs($this->user, 'sanctum')->patchJson("/api/notifications/{$notif->id}/read");
        $response->assertStatus(200);
        $this->assertDatabaseHas('notifications', ['id' => $notif->id, 'is_read' => true]);
    }
}
