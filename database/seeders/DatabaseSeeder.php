<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Panggil seeder dalam urutan yang benar (User dan RVM dulu, baru Deposit)
        $this->call([
            UserSeeder::class,
            ReverseVendingMachineSeeder::class,
            DepositSeeder::class,
            PromptTemplateSeeder::class,
            // Tambahkan seeder lain di sini jika ada
        ]);

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
