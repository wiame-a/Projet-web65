<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Compte de l'utilisateur connecté : nom, email et mot de passe. Le rôle n'est jamais modifiable ici. */
class ProfileService
{
    public function update(User $user, array $data): User
    {
        $user->fill($data)->save();

        ActivityLogger::log($user, ActivityLog::PROFILE_UPDATED, 'a modifié ses informations de profil.');

        return $user->fresh();
    }

    public function changePassword(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }

        $user->password = $new; // haché automatiquement (cast « hashed »)
        $user->save();

        // Sécurité : toutes les AUTRES sessions sont déconnectées, celle-ci reste ouverte.
        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        ActivityLogger::log($user, ActivityLog::PASSWORD_CHANGED, 'a changé son mot de passe.');
    }
}
