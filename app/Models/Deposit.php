<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'rvm_id',
        'detected_type',
        'points_awarded',
        'image_path',
        'gemini_raw_label',
        'gemini_raw_response',
        'needs_action',
        'deposited_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gemini_raw_response' => 'array', // Cast JSON ke array PHP
            'needs_action' => 'boolean',
            'points_awarded' => 'integer',
            'deposited_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the deposit.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the RVM where the deposit was made.
     */
    public function reverseVendingMachine() // Nama method bisa juga rvm()
    {
        return $this->belongsTo(ReverseVendingMachine::class, 'rvm_id');
    }
}
