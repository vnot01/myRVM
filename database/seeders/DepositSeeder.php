<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Untuk manipulasi tanggal

class DepositSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil ID User yang ada (kecuali admin/operator jika tidak ingin mereka punya deposit)
        // Kita asumsikan user ID 3, 4, 5, 6, 7, 8 adalah regular users dari UserSeeder
        $regularUserIds = DB::table('users')->whereIn('role', ['User'])->pluck('id')->toArray();

        // Ambil ID RVM yang ada
        $rvmIds = DB::table('reverse_vending_machines')->pluck('id')->toArray();

        if (empty($regularUserIds) || empty($rvmIds)) {
            $this->command->info('Cannot run DepositSeeder: No regular users or RVMs found. Please run UserSeeder and ReverseVendingMachineSeeder first.');
            return;
        }

        $depositsData = [];
        $detectedTypes = ['PET_BOTTLE', 'ALUMINUM_CAN', 'OTHER_PLASTIC', 'REJECTED_HAS_CONTENT'];
        $pointsMapping = [
            'PET_BOTTLE' => 10,
            'ALUMINUM_CAN' => 15,
            'OTHER_PLASTIC' => 5,
            'REJECTED_HAS_CONTENT' => 0
        ];
        $rawLabels = [
            'PET_BOTTLE' => 'empty mineral bottle',
            'ALUMINUM_CAN' => 'empty soda can',
            'OTHER_PLASTIC' => 'empty shampoo bottle',
            'REJECTED_HAS_CONTENT' => 'filled mineral bottle - water'
        ];


        for ($i = 0; $i < 15; $i++) { // Buat 15 deposit acak
            $userId = $regularUserIds[array_rand($regularUserIds)];
            $rvmId = $rvmIds[array_rand($rvmIds)];
            $detectedType = $detectedTypes[array_rand($detectedTypes)];
            $points = $pointsMapping[$detectedType];
            $rawLabel = $rawLabels[$detectedType];
            $needsAction = ($detectedType === 'REJECTED_HAS_CONTENT');
            // Tanggal deposit acak dalam 30 hari terakhir
            $depositedAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            $depositsData[] = [
                'user_id' => $userId,
                'rvm_id' => $rvmId,
                'detected_type' => $detectedType,
                'points_awarded' => $points,
                'image_path' => null, // Bisa diisi jika ada contoh gambar
                'gemini_raw_label' => $rawLabel,
                'gemini_raw_response' => json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode([['label' => $rawLabel, 'box_2d' => [100, 100, 200, 200]]])]]]]]]), // Contoh respons Gemini sederhana
                'needs_action' => $needsAction,
                'deposited_at' => $depositedAt,
                'created_at' => $depositedAt, // Samakan dengan deposited_at untuk data awal
                'updated_at' => $depositedAt,
            ];
        }

        DB::table('deposits')->insert($depositsData);
    }
}
