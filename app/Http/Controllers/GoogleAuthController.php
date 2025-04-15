<?php

namespace App\Http\Controllers;

use App\Models\User;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    //
    public function handleGoogleCallback(Request $request)
    {
        try {
            $client = new Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->token);
            
            if (!$payload) {
                return response()->json(['error' => 'Invalid token'], 401);
            }
            
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? 'Google User';
            
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'role' => 'Leader',
                    'email' => $email,
                    'password' => bcrypt(Str::random(16)),
                    'google_id' => $googleId,
                    'email_verified_at' => now(),
                ]);
            } elseif (empty($user->google_id)) {
                $user->update(['google_id' => $googleId]);
            }
            
            $token = $user->createToken('google-token')->plainTextToken;
            
            return response()->json([
                'token' => $token,
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Google authentication failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }
}
