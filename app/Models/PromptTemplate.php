<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model {
    use HasFactory;
    protected $fillable = [
        'template_name',
        'template_string',
        'description',
        'placeholders_defined', // Pastikan ini ada di $fillable
    ];

    // INI BAGIAN PALING PENTING UNTUK MASALAH INI
    protected $casts = [
        'placeholders_defined' => 'array', // HARUS 'array', bukan 'json' atau yang lain untuk konversi otomatis ke array PHP
    ];

    public function configuredPrompts() {
        return $this->hasMany(ConfiguredPrompt::class);
    }
}