<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // Jika perlu membuat user baru
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request; // Tambahkan ini

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request) // Tambahkan Request $request
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::updateOrCreate(
                ['google_id' => $googleUser->getId()],
                [
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(), // Anggap email terverifikasi dari Google
                    'password' => Hash::make(str()->random(24)), // Password acak, karena login via Google
                    // 'role' => 'User', // Default role sudah 'User' di model
                    // 'points' => 0, // Default points sudah 0 di model
                ]
            );

            Auth::login($user, true); // Login user dan buat session "remember me"

            // Redirect ke halaman yang diinginkan setelah login
            // Misalnya, halaman dashboard pengguna
            return redirect()->intended('/dashboard'); // Sesuaikan '/dashboard'

        } catch (\Exception $e) {
            // Log error atau tampilkan pesan error
            // return redirect('/login')->withErrors(['google_login_failed' => 'Tidak dapat login menggunakan Google. Silakan coba lagi.']);
            return redirect('/login')->with('error', 'Login Google gagal: ' . $e->getMessage());
        }
    }
}
