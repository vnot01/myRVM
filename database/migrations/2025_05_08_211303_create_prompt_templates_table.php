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
            $table->string('template_name')->unique();
            $table->text('template_string'); // Dengan placeholder seperti {{target_desc}}
            $table->text('description')->nullable();
            $table->json('placeholders_defined')->nullable(); // Array string placeholder
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};