<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReverseVendingMachine;
use Illuminate\Http\Request;
use Inertia\Inertia; // Import Inertia
use App\Models\Deposit;
use App\Models\User;
use App\Models\ConfiguredPrompt;
use Illuminate\Support\Facades\DB; // Import DB facade
use Illuminate\Support\Str; // Untuk Str::random()
use Illuminate\Validation\Rule; // Untuk Rule::in()
use Illuminate\Support\Facades\Log; // Import Log facade
use Illuminate\Support\Facades\Auth; // Import Auth facade

class DashboardController extends Controller
{
    public function index()
    {
        // Anda bisa mengirim data tambahan ke komponen Vue sebagai props
        // return Inertia::render('Admin/Dashboard', [
        //     // 'someData' => ['foo' => 'bar'],
        // ]);

        // Statistik untuk Kartu
        $totalRvms = ReverseVendingMachine::count();
        $activeRvms = ReverseVendingMachine::where('status', 'active')->count();
        $totalUsers = User::count();
        $todayDepositsCount = Deposit::whereDate('deposited_at', today())->count();
        $todayPointsAwarded = (int) Deposit::whereDate('deposited_at', today())->sum('points_awarded'); // Cast ke int

        // Informasi Prompt Aktif
        $activePrompt = ConfiguredPrompt::where('is_active', true)->first(['id','configured_prompt_name', 'version']);

        // Data untuk Grafik (akan kita siapkan nanti, sekarang contoh sederhana)
        // Misalnya, total deposit per hari selama 7 hari terakhir
        $depositsLast7Days = Deposit::select(
                DB::raw('DATE(deposited_at) as date'),
                DB::raw('count(*) as total_deposits'),
                DB::raw('sum(points_awarded) as total_points')
            )
            ->where('deposited_at', '>=', now()->subDays(6)->startOfDay()) // 7 hari termasuk hari ini
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->mapWithKeys(function ($item) { // mapWithKeys agar mudah diakses di JS
                return [$item->date => [
                    'deposits' => $item->total_deposits,
                    'points' => (int) $item->total_points
                    ]];
            });

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalRvms' => $totalRvms,
                'activeRvms' => $activeRvms,
                'totalUsers' => $totalUsers,
                'todayDepositsCount' => $todayDepositsCount,
                'todayPointsAwarded' => $todayPointsAwarded,
            ],
            'activePromptInfo' => $activePrompt ? [
                'name' => $activePrompt->configured_prompt_name,
                'version' => $activePrompt->version,
                'id' => $activePrompt->id, // Kirim ID jika ingin ada link ke edit
            ] : null,
            'depositsChartData' => [ // Data awal untuk chart
                'labels' => $depositsLast7Days->keys()->all(),
                'datasets' => [
                    [
                        'label' => 'Total Deposit per Hari',
                        'backgroundColor' => '#4ade80', // Contoh warna hijau
                        'borderColor' => '#22c55e',
                        'data' => $depositsLast7Days->map(fn($data) => $data['deposits'])->values()->all(),
                        'tension' => 0.1,
                    ],
                    // Anda bisa tambahkan dataset lain, misal untuk poin
                ]
            ]
        ]);
    }
}