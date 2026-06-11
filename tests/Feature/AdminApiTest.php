<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bus;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);
    }

    public function test_admin_can_crud_users()
    {
        // CREATE
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'New Driver',
            'email' => 'driver@new.com',
            'password' => 'password123',
            'role' => 'driver',
            'is_active' => true
        ]);
        $response->assertStatus(201);
        $userId = $response->json('data.id');
        $this->assertDatabaseHas('users', ['email' => 'driver@new.com']);

        // READ
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/users')
             ->assertStatus(200)
             ->assertJsonCount(2, 'data'); // Admin itself + New Driver

        // UPDATE
        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/admin/users/{$userId}", [
            'name' => 'Updated Driver Name'
        ])->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $userId, 'name' => 'Updated Driver Name']);

        // DELETE
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/admin/users/{$userId}")
             ->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_can_crud_buses()
    {
        // CREATE
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/buses', [
            'plate_number' => 'B 9999 XX',
            'capacity' => 60,
            'year' => 2024,
            'status' => true
        ]);
        $response->assertStatus(201);
        $busId = $response->json('data.id');

        // READ
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/admin/buses/{$busId}")
             ->assertStatus(200)
             ->assertJsonPath('data.plate_number', 'B 9999 XX');
             
        // DELETE
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/admin/buses/{$busId}")
             ->assertStatus(200);
    }

    public function test_admin_can_upload_csv()
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('schedule.csv', 100);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/csv-uploads', [
            'file' => $file
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('csv_uploads', [
            'status' => 'pending'
        ]);
        
        $path = $response->json('data.filename');
        Storage::disk('local')->assertExists($path);
    }

    public function test_passenger_cannot_access_admin_endpoints()
    {
        $passenger = User::create([
            'name' => 'Just Passenger',
            'email' => 'passenger_admin_test@test.com',
            'password' => bcrypt('password'),
            'role' => 'passenger'
        ]);

        $response = $this->actingAs($passenger, 'sanctum')->getJson('/api/admin/users');
        $response->assertStatus(403)
                 ->assertJsonPath('message', 'Forbidden: You do not have the required role to access this endpoint.');
    }

    public function test_operator_can_access_admin_endpoints()
    {
        $operator = User::create([
            'name' => 'Operator',
            'email' => 'operator_admin_test@test.com',
            'password' => bcrypt('password'),
            'role' => 'operator'
        ]);

        $response = $this->actingAs($operator, 'sanctum')->getJson('/api/admin/users');
        $response->assertStatus(200);
    }
}
