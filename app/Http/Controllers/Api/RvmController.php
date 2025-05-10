<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GeminiVisionService;
use App\Models\User;
use App\Models\Deposit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class RvmController extends Controller
{
    protected GeminiVisionService $geminiService;

    public function __construct(GeminiVisionService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'user_identifier' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $rvm = $request->attributes->get('authenticated_rvm');
        if (!$rvm) {
            Log::error('Authenticated RVM not found in request attributes...');
            return response()->json(['status' => 'error', 'message' => 'RVM authentication processing error.'], 500);
        }

        $userId = null;
        $userInstance = null; // Ganti nama variabel agar lebih jelas ini instance model
        if (is_numeric($request->input('user_identifier'))) {
            $userInstance = User::find($request->input('user_identifier'));
            if ($userInstance) {
                $userId = $userInstance->id;
            } else {
                Log::info('User ID not found for numeric identifier.', ['identifier' => $request->input('user_identifier')]);
            }
        } else {
            Log::info('Processing non-numeric user_identifier...', ['identifier' => $request->input('user_identifier')]);
        }
        Log::info('Deposit - Starting DB Transaction');
        DB::beginTransaction();
        try {
            $imageFile = $request->file('image');
            $imagePath = 'deposits/' . date('Y/m') . '/' . Str::uuid()->toString() . '.' . $imageFile->getClientOriginalExtension();
            $imageFile->storeAs('public', $imagePath);
            Log::info('Deposit - Image stored locally', ['path' => $imagePath]);
            Log::info('Deposit - Calling GeminiVisionService...');
            $geminiResults = $this->geminiService->analyzeImageFromFile($imageFile);
            Log::info('Deposit - GeminiVisionService call completed.', ['results_count' => count($geminiResults ?? [])]);
            $firstResult = $geminiResults[0] ?? null;
            $rawLabel = $firstResult['label'] ?? 'unknown';
            Log::info('Deposit - Raw label from Gemini:', ['label' => $rawLabel]);
            // Penanganan jika tidak ada hasil dari Gemini atau label unknown
            if (empty($geminiResults) || $rawLabel === 'unknown') {
                Log::info('Deposit - No items detected or label is unknown. Rejecting.');
                $depositData = [
                    'user_id' => $userId,
                    'rvm_id' => $rvm->id,
                    'detected_type' => 'REJECTED_UNIDENTIFIED',
                    'points_awarded' => 0,
                    'image_path' => $imagePath,
                    'gemini_raw_label' => $rawLabel === 'unknown' && !empty($geminiResults) ? $rawLabel : 'No items detected',
                    'gemini_raw_response' => $geminiResults,
                    'needs_action' => true,
                    'deposited_at' => now(),
                ];
                $deposit = Deposit::create($depositData);
                DB::commit();
                return response()->json([
                    'status' => 'rejected',
                    'reason' => 'UNIDENTIFIED_ITEM',
                    'message' => 'No identifiable items detected or item type unknown.',
                    'item_type' => 'REJECTED_UNIDENTIFIED',
                    'points_awarded' => 0,
                    'deposit_id' => $deposit->id,
                ]);
            }

            // Logika Interpretasi Label (Sama seperti sebelumnya)
            $detectedType = 'UNKNOWN';
            $pointsAwarded = 0;
            $needsAction = true;
            $lowerLabel = strtolower($rawLabel);
            Log::info('Deposit - Starting label interpretation...');
            // ... (blok if/elseif untuk interpretasi label) ...
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
                $detectedType = 'REJECTED_UNKNOWN_TYPE';
                $pointsAwarded = 0;
                $needsAction = true;
                Log::info('Unmatched Gemini label:', ['label' => $rawLabel, 'rvm_id' => $rvm->id]);
            }
            Log::info('Deposit - Label interpretation result:', ['detected_type' => $detectedType, 'points' => $pointsAwarded, 'needs_action' => $needsAction]);

            Log::info('Deposit - Creating deposit record...');
            $depositData = [
                'user_id' => $userId,
                'rvm_id' => $rvm->id,
                'detected_type' => $detectedType,
                'points_awarded' => $pointsAwarded,
                'image_path' => $imagePath,
                'gemini_raw_label' => $rawLabel,
                'gemini_raw_response' => $geminiResults,
                'needs_action' => $needsAction,
                'deposited_at' => now(),
            ];
            $deposit = Deposit::create($depositData);
            Log::info('Deposit - Deposit record created.', ['deposit_id' => $deposit->id]);
            $currentUserTotalPoints = null; // Inisialisasi
            if ($userInstance && !$needsAction && $pointsAwarded > 0) {
                Log::info('Deposit - Updating user points...');
                $userInstance->points += $pointsAwarded;
                $userInstance->save();
                // $userInstance->refresh(); // Opsional, tapi $userInstance->points sudah update di memori
                $currentUserTotalPoints = $userInstance->points; // Ambil poin setelah diupdate
                Log::info('Deposit - User points updated.', ['user_id' => $userInstance->id, 'new_total' => $currentUserTotalPoints]);
            } elseif ($userInstance) {
                // Jika user ada tapi item ditolak atau tidak dapat poin
                $currentUserTotalPoints = $userInstance->points;
            }

            Log::info('Deposit - Committing transaction...');
            DB::commit();
            Log::info('Deposit - Transaction committed.');
            Log::info('Deposit - Preparing final response...');
            if ($needsAction) {
                return response()->json([
                    'status' => 'rejected',
                    'reason' => $detectedType,
                    'message' => 'Item rejected. Reason: ' . str_replace('_', ' ', $detectedType),
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
                    'user_total_points' => $currentUserTotalPoints, // Gunakan variabel yang sudah pasti
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RVM Deposit Error: ' . $e->getMessage(), [
                'trace' => substr($e->getTraceAsString(), 0, 2000),
                'rvm_id' => $rvm->id ?? 'RVM NA',
                'user_identifier' => $request->input('user_identifier'),
            ]);
            return response()->json(['status' => 'error', 'message' => 'An internal server error occurred during deposit processing.'], 500);
        }
    }

    // Metode authenticateRvm dan validateUserToken tetap sama
    public function authenticateRvm(Request $request)
    {
        // ... (kode sama)
        $rvmValidInstance = \App\Models\ReverseVendingMachine::where('api_key', $request->input('api_key'))->first();
        if ($rvmValidInstance && $rvmValidInstance->status === 'active') { // ganti nama var
            return response()->json(['status' => 'success', 'message' => 'RVM authenticated.', 'rvm_id' => $rvmValidInstance->id]);
        }
        return response()->json(['status' => 'error', 'message' => 'Invalid RVM API Key or RVM not active.'], 401);
    }

    public function validateUserToken(Request $request)
    {
        // ... (kode sama, pastikan User::find() di dalamnya)
        $validator = Validator::make($request->all(), [
            'user_token' => 'required|string|size:40'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'User token is required/invalid format.', 'errors' => $validator->errors()], 422);
        }
        $rvmLoginToken = $request->input('user_token');
        $cacheKey = 'rvm_login_token:' . $rvmLoginToken;
        Log::info('RVM_VALIDATE_TOKEN: Received token from RPi', ['token' => $rvmLoginToken, 'cache_key' => $cacheKey]);
        Log::info('RVM_VALIDATE_TOKEN: Current CACHE_DRIVER', ['driver' => config('cache.default')]);
        if (Cache::has($cacheKey)) {
            $userIdFromCache = Cache::get($cacheKey); // ganti nama var
            $userFromCache = User::find($userIdFromCache); // ganti nama var
            Log::info('RVM_VALIDATE_TOKEN: Token found in cache.', ['user_id_from_cache' => $userIdFromCache]);
            $user = \App\Models\User::find($userIdFromCache);
            if ($user) {
                Cache::forget($cacheKey);
                Log::info('RVM_VALIDATE_TOKEN: cacheKey.', ['user_id' => $userFromCache->id]);
                Log::info('RVM_VALIDATE_TOKEN: Token validated. User found.', ['user_id' => $user->id, 'token_would_be_forgotten' => $rvmLoginToken]);
                return response()->json([
                    'status' => 'success',
                    'message' => 'User token validated.',
                    'data' => ['user_id' => $userFromCache->id, 'user_name' => $userFromCache->name]
                ]);
            } else {
                Log::warning('RVM_VALIDATE_TOKEN: User ID not found in DB.', ['token' => $rvmLoginToken]);
                Log::warning('RVM_VALIDATE_TOKEN: User ID from cache.', ['cached_user_id' => $userIdFromCache, 'token' => $rvmLoginToken]);
                Cache::forget($cacheKey);
                return response()->json(['status' => 'error', 'message' => 'User for token not found.'], 404);
            }
        }
        Log::warning('RVM_VALIDATE_TOKEN: Token not found in cache or expired.', ['token' => $rvmLoginToken]);
        return response()->json(['status' => 'error', 'message' => 'Invalid or expired user token.'], 401);
    }
}
