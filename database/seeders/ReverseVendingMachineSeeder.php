<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReverseVendingMachineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rvmsData = [
            [
                'name' => 'RVM Kantin Pusat Gedung A',
                'location_description' => 'Sebelah pintu masuk utama Kantin Pusat, Gedung A, Lantai 1',
                'latitude' => -6.2000000, // Contoh Latitude Jakarta
                'longitude' => 106.8166644, // Contoh Longitude Jakarta
                'status' => 'active',
                'api_key' => 'RVM001-' . Str::random(32) // Generate unique API key
            ],
            [
                'name' => 'RVM Perpustakaan Universitas Sayap Barat',
                'location_description' => 'Lobi Perpustakaan, dekat meja informasi',
                'latitude' => -6.2012345,
                'longitude' => 106.8178901,
                'status' => 'active',
                'api_key' => 'RVM002-' . Str::random(32)
            ],
            [
                'name' => 'RVM Asrama Mahasiswa Blok C',
                'location_description' => 'Area umum Asrama Mahasiswa Blok C, dekat vending machine minuman',
                'latitude' => -6.1987654,
                'longitude' => 106.8154321,
                'status' => 'maintenance',
                'api_key' => 'RVM003-' . Str::random(32)
            ],
            [
                'name' => 'RVM Fakultas Teknik Ruang Terbuka',
                'location_description' => 'Plaza Fakultas Teknik, samping taman',
                'latitude' => -6.2023456,
                'longitude' => 106.8190123,
                'status' => 'inactive',
                'api_key' => 'RVM004-' . Str::random(32)
            ],
            [
                'name' => 'RVM Stasiun Kereta Kota Pintu Utara',
                'location_description' => 'Dekat loket tiket Pintu Utara Stasiun Kereta Kota',
                'latitude' => -6.1352000, // Contoh Latitude Jakarta Kota
                'longitude' => 106.8133000, // Contoh Longitude Jakarta Kota
                'status' => 'full',
                'api_key' => 'RVM005-' . Str::random(32)
            ],
        ];

        foreach ($rvmsData as $rvmData) {
            DB::table('reverse_vending_machines')->insert([
                'name' => $rvmData['name'],
                'location_description' => $rvmData['location_description'],
                'latitude' => $rvmData['latitude'],
                'longitude' => $rvmData['longitude'],
                'status' => $rvmData['status'],
                'api_key' => $rvmData['api_key'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
