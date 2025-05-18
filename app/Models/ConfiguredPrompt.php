<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ConfiguredPrompt extends Model
{
    use HasFactory;
    protected $fillable = [
        'configured_prompt_name',
        'prompt_template_id',
        'description',
        'full_prompt_text_generated',
        'generation_config_final',
        'is_active',
        'version',
        'root_configured_prompt_id',
    ];
    protected $casts = [
        'generation_config_final' => 'array',
        'is_active' => 'boolean',
    ];
    public function template()
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }
    public function componentMappings()
    {
        return $this->hasMany(ConfiguredPromptComponentMapping::class, 'configured_prompt_id');
    }
    public function rootPrompt()
    {
        return $this->belongsTo(ConfiguredPrompt::class, 'root_configured_prompt_id');
    }
    public function revisions()
    {
        return $this->hasMany(ConfiguredPrompt::class, 'root_configured_prompt_id');
    }
    protected static function booted()
    { // Untuk clear cache saat ada perubahan pada prompt aktif
        static::saved(function ($configuredPrompt) {
            if ($configuredPrompt->isDirty('is_active') || $configuredPrompt->is_active) {
                Cache::forget(config('services.google.active_prompt_cache_key', 'gemini_active_configured_prompt'));
            }
        });
        static::deleted(function ($configuredPrompt) {
            Cache::forget(config('services.google.active_prompt_cache_key', 'gemini_active_configured_prompt'));
        });
    }
}
