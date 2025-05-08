<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GeminiVisionService;
use App\Models\User;
use App\Models\ReverseVendingMachine;
use App\Models\Deposit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Untuk transaksi database
use Illuminate\Support\Str; // Jika perlu untuk image path

class RvmController extends Controller
{
    protected GeminiVisionService $geminiService;

    public function __construct(GeminiVisionService $geminiService)
    {
        $this->geminiService = $geminiService;
        // Middleware untuk otentikasi RVM bisa diterapkan di sini atau di rute
        // $this->middleware('auth.rvm')->only('deposit');
    }

    /**
     * Menerima deposit dari RVM.
     */
    public function deposit(Request $request)
    {
        // Validasi input awal
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // Max 5MB
            'user_identifier' => 'required|string', // Bisa user_id atau guest_session_id
            // 'rvm_api_key' => 'required|string', // rvm_api_key tidak perlu divalidasi di sini lagi jika via middleware & header
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $rvm = $request->attributes->get('authenticated_rvm');
        if (!$rvm) {
            // Ini seharusnya tidak terjadi jika middleware berjalan dengan benar
            return response()->json(['status' => 'error', 'message' => 'RVM authentication failed unexpectedly.'], 500);
        }

        // // 1. Otentikasi RVM berdasarkan rvm_api_key
        // $rvm = ReverseVendingMachine::where('api_key', $request->input('rvm_api_key'))->first();
        // if (!$rvm) {
        //     return response()->json(['status' => 'error', 'message' => 'Invalid RVM API Key.'], 401);
        // }
        // if ($rvm->status !== 'active') {
        //     return response()->json(['status' => 'error', 'message' => 'RVM is not active. Current status: ' . $rvm->status], 403);
        // }

        // 2. Identifikasi User (Contoh sederhana, perlu disesuaikan dengan mekanisme token Anda)
        // Untuk saat ini, kita asumsikan 'user_identifier' adalah user_id yang sudah valid
        // atau sebuah penanda guest. Jika guest, user_id di deposit bisa null.
        $userId = null;
        $user = User::find($request->input('user_identifier')); // Jika identifier adalah ID user
        if ($user) {
            $userId = $user->id;
        } else {
            // Logika untuk guest user atau jika identifier adalah token yang perlu divalidasi
            // Jika user_identifier adalah token, validasi di sini
            // Untuk saat ini, jika tidak ditemukan sebagai ID, kita anggap guest atau invalid
            Log::info('Deposit attempt with non-user ID identifier or guest.', ['identifier' => $request->input('user_identifier')]);
            // Jika sistem tidak mengizinkan deposit tanpa user yang dikenali, return error
            // return response()->json(['status' => 'error', 'message' => 'Invalid user identifier.'], 401);
        }


        DB::beginTransaction();
        try {
            $imageFile = $request->file('image');

            // (Opsional) Simpan gambar asli jika diperlukan untuk audit
            // $imagePath = $imageFile->store('deposits/'.date('Y/m'), 'public');
            $imagePath = 'deposits/' . date('Y/m') . '/' . Str::uuid()->toString() . '.' . $imageFile->getClientOriginalExtension();
            $imageFile->storeAs('public', $imagePath);


            // 3. Panggil GeminiVisionService
            $geminiResults = $this->geminiService->analyzeImageFromFile($imageFile);

            if (empty($geminiResults)) {
                // Tidak ada item terdeteksi oleh Gemini atau error parsing yang menghasilkan array kosong
                // Kembalikan sebagai "unknown" atau "rejected"
                $deposit = Deposit::create([
                    'user_id' => $userId,
                    'rvm_id' => $rvm->id,
                    'detected_type' => 'REJECTED_UNIDENTIFIED',
                    'points_awarded' => 0,
                    'image_path' => $imagePath,
                    'gemini_raw_label' => 'No items detected by vision service',
                    'gemini_raw_response' => $geminiResults, // Akan jadi array kosong
                    'needs_action' => true, // User harus ambil kembali
                    'deposited_at' => now(),
                ]);
                DB::commit();
                return response()->json([
                    'status' => 'rejected',
                    'reason' => 'UNIDENTIFIED_ITEM',
                    'message' => 'No identifiable items detected. Please take your item back.',
                    'item_type' => 'REJECTED_UNIDENTIFIED',
                    'points_awarded' => 0,
                    'deposit_id' => $deposit->id,
                ]);
            }

            // 4. Interpretasi Hasil Gemini (Logika ini perlu disempurnakan)
            // Asumsi $geminiResults adalah array, dan kita proses item pertama yang paling relevan
            // atau jika ada beberapa, kita ambil yang paling dominan/jelas.
            // Untuk RVM, biasanya kita harapkan satu item per deposit.
            $firstResult = $geminiResults[0]; // Ambil deteksi pertama
            $rawLabel = $firstResult['label'] ?? 'unknown';

            // --- Mulai Logika Interpretasi Label ---
            $detectedType = 'UNKNOWN';
            $pointsAwarded = 0;
            $needsAction = true; // Default true, kecuali item valid diterima

            // Contoh sederhana logika interpretasi (PERLU DIKEMBANGKAN SECARA ROBUST)
            // Anda mungkin perlu menggunakan regex atau pencocokan string yang lebih canggih
            $lowerLabel = strtolower($rawLabel);

            if (str_contains($lowerLabel, 'empty') && (str_contains($lowerLabel, 'mineral bottle') || str_contains($lowerLabel, 'water bottle'))) {
                $detectedType = 'PET_MINERAL_EMPTY';
                $pointsAwarded = 10;
                $needsAction = false;
            } elseif (str_contains($lowerLabel, 'empty') && (str_contains($lowerLabel, 'soda bottle') || str_contains($lowerLabel, 'coke bottle'))) {
                $detectedType = 'PET_SODA_EMPTY';
                $pointsAwarded = 8;
                $needsAction = false;
            } elseif (str_contains($lowerLabel, 'empty') && str_contains($lowerLabel, 'can')) {
                $detectedType = 'ALUMINUM_CAN_EMPTY';
                $pointsAwarded = 12;
                $needsAction = false;
            } elseif (str_contains($lowerLabel, 'filled') || str_contains($lowerLabel, 'content') || str_contains($lowerLabel, 'trash') || str_contains($lowerLabel, 'cigarette')) {
                $detectedType = 'REJECTED_HAS_CONTENT_OR_TRASH';
                $pointsAwarded = 0;
                $needsAction = true;
            } else {
                // Jika tidak cocok dengan kriteria di atas
                $detectedType = 'REJECTED_UNKNOWN_TYPE';
                $pointsAwarded = 0;
                $needsAction = true;
                Log::info('Unmatched Gemini label:', ['label' => $rawLabel]);
            }
            // --- Akhir Logika Interpretasi Label ---

            // 5. Simpan ke Database
            $deposit = Deposit::create([
                'user_id' => $userId,
                'rvm_id' => $rvm->id,
                'detected_type' => $detectedType,
                'points_awarded' => $pointsAwarded,
                'image_path' => $imagePath,
                'gemini_raw_label' => $rawLabel,
                'gemini_raw_response' => $geminiResults, // Simpan semua hasil jika ada >1
                'needs_action' => $needsAction,
                'deposited_at' => now(),
            ]);

            // 6. Update Poin User jika user teridentifikasi dan item diterima
            if ($user && !$needsAction && $pointsAwarded > 0) {
                $user->points += $pointsAwarded;
                $user->save();
            }

            DB::commit();

            // 7. Kembalikan Respons ke RVM
            if ($needsAction) {
                return response()->json([
                    'status' => 'rejected',
                    'reason' => $detectedType, // e.g., REJECTED_HAS_CONTENT
                    'message' => 'Item rejected. Please take your item back. Reason: ' . str_replace('_', ' ', $detectedType),
                    'item_type' => $detectedType,
                    'points_awarded' => 0,
                    'deposit_id' => $deposit->id,
                ]);
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Item accepted!',
                    'item_type' => $detectedType,
                    'points_awarded' => $pointsAwarded,
                    'deposit_id' => $deposit->id,
                    'user_total_points' => $user ? $user->points : null,
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RVM Deposit Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except('image') // Jangan log data file gambar
            ]);
            return response()->json(['status' => 'error', 'message' => 'An internal error occurred during deposit: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Placeholder untuk otentikasi RVM.
     * RVM mengirimkan API key-nya, endpoint ini bisa digunakan untuk validasi awal
     * atau untuk mendapatkan session token jika menggunakan pendekatan stateful (kurang ideal untuk API).
     * Untuk stateless API, setiap request dari RVM harus menyertakan API key.
     */
    public function authenticateRvm(Request $request)
    {
        $validator = Validator::make($request->all(), ['api_key' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'API key is required.'], 422);
        }

        $rvm = ReverseVendingMachine::where('api_key', $request->input('api_key'))->first();
        if ($rvm && $rvm->status === 'active') {
            // Di sini Anda bisa generate token Sanctum khusus untuk RVM ini jika mau
            // $token = $rvm->createToken('rvm-token-' . $rvm->id)->plainTextToken;
            return response()->json(['status' => 'success', 'message' => 'RVM authenticated.', 'rvm_id' => $rvm->id /*, 'token' => $token */]);
        }
        return response()->json(['status' => 'error', 'message' => 'Invalid RVM API Key or RVM not active.'], 401);
    }

    /**
     * Placeholder untuk validasi user token dari QR Code.
     * RVM mengirimkan token yang di-scan, endpoint ini memvalidasinya.
     */
    public function validateUserToken(Request $request)
    {
        // Implementasi validasi token user yang di-generate oleh Aplikasi User
        // Contoh: token JWT singkat atau UUID yang disimpan sementara dengan user_id
        $validator = Validator::make($request->all(), ['user_token' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'User token is required.'], 422);
        }

        $userToken = $request->input('user_token');
        // Logika validasi token (misalnya, cek di cache atau tabel temporary tokens)
        // $userId = $this->findUserIdByToken($userToken);

        // Placeholder:
        $user = User::find($userToken); // Ini BUKAN implementasi yang aman, hanya contoh jika token adalah user_id
        if ($user) {
            return response()->json(['status' => 'success', 'message' => 'User token validated.', 'user_id' => $user->id, 'user_name' => $user->name]);
        }
        return response()->json(['status' => 'error', 'message' => 'Invalid or expired user token.'], 401);
    }
}
