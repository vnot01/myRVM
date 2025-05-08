<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth; // Untuk mendapatkan user yang login

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Memeriksa apakah user yang terotentikasi memiliki salah satu role yang diizinkan.
     * Middleware ini harus dijalankan SETELAH middleware otentikasi (seperti auth:sanctum).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string ...$roles Role yang diizinkan (dipisahkan koma jika lebih dari satu). Contoh: 'Admin' atau 'Admin,Operator'
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Pastikan user sudah terotentikasi sebelum memeriksa role
        if (!Auth::check()) {
             // Seharusnya tidak terjadi jika 'auth:sanctum' sudah dijalankan sebelumnya
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();

        // Periksa apakah role user ada dalam daftar role yang diizinkan
        foreach ($roles as $role) {
            // Bandingkan dengan case-insensitive untuk fleksibilitas, atau sesuaikan jika perlu case-sensitive
            if (strcasecmp($user->role, $role) === 0) {
                // Jika salah satu role cocok, izinkan request melanjutkan
                return $next($request);
            }
        }

        // Jika tidak ada role yang cocok setelah loop selesai
        return response()->json(['status' => 'error', 'message' => 'Forbidden. You do not have the required role(s).'], 403);
    }
}