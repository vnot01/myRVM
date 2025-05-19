<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguredPrompt;
use App\Models\Deposit;
use App\Models\ReverseVendingMachine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // Import Str helper
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // Fungsi helper untuk memformat label
    private function formatLabel($label)
    {
        if (empty($label)) {
            return 'Tidak Diketahui';
        }
        return Str::title(str_replace('_', ' ', strtolower($label)));
    }

    public function index()
    {
        // Statistik RVM
        $totalRvms = ReverseVendingMachine::count();
        $activeRvms = ReverseVendingMachine::where('status', 'active')->count();
        $inactiveRvms = ReverseVendingMachine::where('status', 'inactive')->count();
        $maintenanceRvms = ReverseVendingMachine::where('status', 'maintenance')->count();
        $fullRvms = ReverseVendingMachine::where('status', 'full')->count();

        // Statistik User
        $totalUsers = User::count();

        // Statistik Deposit
        $todayDepositsCount = Deposit::whereDate('deposited_at', today())->count();
        $todayPointsAwarded = (int) Deposit::whereDate('deposited_at', today())->sum('points_awarded');

        $weekDepositsCount = Deposit::whereBetween('deposited_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $weekPointsAwarded = (int) Deposit::whereBetween('deposited_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('points_awarded');

        $monthDepositsCount = Deposit::whereMonth('deposited_at', now()->month)->whereYear('deposited_at', now()->year)->count();
        $monthPointsAwarded = (int) Deposit::whereMonth('deposited_at', now()->month)->whereYear('deposited_at', now()->year)->sum('points_awarded');

        // Informasi Prompt Aktif
        $activePrompt = ConfiguredPrompt::where('is_active', true)
                            ->first(['id', 'configured_prompt_name', 'version', 'estimated_confidence_score']);

        // Data untuk Grafik Garis (Line Chart)
        $dailyActivity = Deposit::select(
                DB::raw('DATE(deposited_at) as date'),
                DB::raw('count(*) as total_deposits'),
                DB::raw('sum(points_awarded) as total_points')
            )
            ->where('deposited_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $labels = $dailyActivity->pluck('date')->map(function ($date) {
            return Carbon::parse($date)->isoFormat('dd, D MMM');
        })->all();

        $depositCounts = $dailyActivity->pluck('total_deposits')->all();
        $pointSums = $dailyActivity->pluck('total_points')->map(fn($val) => (int)$val)->all();

        // Data untuk Diagram Lingkaran (Pie Chart) Distribusi Jenis Item
        $itemDistribution = Deposit::select('detected_type', DB::raw('count(*) as total')) // <-- DIUBAH KE detected_type
            ->whereNotNull('detected_type')    // <-- DIUBAH KE detected_type
            ->where('detected_type', '!=', '') // <-- DIUBAH KE detected_type
            ->groupBy('detected_type')         // <-- DIUBAH KE detected_type
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        $pieChartLabels = $itemDistribution->pluck('detected_type')->map(function ($type) { // <-- DIUBAH KE detected_type
            return $this->formatLabel($type);
        })->all();

        $pieChartData = $itemDistribution->pluck('total')->all();

        $pieChartColors = [
            'rgba(255, 99, 132, 0.7)',
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)',
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalRvms' => $totalRvms,
                'activeRvms' => $activeRvms,
                'inactiveRvms' => $inactiveRvms,
                'maintenanceRvms' => $maintenanceRvms,
                'fullRvms' => $fullRvms,
                'totalUsers' => $totalUsers,
                'todayDepositsCount' => $todayDepositsCount,
                'todayPointsAwarded' => $todayPointsAwarded,
                'weekDepositsCount' => $weekDepositsCount,
                'weekPointsAwarded' => $weekPointsAwarded,
                'monthDepositsCount' => $monthDepositsCount,
                'monthPointsAwarded' => $monthPointsAwarded,
            ],
            'activePromptInfo' => $activePrompt ? [
                'name' => $activePrompt->configured_prompt_name,
                'version' => $activePrompt->version,
                'score' => $activePrompt->estimated_confidence_score,
                'id' => $activePrompt->id,
            ] : null,
            'depositsChartData' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Total Deposit per Hari',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'borderColor' => 'rgb(75, 192, 192)',
                        'borderWidth' => 2,
                        'data' => $depositCounts,
                        'tension' => 0.2,
                        'fill' => true,
                    ],
                    [
                        'label' => 'Total Poin per Hari',
                        'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                        'borderColor' => 'rgb(255, 159, 64)',
                        'borderWidth' => 2,
                        'data' => $pointSums,
                        'tension' => 0.2,
                        'fill' => true,
                    ]
                ]
            ],
            'itemDistributionChartData' => [
                'labels' => $pieChartLabels,
                'datasets' => [
                    [
                        'label' => 'Distribusi Item',
                        'backgroundColor' => array_slice($pieChartColors, 0, count($pieChartLabels)),
                        'borderColor' => '#fff',
                        'borderWidth' => 1,
                        'data' => $pieChartData,
                    ]
                ]
            ]
        ]);
    }
}