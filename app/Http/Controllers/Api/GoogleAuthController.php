<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google
     */
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle callback from Google
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Find or Create User
            $user = User::where('email', $googleUser->getEmail())->first();
            
            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(Str::random(24)),
                    'role' => 'passenger',
                    'is_active' => true,
                ]);

                // Create profile and wallet for new passenger
                UserProfile::create([
                    'user_id' => $user->id,
                ]);
                
                Wallet::create([
                    'user_id' => $user->id,
                    'balance' => 0,
                ]);
            } else {
                // Update google ID if not set
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleUser->getId()]);
                }
            }

            // Create token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirect back to frontend with the token
            return redirect()->to(env('FRONTEND_URL', 'http://localhost:8000') . '/auth/google/success?token=' . $token);

        } catch (\Exception $e) {
            return redirect()->to(env('FRONTEND_URL', 'http://localhost:8000') . '/login?error=GoogleLoginFailed');
        }
    }
}
