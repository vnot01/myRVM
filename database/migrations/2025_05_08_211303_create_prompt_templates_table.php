<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->text('target_prompt');
            $table->text('condition_prompt');
            $table->text('label_guidance');
            $table->text('output_instructions');
            $table->json('generation_config')->nullable();
            $table->boolean('is_active')->default(false)->index(); // index untuk pencarian cepat
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};