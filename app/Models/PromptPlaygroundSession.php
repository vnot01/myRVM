<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PromptPlaygroundSession extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'session_name', 'base_template_id', 'target_prompt_text',
        'condition_prompt_text', 'label_guidance_text', 'output_instructions_text',
        'generation_config_values', 'test_image_path_snapshot',
        'gemini_response_snapshot', 'notes',
    ];
    protected $casts = [
        'generation_config_values' => 'array',
        'gemini_response_snapshot' => 'array',
    ];
    public function user() { return $this->belongsTo(User::class); }
    public function baseTemplate() { return $this->belongsTo(PromptTemplate::class, 'base_template_id'); }
}
