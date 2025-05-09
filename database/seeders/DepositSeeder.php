<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User; // Gunakan Model untuk kemudahan
use App\Models\ReverseVendingMachine; // Gunakan Model
use Carbon\Carbon;

class DepositSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil ID User yang ada dengan role 'User'
        $regularUserIds = User::where('role', 'User')->pluck('id')->toArray();

        // Ambil ID RVM yang ada
        $rvmIds = ReverseVendingMachine::pluck('id')->toArray();

        if (empty($regularUserIds) || empty($rvmIds)) {
            $this->command->getOutput()->writeln("<comment>Cannot run DepositSeeder: No regular users or RVMs found. Please run UserSeeder and RvmSeeder first.</comment>");
            return;
        }

        $depositsData = [];

        // Daftar contoh tipe deteksi dan label yang sesuai
        $detectionScenarios = [
            ['type' => 'PET_MINERAL_EMPTY', 'label' => 'EMPTY mineral water bottle', 'points' => 10, 'needs_action' => false],
            ['type' => 'ALUMINUM_CAN_EMPTY', 'label' => 'empty soda can', 'points' => 12, 'needs_action' => false],
            ['type' => 'PET_SODA_EMPTY', 'label' => 'empty coke bottle', 'points' => 8, 'needs_action' => false],
            ['type' => 'REJECTED_HAS_CONTENT_OR_TRASH', 'label' => 'filled mineral bottle - water', 'points' => 0, 'needs_action' => true],
            ['type' => 'REJECTED_UNKNOWN_TYPE', 'label' => 'unknown plastic item', 'points' => 0, 'needs_action' => true],
            ['type' => 'PET_BOTTLE', 'label' => 'PET bottle', 'points' => 10, 'needs_action' => false], // Tipe generik
        ];


        for ($i = 0; $i < 20; $i++) { // Buat 20 deposit acak
            $userId = $regularUserIds[array_rand($regularUserIds)];
            $rvmId = $rvmIds[array_rand($rvmIds)];
            
            $scenario = $detectionScenarios[array_rand($detectionScenarios)];
            $detectedType = $scenario['type'];
            $points = $scenario['points'];
            $rawLabel = $scenario['label'];
            $needsAction = $scenario['needs_action'];

            // Tanggal deposit acak dalam 30 hari terakhir
            $depositedAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59))->subSeconds(rand(0,59));

            // Buat contoh bounding box acak sederhana
            $ymin = rand(100, 300);
            $xmin = rand(100, 300);
            $ymax = $ymin + rand(100, 400);
            $xmax = $xmin + rand(100, 400);

            // Struktur gemini_raw_response yang lebih sesuai dengan hasil parse GeminiVisionService
            // Ini adalah array PHP yang akan di-encode menjadi JSON oleh Laravel saat insert.
            $geminiResponseArray = [
                [
                    "box_2d" => [$ymin, $xmin, $ymax, $xmax],
                    "label" => $rawLabel
                ]
                // Jika ingin mensimulasikan beberapa item terdeteksi, tambahkan entri lain ke array ini
            ];

            $depositsData[] = [
                'user_id' => $userId,
                'rvm_id' => $rvmId,
                'detected_type' => $detectedType,
                'points_awarded' => $points,
                'image_path' => 'seeders/sample_deposit_image.jpg', // Contoh path, atau null
                'gemini_raw_label' => $rawLabel, // Label utama yang diekstrak
                'gemini_raw_response' => json_encode($geminiResponseArray), // Simpan sebagai string JSON
                'needs_action' => $needsAction,
                'deposited_at' => $depositedAt,
                'created_at' => $depositedAt, 
                'updated_at' => $depositedAt,
            ];

            // (Opsional) Update poin user secara langsung di seeder jika diperlukan untuk konsistensi data awal
            // Jika tidak, poin user akan 0 kecuali ada proses lain yang menghitungnya dari deposit
            if (!$needsAction && $points > 0) {
                User::find($userId)->increment('points', $points);
            }
        }

        // Gunakan insert chunk untuk efisiensi jika datanya banyak
        foreach (array_chunk($depositsData, 200) as $chunk) {
            DB::table('deposits')->insert($chunk);
        }
        
        $this->command->getOutput()->writeln("<info>DepositSeeder run successfully. " . count($depositsData) . " deposits created.</info>");
    }
}