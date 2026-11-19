<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/** Inscription, connexion et déconnexion : la logique d'authentification. */
class AuthService
{
    /**
     * Crée un compte. Le rôle n'est pas mass-assignable : un nouveau compte est toujours « user ».
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create($data);

        return ['user' => $user->fresh(), 'token' => $this->issueToken($user)];
    }

    /** Retourne l'utilisateur si les identifiants sont corrects, sinon null (même cas : email inconnu ou mauvais mot de passe). */
    public function attempt(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    public function issueToken(User $user): string
    {
        return $user->createToken('spa')->plainTextToken;
    }

    /** Révoque uniquement le jeton utilisé pour la requête courante. */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
