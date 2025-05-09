<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder; // Untuk scope

class PromptTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'target_prompt',
        'condition_prompt',
        'label_guidance',
        'output_instructions',
        'generation_config',
        'is_active',
    ];

    protected $casts = [
        'generation_config' => 'array', // Otomatis encode/decode JSON
        'is_active' => 'boolean',
    ];

    /**
     * Scope untuk mendapatkan template yang aktif.
     * Menggunakan 'Builder' type hint untuk auto-completion.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Method untuk membangun prompt lengkap dari bagian-bagiannya.
     */
    public function buildFullPrompt(): string
    {
        // Format penggabungan ini bisa disesuaikan nanti jika diperlukan
        return "Analyze the image to detect {$this->target_prompt}. For each detected item, {$this->condition_prompt}. {$this->output_instructions} Each entry in the list must be an object containing: 1. 'box_2d': The 2D bounding box ([ymin, xmin, ymax, xmax] scaled 0-1000). 2. 'label': {$this->label_guidance}";
    }
}