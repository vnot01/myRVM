<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens; // Jika RVM akan memiliki token Sanctum sendiri

class ReverseVendingMachine extends Model
{
    use HasFactory;
    // use HasApiTokens; // Uncomment jika RVM akan memiliki token API-nya sendiri via Sanctum
    // Jika hanya pakai api_key statis, ini tidak perlu untuk model RVM.

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'location_description',
        'latitude',
        'longitude',
        'status',
        'api_key', // Jika api_key di-generate dan disimpan di sini
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7', // Sesuaikan presisi jika perlu
            'longitude' => 'decimal:7',
            // 'status' => 'string', // Enum sudah string by default
        ];
    }

    /**
     * Get the deposits for the RVM.
     */
    public function deposits()
    {
        return $this->hasMany(Deposit::class, 'rvm_id'); // Spesifikasikan foreign key jika berbeda dari konvensi
    }

    // Jika menggunakan Sanctum dan RVM adalah tokenable:
    // Anda mungkin perlu mengimplementasikan interface Authenticatable atau trait tertentu
    // Namun, untuk kasus kita, RVM mungkin lebih sebagai "client" yang menggunakan api_key statis atau token yang di-generate khusus.
    // Jika menggunakan Sanctum untuk RVM sebagai 'personal access token client',
    // maka RVM tidak perlu 'HasApiTokens' trait pada modelnya, karena RVM itu sendiri bukan user.
    // Trait HasApiTokens lebih untuk model User yang bisa issue token.
}
