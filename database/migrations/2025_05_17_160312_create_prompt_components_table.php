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
        Schema::create('prompt_components', function (Blueprint $table) {
            $table->id();
            $table->string('component_name')->unique();
            $table->string('component_type'); // ENUM: 'target_description', 'condition_details', 'label_options', 'output_format_definition', 'generation_config_preset'
            $table->text('content'); // Bisa teks biasa atau string JSON untuk generation_config_preset
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_components');
    }
};
