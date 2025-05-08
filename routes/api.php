<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\RvmController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// --- Grup Rute Autentikasi API ---
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('/register', [ApiAuthController::class, 'register'])->name('register');
    Route::post('/login', [ApiAuthController::class, 'login'])->name('login');

    // Endpoint untuk frontend Mobile/SPA mengirim ID Token Google
    // Frontend mendapatkan ID Token dari Google SDK, lalu mengirimkannya ke endpoint ini.
    Route::post('/google/token-signin', [ApiAuthController::class, 'signInWithGoogleIdToken'])->name('google.token_signin');

    // Rute yang memerlukan user terotentikasi via Sanctum token
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [ApiAuthController::class, 'logout'])->name('logout');
        Route::get('/user', [ApiAuthController::class, 'userProfile'])->name('user.profile');
    });
});

// --- Grup Rute untuk Fitur User yang Sudah Terotentikasi API ---
Route::middleware('auth:sanctum')->prefix('user')->name('api.user.')->group(function () {
    Route::get('/deposit-history', [UserController::class, 'depositHistory'])->name('deposit_history');
    Route::post('/generate-rvm-token', [UserController::class, 'generateRvmLoginToken'])->name('generate_rvm_token');
    // Tambahkan rute lain terkait user di sini jika ada, misalnya update profil, dll.
});
// --- Grup Rute untuk Fitur RVM yang Sudah Terotentikasi API ---

// --- Grup Rute untuk Interaksi dengan RVM ---
Route::prefix('rvm')->name('api.rvm.')->group(function () {
    // Endpoint ini untuk RVM melakukan "login" awal atau cek status.
    // Tidak menggunakan middleware auth.rvm karena di sinilah RVM akan mengirim API key-nya.
    Route::post('/authenticate', [RvmController::class, 'authenticateRvm'])->name('authenticate');

    // Endpoint ini dipanggil oleh RVM setelah men-scan QR code dari Aplikasi User.
    // Tidak memerlukan middleware auth.rvm karena tokennya dari user, bukan API key RVM.
    // Namun, endpoint ini sendiri tidak memerlukan 'auth:sanctum' karena dipanggil oleh RVM.
    Route::post('/validate-user-token', [RvmController::class, 'validateUserToken'])->name('validate_user_token');

    // Endpoint utama untuk RVM mengirim data deposit.
    // Dilindungi oleh middleware 'auth.rvm' yang memvalidasi X-RVM-ApiKey dari header.
    Route::post('/deposit', [RvmController::class, 'deposit'])
        ->name('deposit')
        ->middleware('auth.rvm'); // Pastikan alias 'auth.rvm' terdaftar
});
// --- Akhir Grup Rute untuk Interaksi dengan RVM ---