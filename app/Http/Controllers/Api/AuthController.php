<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => 'nullable|string|max:20|unique:users',
            'citizenship' => 'nullable|in:WNI,WNA',
            'identity_type' => 'nullable|in:KTP,Pasport',
            'identity_number' => 'nullable|string|max:50|unique:users',
            // Tambahkan validasi untuk field lain jika perlu
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'citizenship' => $request->citizenship,
            'identity_type' => $request->identity_type,
            'identity_number' => $request->identity_number,
            'role' => 'User', // Default role
            'points' => 0,   // Default points
        ]);

        // Opsional: Langsung login dan berikan token
        // $token = $user->createToken('auth-token')->plainTextToken;
        // return response()->json(['user' => $user, 'token' => $token], 201);

        return response()->json(['message' => 'User registered successfully', 'user' => $user], 201);
    }

    /**
     * Login user and return a token.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid login credentials'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth-token-' . $user->id . '-' . Str::random(5))->plainTextToken; // Nama token bisa lebih deskriptif

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'bearer_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout user (revoke the current token).
     */
    public function logout(Request $request)
    {
        if ($request->user()) { // Memastikan user terotentikasi
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Get the authenticated User.
     */
    public function userProfile(Request $request)
    {
        return response()->json($request->user());
    }

    public function redirectToGoogle()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider  */
        $googleDriver = Socialite::driver('google');
        return $googleDriver->stateless()->redirect();

        // return Socialite::driver('google')->stateless()->redirect();
        // stateless() penting untuk API
    }

    public function handleGoogleCallback()
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $googleDriver */
            $googleDriver = Socialite::driver('google');
            $googleUser = $googleDriver->stateless()->user();; // Mengambil informasi user dari Google
            // Cari user berdasarkan google_id atau email
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();
            // $user = User::where('email', $googleUser->email)->firstOrFail();

            if (!$user) {
                // Jika user tidak ada, buat user baru
                // Pastikan semua field yang required diisi
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)), // Password acak karena login via Google
                    'email_verified_at' => now(), // Asumsikan email dari Google sudah terverifikasi
                    'role' => 'User',
                    'points' => 0,
                    // Isi field lain yang wajib atau bisa Anda dapatkan/defaultkan
                ]);
            } else {
                // Jika user ada, update google_id dan avatar jika belum ada/berbeda
                if (empty($user->google_id)) {
                    $user->google_id = $googleUser->getId();
                }
                if (empty($user->avatar) && $googleUser->getAvatar()) {
                    $user->avatar = $googleUser->getAvatar();
                }
                if (!$user->email_verified_at && $googleUser->getEmail()) {
                    $user->email_verified_at = now();
                }
                $user->save();
            }

            // Generate token untuk user
            $token = $user->createToken('google-auth-token-' . $user->id)->plainTextToken;

            // Untuk API, idealnya kembalikan token dalam JSON.
            // Frontend akan menangani redirect jika perlu.
            // Jika frontend Anda bisa menangani query param setelah redirect:
            // $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000'); // URL frontend Anda
            // return redirect($frontendUrl . '/auth/callback?token=' . $token . '&user_name=' . urlencode($user->name));

            return response()->json([
                'message' => 'Google authentication successful',
                'user' => $user,
                'bearer_token' => $token,
                'token_type' => 'Bearer',
            ]);
        } catch (\Exception $e) {
            // Log errornya
            Log::error('Google Auth Callback Error: ' . $e->getMessage());
            return response()->json(['error' => 'Google authentication failed. Please try again. Message: ' . $e->getMessage()], 401);
        }
    }
}
