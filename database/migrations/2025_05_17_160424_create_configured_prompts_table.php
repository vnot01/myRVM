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
        Schema::create('configured_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('configured_prompt_name')->unique();
            $table->foreignId('prompt_template_id')->nullable()->constrained('prompt_templates')->onDelete('set null');
            $table->text('description')->nullable();
            $table->text('full_prompt_text_generated')->nullable(); // Hasil rakitan
            $table->json('generation_config_final')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->integer('version')->default(1);
            $table->foreignId('root_configured_prompt_id')->nullable()->constrained('configured_prompts')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configured_prompts');
    }
};
