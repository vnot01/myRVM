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
        Schema::create('reverse_vending_machines', function (Blueprint $table) {
            $table->id(); // bigIncrements, unsigned, primary
            $table->string('name');
            $table->text('location_description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable(); // Total 10 digits, 7 after decimal point
            $table->decimal('longitude', 10, 7)->nullable(); // Total 10 digits, 7 after decimal point
            $table->enum('status', ['active', 'inactive', 'maintenance', 'full'])->default('active');
            $table->string('api_key')->unique(); // Dibuat not nullable, setiap RVM terdaftar wajib punya
            $table->timestamps(); // created_at and updated_a
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reverse_vending_machines');
    }
};
