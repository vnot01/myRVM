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
        Schema::create('configured_prompt_component_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configured_prompt_id')
                  ->constrained('configured_prompts')
                  ->onDelete('cascade')
                  ->name('cpcm_configured_prompt_fk');
            $table->string('placeholder_in_template'); // e.g., "target_desc"
            $table->foreignId('prompt_component_id')
                  ->constrained('prompt_components')
                  ->onDelete('cascade')
                  ->name('cpcm_prompt_component_fk');
            // $table->timestamps(); // Biasanya tidak perlu timestamp untuk tabel pivot murni
            $table->unique(['configured_prompt_id', 'placeholder_in_template'], 'cpcm_unique_placeholder_per_prompt'); // Pastikan placeholder unik per configured_prompt
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configured_prompt_component_mappings');
    }
};
