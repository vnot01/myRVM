<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

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
