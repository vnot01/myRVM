<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
// GANTI BARIS INI:
// use App\Http\Controllers\Auth\GoogleAuthController; 
// DENGAN BARIS INI:
use App\Http\Controllers\Web\GoogleAuthController; // <--- PERBAIKAN PATH

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Rute untuk Google Sign-In Web
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('web.auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('web.auth.google.callback');

// Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');

require __DIR__ . '/auth.php'; // Ini mengimpor rute Breeze (login, register email/pass, dll.)