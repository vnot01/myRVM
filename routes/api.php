<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RvmController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'userProfile']); // Endpoint untuk mengambil info user yang sedang login
    // Rute lain yang memerlukan otentikasi akan ada di sini
});

// Rute untuk Google OAuth
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('google.redirect'); // Beri nama rute jika perlu
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');
Route::post('/deposit', [RvmController::class, 'deposit'])
    ->name('deposit')
    ->middleware('auth.rvm');

Route::prefix('rvm')->name('rvm.')->group(function () {
    // Endpoint ini mungkin tidak memerlukan middleware auth.rvm jika api_key dikirim di body
    // dan divalidasi di dalam controller seperti contoh di atas.
    // Jika api_key dikirim di header dan divalidasi middleware, maka hapus validasi api_key di controller.
    Route::post('/authenticate', [RvmController::class, 'authenticateRvm'])->name('authenticate');
    Route::post('/validate-user-token', [RvmController::class, 'validateUserToken'])->name('validate_user_token');

    // Endpoint deposit:
    // Jika menggunakan middleware 'auth.rvm' dan API key di header:
    // Route::post('/deposit', [RvmController::class, 'deposit'])->name('deposit')->middleware('auth.rvm');
    // Jika API key dikirim di body dan divalidasi di controller (seperti contoh controller saat ini):
    Route::post('/deposit', [RvmController::class, 'deposit'])->name('deposit');
});
