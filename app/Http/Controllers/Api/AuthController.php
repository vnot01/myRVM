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
use Illuminate\Support\Facades\Cache; // Untuk menyimpan token RVM sementara
use App\Traits\HandlesGoogleUser; // Import Trait

class AuthController extends Controller
{
    use HandlesGoogleUser; // Gunakan Trait
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
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        if (!empty($request->phone_number)) {
            $userPhone = $request->phone_number;
        } else {
            $userPhone = null;
        }
        if (!empty($request->citizenship)) {
            $userCitizenship = $request->citizenship;
        } else {
            $userCitizenship = null;
        }
        if (!empty($request->identity_type)) {
            $userIdentityType = $request->identity_type;
        } else {
            $userIdentityType = null;
        }
        if (!empty($request->identity_number)) {
            $userIdentityNumber = $request->identity_number;
        } else {
            $userIdentityNumber = null;
        }
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $userPhone,
            'citizenship' => $userCitizenship,
            'identity_type' => $userIdentityType,
            'identity_number' => $userIdentityNumber,
            'role' => 'User', // Default role
            'points' => 0,   // Default points
        ]);

        // Buat token Sanctum untuk user yang baru register
        $token = $user->createToken('api_auth_token_register')->plainTextToken;
        // return response()->json(['user' => $user, 'token' => $token], 201);

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user // Mengembalikan data user juga bisa berguna
        ], 201);
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
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['status' => 'error', 'message' => 'Invalid login credentials'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        // $token = $user->createToken('auth-token-' . $user->id . '-' . Str::random(5))->plainTextToken; // Nama token bisa lebih deskriptif
        $token = $user->createToken('api_auth_token_login')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User logged in successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    /**
     * Logout user (revoke the current token).
     */
    public function logout(Request $request)
    {
        // Revoke token yang digunakan untuk request ini
        // $request->user()->currentAccessToken()->delete();
        if ($request->user()) { // Memastikan user terotentikasi
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['status' => 'success', 'message' => 'User logged out successfully']);
    }

    /**
     * Get the authenticated User.
     */
    public function userProfile(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user' => $request->user() // Mengembalikan data user yang sedang login
        ]);
    }

    /**
     * Handles Google Sign-In for API clients by validating an ID token.
     */
    public function signInWithGoogleIdToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Google ID token is required.', 'errors' => $validator->errors()], 422);
        }

        $idToken = $request->input('id_token');

        try {
            // Anda PERLU 'google/apiclient': composer require google/apiclient
            $client = new \Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($idToken);

            if ($payload) {
                // Gunakan metode dari Trait
                $user = $this->findOrCreateUserFromGoogleIdTokenPayload($payload);

                $token = $user->createToken('api_auth_token_google')->plainTextToken;

                return response()->json([
                    'status' => 'success',
                    'message' => 'User authenticated successfully with Google',
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => $user
                ]);
            } else {
                Log::warning('Invalid Google ID token received by API.', ['id_token_start' => substr($idToken, 0, 50)]);
                return response()->json(['status' => 'error', 'message' => 'Invalid Google ID token.'], 401);
            }
        } catch (\Exception $e) {
            Log::error('API Google ID Token Sign-In Error: ' . $e->getMessage(), ['trace' => substr($e->getTraceAsString(), 0, 500)]);
            return response()->json(['status' => 'error', 'message' => 'Failed to authenticate with Google. ' . $e->getMessage()], 500);
        }
    }

    // // Metode Google Auth redirectToGoogle dan handleGoogleCallback bisa tetap sama
    // // seperti sebelumnya, namun handleGoogleCallback perlu dimodifikasi untuk API
    // // jika aplikasi user adalah SPA/Mobile yang menangani redirect OAuth.
    // // Jika aplikasi user adalah web tradisional, redirect bisa langsung.
    // // Untuk API (SPA/Mobile), handleGoogleCallback biasanya akan:
    // // 1. Mendapatkan user dari Google.
    // // 2. Membuat/update user di DB.
    // // 3. Membuat token Sanctum.
    // // 4. Mengembalikan token ini ke frontend (misalnya, via redirect dengan token di query param,
    // //    atau frontend melakukan polling setelah window OAuth ditutup). Ini agak tricky.
    // // Atau, frontend mobile/SPA menggunakan SDK Google Sign-In sisi klien untuk mendapatkan ID Token,
    // // lalu mengirim ID Token itu ke backend API untuk divalidasi dan login/register.
    // public function loginWithGoogleToken(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'id_token' => 'required|string', // ID Token dari Google Sign-In Client
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
    //     }

    //     try {
    //         // Ini cara validasi ID Token Google sisi server (memerlukan library google/apiclient)
    //         // $client = new \Google_Client(['client_id' => config('services.google.client_id')]);
    //         // $payload = $client->verifyIdToken($request->id_token);
    //         // if ($payload) {
    //         //     $googleUserEmail = $payload['email'];
    //         //     $googleUserName = $payload['name'];
    //         //     $googleUserId = $payload['sub']; // Google User ID
    //         //     $googleUserAvatar = $payload['picture'] ?? null;

    //         // Untuk kesederhanaan, kita mock data seolah-olah dari Socialite,
    //         // asumsikan frontend telah mengambil info ini dan mengirimkannya,
    //         // atau kita gunakan Socialite jika alurnya web-based redirect.
    //         // Jika ingin benar-benar aman dengan ID Token, implementasi validasi token Google diperlukan.

    //         // Untuk sekarang, kita asumsikan kita tetap menggunakan alur redirect Socialite dari web
    //         // atau frontend mengirimkan data yang diperlukan setelah login Google di client.
    //         // Jika frontend mengirim data user Google (BUKAN cara paling aman tanpa validasi token):
    //         // $googleUserData = $request->input('google_user_data'); // Misal {id, name, email, avatar}
    //         // $user = User::updateOrCreate(
    //         //     ['google_id' => $googleUserData['id']],
    //         //     [
    //         //         'name' => $googleUserData['name'],
    //         //         'email' => $googleUserData['email'],
    //         //         'avatar' => $googleUserData['avatar'] ?? null,
    //         //         'email_verified_at' => now(),
    //         //         'password' => Hash::make(Str::random(24)),
    //         //     ]
    //         // );
    //         // $token = $user->createToken('auth_token')->plainTextToken;
    //         // return response()->json([...]);

    //         // Untuk sekarang, kita fokus pada email/password dan biarkan Google Auth seperti di web.
    //         // Jika Aplikasi User Anda adalah SPA/Mobile yang menangani OAuth flow,
    //         // maka endpoint callback perlu disesuaikan untuk mengembalikan token.
    //         return response()->json(['status' => 'info', 'message' => 'Google login via API token validation needs specific client-side flow handling.'], 501);
    //     } catch (\Exception $e) {
    //         Log::error("Google Login API error: " . $e->getMessage());
    //         return response()->json(['status' => 'error', 'message' => 'Google login failed.'], 500);
    //     }
    // }

    // // Metode redirectToGoogle dan handleGoogleCallback dari GoogleAuthController
    // // bisa dipindahkan ke sini jika ingin AuthController menangani semua.
    // // Pastikan handleGoogleCallback dimodifikasi untuk API jika perlu.
    // public function redirectToGoogle()
    // {
    //     if (!config('services.google.client_id')) {
    //         return response()->json(['status' => 'error', 'message' => 'Google Sign-In not configured on server.'], 503);
    //     }
    //     /** @var \Laravel\Socialite\Two\GoogleProvider  */
    //     $googleDriver = Socialite::driver('google');
    //     return $googleDriver->stateless()->redirect();
    // }

    // public function handleGoogleCallback()
    // {
    //     try {
    //         if (!config('services.google.client_id')) {
    //             return response()->json(['status' => 'error', 'message' => 'Google Sign-In not configured on server.'], 503);
    //         }

    //         /** @var \Laravel\Socialite\Two\GoogleProvider $googleDriver */
    //         $googleDriver = Socialite::driver('google');
    //         $googleUser = $googleDriver->stateless()->user(); // Mengambil informasi user dari Google

    //         $user = User::updateOrCreate(
    //             ['google_id' => $googleUser->getId()],
    //             [
    //                 'name' => $googleUser->getName(),
    //                 'email' => $googleUser->getEmail(),
    //                 'avatar' => $googleUser->getAvatar(),
    //                 'email_verified_at' => now(),
    //                 'password' => Hash::make(Str::random(24)),
    //             ]
    //         );
    //         $token = $user->createToken('auth_token_google')->plainTextToken;

    //         // Untuk API, kita tidak bisa redirect ke '/dashboard' dengan session.
    //         // Kita perlu mengembalikan token ke frontend.
    //         // Cara paling umum adalah redirect ke URL frontend dengan token sebagai query param.
    //         // Frontend kemudian mengambil token ini dan menyimpannya.
    //         // Pastikan URL frontend Anda bisa menangani ini.
    //         $frontendUrl = config('app.frontend_url', 'http://localhost:3000'); // Ambil dari .env atau default
    //         return redirect()->to($frontendUrl . '/auth/callback?token=' . $token . '&user_name=' . urlencode($user->name));
    //     } catch (\Exception $e) {
    //         Log::error('Google Callback Error: ' . $e->getMessage());
    //         $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
    //         return redirect()->to($frontendUrl . '/auth/callback?error=' . urlencode('Google login failed. Please try again.'));
    //     }

    //     //     // Cari user berdasarkan google_id atau email
    //     //     $user = User::where('google_id', $googleUser->getId())
    //     //         ->orWhere('email', $googleUser->getEmail())
    //     //         ->first();
    //     //     // $user = User::where('email', $googleUser->email)->firstOrFail();

    //     //     if (!$user) {
    //     //         // Jika user tidak ada, buat user baru
    //     //         // Pastikan semua field yang required diisi
    //     //         $user = User::create([
    //     //             'name' => $googleUser->getName(),
    //     //             'email' => $googleUser->getEmail(),
    //     //             'google_id' => $googleUser->getId(),
    //     //             'avatar' => $googleUser->getAvatar(),
    //     //             'password' => Hash::make(Str::random(24)), // Password acak karena login via Google
    //     //             'email_verified_at' => now(), // Asumsikan email dari Google sudah terverifikasi
    //     //             'role' => 'User',
    //     //             'points' => 0,
    //     //             // Isi field lain yang wajib atau bisa Anda dapatkan/defaultkan
    //     //         ]);
    //     //     } else {
    //     //         // Jika user ada, update google_id dan avatar jika belum ada/berbeda
    //     //         if (empty($user->google_id)) {
    //     //             $user->google_id = $googleUser->getId();
    //     //         }
    //     //         if (empty($user->avatar) && $googleUser->getAvatar()) {
    //     //             $user->avatar = $googleUser->getAvatar();
    //     //         }
    //     //         if (!$user->email_verified_at && $googleUser->getEmail()) {
    //     //             $user->email_verified_at = now();
    //     //         }
    //     //         $user->save();
    //     //     }

    //     //     // Generate token untuk user
    //     //     $token = $user->createToken('google-auth-token-' . $user->id)->plainTextToken;

    //     //     // Untuk API, idealnya kembalikan token dalam JSON.
    //     //     // Frontend akan menangani redirect jika perlu.
    //     //     // Jika frontend Anda bisa menangani query param setelah redirect:
    //     //     // $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000'); // URL frontend Anda
    //     //     // return redirect($frontendUrl . '/auth/callback?token=' . $token . '&user_name=' . urlencode($user->name));

    //     //     return response()->json([
    //     //         'message' => 'Google authentication successful',
    //     //         'user' => $user,
    //     //         'bearer_token' => $token,
    //     //         'token_type' => 'Bearer',
    //     //     ]);
    //     // } catch (\Exception $e) {
    //     //     // Log errornya
    //     //     Log::error('Google Auth Callback Error: ' . $e->getMessage());
    //     //     return response()->json(['error' => 'Google authentication failed. Please try again. Message: ' . $e->getMessage()], 401);
    //     // }
    // }
}
