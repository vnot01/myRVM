<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     * (Siapa yang boleh melihat daftar user)
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'Admin' || $user->role === 'Operator';
    }

    /**
     * Determine whether the user can view the model.
     * (Siapa yang boleh melihat detail satu user)
     */
    public function view(User $user, User $model): bool // $model adalah user yang ingin dilihat
    {
        return $user->role === 'Admin' || $user->role === 'Operator' || $user->id === $model->id; // Boleh lihat diri sendiri
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'Admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool // $model adalah user yang ingin diupdate
    {
        // Admin bisa update siapa saja (kecuali mungkin super-admin lain)
        // Operator mungkin tidak bisa update sama sekali atau terbatas
        // User hanya bisa update profilnya sendiri (biasanya di controller ProfileController)
        return $user->role === 'Admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool // $model adalah user yang ingin dihapus
    {
        // Admin tidak boleh hapus dirinya sendiri
        if ($user->id === $model->id && $user->role === 'Admin') {
            return false;
        }
        return $user->role === 'Admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
