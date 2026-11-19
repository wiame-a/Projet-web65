<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/** Administration des comptes et recherche d'utilisateurs pour le partage. */
class UserService
{
    /** Liste paginée des comptes, avec recherche sur le nom ou l'email. */
    public function paginate(string $search = ''): LengthAwarePaginator
    {
        $search = trim($search);

        return User::query()
            ->withCount('documents')
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Recherche légère pour la boîte de partage : id, nom, email ; 10 résultats max ;
     * 2 caractères minimum ; l'utilisateur courant est exclu.
     */
    public function lookup(string $term, User $current): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return new Collection();
        }

        return User::query()
            ->where('id', '!=', $current->id)
            ->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);
    }

    public function changeRole(User $target, User $actor, string $role): User
    {
        if ($target->is($actor)) {
            throw new BusinessRuleException('Vous ne pouvez pas modifier votre propre rôle.', 422);
        }

        $target->role = $role; // le rôle n'est pas mass-assignable
        $target->save();

        return $target;
    }

    public function delete(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw new BusinessRuleException('Vous ne pouvez pas supprimer votre propre compte.', 422);
        }

        if ($target->documents()->exists()) {
            throw new BusinessRuleException('Suppression impossible : cet utilisateur possède encore des documents.', 409);
        }

        $target->tokens()->delete();
        $target->delete();
    }
}
