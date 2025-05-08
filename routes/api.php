<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController; // Asumsi Anda punya controller ini
use App\Http\Controllers\Api\RvmController;

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

// Rute Autentikasi User (Contoh)
Route::post('/auth/register', [AuthController::class, 'register'])->name('api.auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('api.auth.google.redirect');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('api.auth.google.callback');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'userProfile'])->name('api.user.profile');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    // Rute lain yang memerlukan otentikasi user (Sanctum)
});

// Rute untuk RVM
Route::prefix('rvm')->name('api.rvm.')->group(function () {
    // Endpoint ini untuk RVM melakukan "login" awal atau cek status jika diperlukan.
    // Tidak menggunakan middleware auth.rvm karena di sinilah RVM akan mengirim API key-nya
    // untuk pertama kali atau untuk validasi sederhana.
    Route::post('/authenticate', [RvmController::class, 'authenticateRvm'])->name('authenticate');

    // Endpoint ini mungkin memerlukan otentikasi user (Sanctum) jika token dikirim
    // atau bisa juga terbuka jika tokennya adalah jenis public short-lived token.
    // Untuk saat ini, biarkan terbuka atau tambahkan middleware yang sesuai nanti.
    Route::post('/validate-user-token', [RvmController::class, 'validateUserToken'])->name('validate_user_token');

    // Endpoint deposit, INI YANG PENTING
    // Akan menggunakan URL: /api/rvm/deposit
    // dan memerlukan otentikasi RVM via middleware auth.rvm
    Route::post('/deposit', [RvmController::class, 'deposit'])
        ->name('deposit') // Nama rute menjadi api.rvm.deposit
        ->middleware('auth.rvm');
});

// HAPUS DEFINISI /deposit YANG ADA DI LUAR GRUP 'rvm'
// Route::post('/deposit', [RvmController::class, 'deposit'])
//     ->name('deposit')
//     ->middleware('auth.rvm');