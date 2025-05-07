<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Jika Anda akan menggunakan Sanctum untuk API user

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // Tambahkan HasApiTokens jika perlu

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'phone_number',
        'citizenship',
        'identity_type',
        'identity_number',
        'points',
        'role',
        'is_guest',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array // Format baru untuk casts di Laravel 10+
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_guest' => 'boolean',
            'points' => 'integer',
            // 'citizenship' => 'string', // Enum sudah string by default
            // 'identity_type' => 'string',// Enum sudah string by default
            // 'role' => 'string',         // Enum sudah string by default
        ];
    }

    /**
     * Get the deposits for the user.
     */
    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }
}
