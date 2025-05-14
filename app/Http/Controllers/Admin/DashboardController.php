<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia; // Import Inertia

class DashboardController extends Controller
{
    public function index()
    {
        // Anda bisa mengirim data tambahan ke komponen Vue sebagai props
        return Inertia::render('Admin/Dashboard', [
            // 'someData' => ['foo' => 'bar'],
        ]);
    }
}