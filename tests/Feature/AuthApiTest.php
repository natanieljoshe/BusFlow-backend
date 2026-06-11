<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Passenger',
            'email' => 'john@example.com',
            'password' => 'password123',
            'phone' => '08123456789'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'user' => [
                             'id', 'name', 'email', 'role', 'profile', 'wallet'
                         ],
                         'token'
                     ]
                 ]);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com', 'role' => 'passenger']);
        $this->assertDatabaseHas('user_profiles', ['phone' => '08123456789']);
        $this->assertDatabaseHas('wallets', ['balance' => 0]);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'user',
                         'token'
                     ]
                 ]);
    }

    public function test_user_cannot_login_if_inactive()
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_get_me_profile()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'name', 'email'
                     ]
                 ]);
    }

    public function test_user_can_logout()
    {
        $user = User::factory()->create();

        // Acting as simulates a logged-in user with sanctum
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/logout');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Logged out successfully']);
    }
}
