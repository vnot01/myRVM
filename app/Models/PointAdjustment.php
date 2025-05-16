<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PointAdjustment extends Model
{
    //
    use HasFactory;
    protected $fillable = [
        'user_id', 'adjusted_by_user_id', 'previous_points',
        'points_changed', 'new_points', 'reason',
    ];
    public function user() { return $this->belongsTo(User::class); }
    public function adjustedBy() { return $this->belongsTo(User::class, 'adjusted_by_user_id'); }
}
