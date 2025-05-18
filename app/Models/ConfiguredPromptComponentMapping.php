<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model; // Atau Relations\Pivot jika Anda mau

class ConfiguredPromptComponentMapping extends Model
{
    use HasFactory;
    protected $fillable = [
        'configured_prompt_id', 'placeholder_in_template', 'prompt_component_id',
    ];
    public $timestamps = false; // Jika tidak ada kolom created_at/updated_at
    public function configuredPrompt() {
        return $this->belongsTo(ConfiguredPrompt::class);
    }
    public function component() {
        return $this->belongsTo(PromptComponent::class, 'prompt_component_id');
    }
}
