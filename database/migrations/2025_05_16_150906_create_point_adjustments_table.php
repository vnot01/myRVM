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
        Schema::create('point_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('adjusted_by_user_id')->constrained('users')->onDelete('cascade'); // Siapa yang mengubah
            $table->integer('previous_points');
            $table->integer('points_changed'); // Bisa positif atau negatif
            $table->integer('new_points');
            $table->text('reason')->nullable(); // Alasan perubahan (penting!)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_adjustments');
    }
};
