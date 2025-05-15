<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\DashboardController; // Dashboard
use App\Http\Controllers\Admin\RvmManagementController; // RVM Management
use App\Http\Controllers\Admin\UserManagementController; // Users Management


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'role:Admin,Operator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Rute Manajemen RVM
    Route::get('/rvms', [RvmManagementController::class, 'index'])->name('rvms.index')->middleware('role:Admin');
    Route::get('/rvms/create', [RvmManagementController::class, 'create'])->name('rvms.create')->middleware('role:Admin');
    Route::post('/rvms', [RvmManagementController::class, 'store'])->name('rvms.store')->middleware('role:Admin');
    Route::get('/rvms/{rvm}/edit', [RvmManagementController::class, 'edit'])->name('rvms.edit')->middleware('role:Admin');
    Route::patch('/rvms/{rvm}', [RvmManagementController::class, 'update'])->name('rvms.update')->middleware('role:Admin');
    Route::delete('/rvms/{rvm}', [RvmManagementController::class, 'destroy'])->name('rvms.destroy')->middleware('role:Admin');


    // Rute Manajemen User
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    // ... rute CRUD user lainnya ...
});

require __DIR__.'/auth.php';
