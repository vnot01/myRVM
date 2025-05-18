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
        Schema::create('prompt_playground_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Admin yang melakukan
            $table->string('session_name')->nullable();
            $table->foreignId('base_template_id')->nullable()->constrained('prompt_templates')->onDelete('set null');
            $table->text('target_prompt_text')->nullable();
            $table->text('condition_prompt_text')->nullable();
            $table->text('label_guidance_text')->nullable();
            $table->text('output_instructions_text')->nullable();
            $table->json('generation_config_values')->nullable();
            $table->string('test_image_path_snapshot')->nullable();
            $table->json('gemini_response_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_playground_sessions');
    }
};
