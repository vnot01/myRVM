<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ReverseVendingMachine;
use App\Models\Deposit;
use Illuminate\Support\Facades\DB; // Untuk sum
use Carbon\Carbon; // Untuk filter tanggal
use App\Services\GeminiVisionService; // <-- IMPORT
use Illuminate\Support\Facades\Validator; // <-- IMPORT
use Illuminate\Support\Facades\Log; // <-- IMPORT

class AdminDataController extends Controller
{
    protected GeminiVisionService $geminiService;

    // Inject service via constructor
    public function __construct(GeminiVisionService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Mengambil statistik dasar untuk dashboard admin.
     */
    public function getStats(Request $request)
    {
        // User yang request sudah divalidasi role-nya oleh middleware

        $totalUsers = User::count();
        $totalRvms = ReverseVendingMachine::count();
        $activeRvms = ReverseVendingMachine::where('status', 'active')->count();

        $todayDepositsCount = Deposit::whereDate('deposited_at', Carbon::today())->count();
        $totalDepositsCount = Deposit::count();

        // Hitung total poin yang sudah berhasil diberikan (item tidak di-reject)
        $totalPointsAwarded = Deposit::where('needs_action', false) // Hanya yang diterima
                                    ->sum('points_awarded');

        return response()->json([
            'status' => 'success',
            'message' => 'Basic statistics retrieved successfully.',
            'data' => [
                'total_users' => $totalUsers,
                'total_rvms' => $totalRvms,
                'active_rvms' => $activeRvms,
                'total_deposits' => $totalDepositsCount,
                'today_deposits' => $todayDepositsCount,
                'total_points_awarded' => (int) $totalPointsAwarded, // Cast ke integer
            ]
        ]);
    }

    /**
     * Endpoint untuk admin menguji Gemini Vision API dengan gambar.
     */
    public function testVision(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // Izinkan webp juga
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $imageFile = $request->file('image');

            // Panggil service Gemini Vision
            $analysisResults = $this->geminiService->analyzeImageFromFile($imageFile);

            // Di sini kita kembalikan hasil mentah dari Gemini (array yang sudah diparsing)
            // Frontend admin bisa menampilkan ini sesuai kebutuhan.
            return response()->json([
                'status' => 'success',
                'message' => 'Image analyzed successfully by Gemini.',
                'data' => [
                    'gemini_analysis' => $analysisResults,
                    // Anda bisa tambahkan interpretasi sederhana di sini jika mau,
                    // tapi mungkin lebih baik menampilkan hasil mentah untuk testing admin.
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Vision Test Error: ' . $e->getMessage(), [
                'trace' => substr($e->getTraceAsString(), 0, 500),
                'user_id' => Auth::id() // Log user admin yang mencoba
            ]);
            return response()->json(['status' => 'error', 'message' => 'Failed to analyze image: ' . $e->getMessage()], 500);
        }
    }
}