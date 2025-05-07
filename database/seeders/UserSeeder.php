<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // User Admin
        DB::table('users')->insert([
            'name' => 'Admin RVM',
            'email' => 'admin@rvmsystem.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // Ganti dengan password yang aman
            'google_id' => null,
            'avatar' => null,
            'phone_number' => '081200000001',
            'citizenship' => 'WNI',
            'identity_type' => 'KTP',
            'identity_number' => '3171000000000001', // Pastikan unik
            'points' => 0,
            'role' => 'Admin',
            'is_guest' => false,
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // User Operator
        DB::table('users')->insert([
            'name' => 'Operator Lapangan Satu',
            'email' => 'operator1@rvmsystem.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'google_id' => null,
            'avatar' => null,
            'phone_number' => '081200000002',
            'citizenship' => 'WNI',
            'identity_type' => 'KTP',
            'identity_number' => '3171000000000002', // Pastikan unik
            'points' => 0,
            'role' => 'Operator',
            'is_guest' => false,
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Regular Users
        $usersData = [
            [
                'name' => 'Budi Santoso',
                'email' => 'budi.s@example.com',
                'phone_number' => '081234567890',
                'identity_number' => '3171012345670001', // Pastikan unik
                'points' => 150,
            ],
            [
                'name' => 'Citra Lestari',
                'email' => 'citra.l@example.com',
                'phone_number' => '081234567891',
                'identity_number' => '3171012345670002', // Pastikan unik
                'points' => 75,
            ],
            [
                'name' => 'Dewi Anggraini',
                'email' => 'dewi.a@example.com',
                'phone_number' => '081234567892',
                'identity_number' => '3171012345670003', // Pastikan unik
                'points' => 220,
            ],
            [
                'name' => 'Eko Prasetyo',
                'email' => 'eko.p@example.com',
                'phone_number' => '081234567893',
                'identity_number' => '3171012345670004', // Pastikan unik
                'google_id' => 'google_id_eko_123', // Contoh google_id
                'points' => 50,
            ],
            [
                'name' => 'Putu Agus',
                'email' => 'putu.a@example.com',
                'phone_number' => '081234567895',
                'identity_number' => '5171012345670001', // Contoh NIK Bali
                'citizenship' => 'WNI',
                'identity_type' => 'KTP',
                'points' => 120,
            ],
            [
                'name' => 'John Doe (WNA)',
                'email' => 'john.doe@example.com',
                'phone_number' => '081234567896',
                'identity_number' => 'A123456789XYZ', // Contoh No Paspor
                'citizenship' => 'WNA',
                'identity_type' => 'Pasport',
                'points' => 95,
            ],
        ];

        foreach ($usersData as $userData) {
            DB::table('users')->insert([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password123'), // Default password untuk user biasa
                'google_id' => $userData['google_id'] ?? null,
                'avatar' => $userData['avatar'] ?? null,
                'phone_number' => $userData['phone_number'],
                'citizenship' => $userData['citizenship'] ?? 'WNI',
                'identity_type' => $userData['identity_type'] ?? 'KTP',
                'identity_number' => $userData['identity_number'],
                'points' => $userData['points'],
                'role' => 'User',
                'is_guest' => false,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
