<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use App\Traits\HandlesGoogleUser;

class GoogleAuthController extends Controller
{
    use HandlesGoogleUser;

    public function redirectToGoogle()
    {
        Log::info('Redirecting to Google...'); // Log saat redirect
        // if (!config('services.google.client_id') || !config('services.google.redirect_uri_web')) {
        //     Log::error('Google Sign-In configuration missing.');
        //     return redirect()->route('login')
        //         ->with('error', 'Google Sign-In is not configured properly on the server.');
        // }
        // try {
        //     return Socialite::driver('google')->redirect();
        // } catch (\Exception $e) {
        //     Log::error('Error during Socialite redirect: ' . $e->getMessage());
        //     return redirect()->route('login')
        //         ->with('error', 'Could not redirect to Google. Please check server configuration.');
        // }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        Log::info('+++ Reached handleGoogleCallback method +++'); // <--- LOG PALING AWAL

        if ($request->has('error')) {
            Log::error('Google returned an error:', ['error' => $request->input('error'), 'error_description' => $request->input('error_description')]);
            return redirect()->route('login')
                ->with('error', 'Google login failed: ' . $request->input('error_description', $request->input('error')));
        }

        if (!config('services.google.client_id')) {
            Log::error('Google Sign-In configuration missing during callback.');
            return redirect()->route('login')
                ->with('error', 'Google Sign-In is not configured properly on the server.');
        }

        try {
            Log::info('Attempting to get user from Socialite...'); // <--- LOG SEBELUM SOCIALITE USER
            // Coba tambahkan ->stateless() untuk melihat apakah masalahnya di state session
            // $googleUser = Socialite::driver('google')->stateless()->user(); 
            $googleUser = Socialite::driver('google')->user(); // Coba tanpa stateless dulu
            Log::info('Google User Data Retrieved:', ['email' => $googleUser->getEmail(), 'name' => $googleUser->getName()]);

            Log::info('Attempting to find or create local user...');
            $user = $this->findOrCreateUserFromGoogle($googleUser);
            Log::info('Local User Created/Found:', ['id' => $user->id, 'email' => $user->email, 'role' => $user->role]);

            Log::info('Attempting Auth::login...');
            Auth::login($user, true);
            $isAuthenticated = Auth::check(); // Cek status setelah login
            Log::info('Auth::login called. Is authenticated: ' . ($isAuthenticated ? 'YES' : 'NO'));

            if ($isAuthenticated) {
                Log::info('Redirecting to intended dashboard...');
                // Pastikan session ditulis sebelum redirect
                session()->regenerate(); // Regenerate session ID setelah login
                return redirect()->intended(route('dashboard'));
            } else {
                Log::error('Auth::login failed after Google callback despite Auth::login call.');
                return redirect()->route('login')->with('error', 'Failed to establish session after Google login.');
            }
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            Log::error('InvalidStateException during Google Callback: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Login session expired or invalid. Please try again.');
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('GuzzleHttp RequestException during Google Callback (Network Issue?): ' . $e->getMessage());
            // Cek jika ada respons dalam exception
            if ($e->hasResponse()) {
                Log::error('GuzzleHttp Response Body: ' . $e->getResponse()->getBody());
            }
            return redirect()->route('login')->with('error', 'Could not communicate with Google. Please check network or configuration.');
        } catch (\Exception $e) {
            Log::error('General Web Google Callback Error: ' . $e->getMessage(), ['trace' => substr($e->getTraceAsString(), 0, 1000)]); // Log lebih banyak trace
            return redirect()->route('login')
                ->with('error', 'Google login failed. Please try again. Details logged.'); // Jangan tampilkan $e->getMessage() ke user
        }
    }
}
