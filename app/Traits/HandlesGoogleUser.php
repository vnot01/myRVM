<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

trait HandlesGoogleUser
{
    /**
     * Finds an existing user or creates a new one based on Google profile.
     */
    protected function findOrCreateUserFromGoogle(SocialiteUserContract $socialiteUser, string $defaultRole = 'User'): User
    {
        $email = $socialiteUser->getEmail();
        if (empty($email)) {
            throw new \Exception('Email not provided by Google.');
        }

        // Cari user berdasarkan google_id ATAU email (jika google_id belum ada tapi email sudah terdaftar)
        $user = User::where('google_id', $socialiteUser->getId())
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            // User sudah ada (baik via google_id atau email), update data jika perlu
            $user->update([
                'name' => $socialiteUser->getName(),
                'avatar' => $socialiteUser->getAvatar(),
                // Set google_id jika belum ada (misalnya user register via email dulu)
                'google_id' => $user->google_id ?? $socialiteUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            return $user->refresh(); // Kembalikan instance yang sudah di-update
        } else {
            // User belum ada, buat baru
            return User::create([
                'google_id' => $socialiteUser->getId(),
                'name' => $socialiteUser->getName(),
                'email' => $email,
                'avatar' => $socialiteUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(24)), // Buat password acak untuk user baru
                'role' => $defaultRole,
            ]);
        }
    }

    /**
     * Finds an existing user or creates a new one based on Google ID Token Payload.
     */
    protected function findOrCreateUserFromGoogleIdTokenPayload(array $googleIdTokenPayload, string $defaultRole = 'User'): User
    {
        $googleUserId = $googleIdTokenPayload['sub'];
        $email = $googleIdTokenPayload['email'];
        if (empty($email)) {
            throw new \Exception('Email not provided in Google ID Token payload.');
        }

        // Cari user berdasarkan google_id ATAU email
        $user = User::where('google_id', $googleUserId)
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            // User sudah ada, update
            $user->update([
                'name' => $googleIdTokenPayload['name'] ?? $user->name, // Gunakan nama lama jika tidak ada di payload
                'avatar' => $googleIdTokenPayload['picture'] ?? $user->avatar, // Gunakan avatar lama jika tidak ada
                'google_id' => $user->google_id ?? $googleUserId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            return $user->refresh();
        } else {
            // User belum ada, buat baru
            return User::create([
                'google_id' => $googleUserId,
                'name' => $googleIdTokenPayload['name'] ?? 'Google User',
                'email' => $email,
                'avatar' => $googleIdTokenPayload['picture'] ?? null,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(24)),
                'role' => $defaultRole,
            ]);
        }
    }
}
