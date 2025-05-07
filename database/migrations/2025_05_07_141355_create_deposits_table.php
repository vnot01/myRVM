<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id(); // bigIncrements, unsigned, primary

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users') // Merujuk ke tabel 'users'
                ->onDelete('set null'); // Jika user dihapus, user_id di sini jadi null

            $table->foreignId('rvm_id')
                ->constrained('reverse_vending_machines') // Merujuk ke tabel 'reverse_vending_machines'
                ->onDelete('cascade'); // Jika RVM dihapus, deposit terkait ikut terhapus

            $table->string('detected_type')->nullable(); // e.g., PET_BOTTLE, ALUMINUM_CAN
            $table->integer('points_awarded')->default(0);
            $table->string('image_path')->nullable(); // Path ke gambar yg disimpan
            $table->string('gemini_raw_label')->nullable(); // Label mentah dari Gemini
            $table->json('gemini_raw_response')->nullable(); // Seluruh JSON respons dari Gemini
            $table->boolean('needs_action')->default(false);
            $table->timestamp('deposited_at')->useCurrent(); // Otomatis diisi waktu saat ini
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
