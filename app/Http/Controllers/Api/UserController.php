<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Models\Deposit; // Pastikan model Deposit di-import
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Mendapatkan riwayat deposit user yang sedang login.
     * User di-resolve melalui middleware auth:sanctum.
     */
    public function depositHistory(Request $request)
    {
        $user = $request->user(); // User yang terotentikasi via Sanctum

        $deposits = Deposit::where('user_id', $user->id)
            ->with('reverseVendingMachine:id,name,location_description') // Hanya ambil kolom yg relevan dari RVM
            ->orderBy('deposited_at', 'desc')
            ->paginate(15); // Menggunakan paginasi

        return response()->json([
            'status' => 'success',
            'message' => 'Deposit history retrieved successfully.',
            'data' => $deposits // Data ada di dalam 'data' key karena paginasi
        ]);
    }

    /**
     * Men-generate token sementara untuk login ke RVM.
     * Token ini akan ditampilkan sebagai QR Code di Aplikasi User.
     * RVM akan mengirim token ini ke endpoint /api/rvm/validate-user-token.
     * User di-resolve melalui middleware auth:sanctum.
     */
    public function generateRvmLoginToken(Request $request)
    {
        $user = $request->user(); // User yang terotentikasi via Sanctum
        $token = Str::random(40); // Buat token acak yang cukup panjang
        $expiresInMinutes = 5;    // Token berlaku selama 5 menit
        $cacheKey = 'rvm_login_token:' . $token;
        Log::info('USER_GENERATE_TOKEN: Attempting to cache token.', [
            'user_id' => $user->id,
            'token' => $token,
            'cache_key' => $cacheKey,
            'expires_in_minutes' => $expiresInMinutes
        ]);
        // Simpan token di cache dengan user ID terkait
        // Key cache dibuat unik dengan prefix dan token itu sendiri
        // Cache::put('rvm_login_token:' . $token, $user->id, now()->addMinutes($expiresInMinutes));
        try {
            Cache::put($cacheKey, $user->id, now()->addMinutes($expiresInMinutes));
            // Verifikasi langsung setelah put
            if (Cache::has($cacheKey)) {
                Log::info('USER_GENERATE_TOKEN: Token successfully cached.', ['cache_key' => $cacheKey, 'cached_user_id' => Cache::get($cacheKey)]);
            } else {
                Log::error('USER_GENERATE_TOKEN: FAILED TO CACHE TOKEN immediately after put!', ['cache_key' => $cacheKey]);
            }
        } catch (\Exception $e) {
            Log::error('USER_GENERATE_TOKEN: Exception during Cache::put()', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to generate RVM token due to cache error.'], 500);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'RVM login token generated successfully. Scan at RVM within ' . $expiresInMinutes . ' minutes.',
            'data' => [
                'rvm_login_token' => $token,
                'expires_in_seconds' => $expiresInMinutes * 60,
            ]
        ]);
    }
}
