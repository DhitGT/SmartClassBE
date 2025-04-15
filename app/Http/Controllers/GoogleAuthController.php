<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\User;

use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            $googleAvatarUrl = $payload['picture'] ?? null;

            $user = User::where('email', $email)->first();

            if (!$user) {
                // Upload avatar either from file or from Google
                $avatarPath = null;

                // Fetch avatar from Google profile
                $contents = file_get_contents($googleAvatarUrl);
                $filename = time() . '_google_avatar.jpg';
                Storage::disk('public')->put('avatars/' . $filename, $contents);
                $avatarPath = 'avatars/' . $filename;


                $user = User::create([
                    'name' => $name,
                    'role' => 'Leader',
                    'avatar' => $avatarPath,
                    'email' => $email,
                    'password' => bcrypt(Str::random(16)),
                    'google_id' => $googleId,
                    'email_verified_at' => now(),
                ]);

                ClassModel::create([
                    'name' => $user->name . '`s Class',
                    'leader_id' => $user->id,
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
