<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PromptComponent extends Model
{
    use HasFactory;
    protected $fillable = [
        'component_name', 'component_type', 'content', 'description',
    ];
}
