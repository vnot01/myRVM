<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\DashboardController; // Dashboard
use App\Http\Controllers\Admin\RvmManagementController; // RVM Management
use App\Http\Controllers\Admin\UserManagementController; // Users Management
use App\Http\Controllers\Admin\PromptTemplateController; // My VisionPrompt
use App\Http\Controllers\Admin\ConfiguredPromptController; // Configurasi Prompt
use App\Http\Controllers\Admin\PromptComponentController; // Komponen Prompt


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
// http://localhost:8000/web/dashboard
Route::middleware(['auth', 'verified', 'role:Admin,Operator'])
    ->prefix('web')
    ->name('admin.')
    ->group(function () {
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
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create')->middleware('role:Admin');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store')->middleware('role:Admin');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit')->middleware('role:Admin');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update')->middleware('role:Admin');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy')->middleware('role:Admin');

        
        // --- RUTE BARU UNTUK PROMPT TEMPLATE MANAGEMENT ---
        Route::resource('/configured-prompts', ConfiguredPromptController::class)->except(['show']);
        Route::post('/configured-prompts/{configuredPrompt}/activate', [ConfiguredPromptController::class, 'activate'])->name('configured-prompts.activate'); // Perlu metode activate
        Route::post('/configured-prompts/test-prompt', [ConfiguredPromptController::class, 'testPrompt'])->name('configured-prompts.test'); // Perlu metode testPrompt
        
        Route::resource('/prompt-components-manage', PromptComponentController::class)
            ->except(['show'])
            ->parameters(['prompt-components-manage' => 'promptComponent']) // Gunakan camelCase di sini
            ->names('prompt-components')
            ->middleware('role:Admin');
        Route::resource('/prompt-templates-manage', PromptTemplateController::class)
            ->except(['show'])
            ->parameters(['prompt-templates-manage' => 'promptTemplate']) // Gunakan camelCase di sini
            ->names('prompt-templates')
            ->middleware('role:Admin');
        // --- AKHIR RUTE BARU ---
        // ... rute CRUD user lainnya ...
    });



require __DIR__ . '/auth.php';
