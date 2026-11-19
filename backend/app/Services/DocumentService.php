<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'un document : recherche, création, modification, suppression,
 * archivage et restauration. Les contrôleurs vérifient les droits (Policies),
 * puis délèguent ici.
 */
class DocumentService
{
    /** Relations chargées pour renvoyer un document complet. */
    public const RELATIONS = ['category', 'owner:id,name', 'validator:id,name', 'latestVersion'];

    public function __construct(private readonly DocumentVersionService $versions)
    {
    }

    /**
     * Liste + recherche multicritères. Les filtres sont combinés (ET) et ne portent
     * QUE sur les documents accessibles à l'utilisateur connecté.
     */
    public function search(User $user, array $f): LengthAwarePaginator
    {
        $query = Document::query()
            ->accessibleBy($user)
            ->with([
                'category',
                'owner:id,name',
                'validator:id,name',
                'latestVersion',
                // On ne charge que le partage de l'utilisateur courant (calcul du droit sans N+1).
                'permissions' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->withCount('versions');

        $query
            ->when($f['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.$term.'%';
                $q->where(fn (Builder $w) => $w
                    ->where('titre', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('mots_cles', 'like', $like));
            })
            ->when($f['titre'] ?? null, fn (Builder $q, string $v) => $q->where('titre', 'like', '%'.$v.'%'))
            ->when($f['mots_cles'] ?? null, function (Builder $q, string $v) {
                // Chaque mot-clé saisi doit être présent (ET).
                foreach (array_filter(array_map('trim', explode(',', $v))) as $word) {
                    $q->where('mots_cles', 'like', '%'.$word.'%');
                }
            })
            ->when($f['category_id'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($f['user_id'] ?? null, fn (Builder $q, $v) => $q->where('documents.user_id', $v))
            ->when($f['statut'] ?? null, fn (Builder $q, $v) => $q->where('statut', $v))
            ->when($f['validation'] ?? null, fn (Builder $q, $v) => $q->where('validation', $v))
            ->when($f['date_debut'] ?? null, fn (Builder $q, $v) => $q->whereDate('documents.created_at', '>=', $v))
            ->when($f['date_fin'] ?? null, fn (Builder $q, $v) => $q->whereDate('documents.created_at', '<=', $v))
            ->when(($f['portee'] ?? null) === 'mes', fn (Builder $q) => $q->where('documents.user_id', $user->id))
            ->when(($f['portee'] ?? null) === 'partages', fn (Builder $q) => $q
                ->where('documents.user_id', '!=', $user->id)
                ->whereHas('permissions', fn (Builder $p) => $p->where('user_id', $user->id)));

        $query->orderBy('documents.'.($f['tri'] ?? 'created_at'), $f['ordre'] ?? 'desc')
            ->orderByDesc('documents.id');

        return $query->paginate($f['per_page'] ?? 10)->withQueryString();
    }

    /** Charge ce qu'affiche la fiche d'un document. */
    public function loadForShow(Document $document, User $user, bool $canShare): Document
    {
        $document->load([...self::RELATIONS, 'versions.author:id,name']);

        // La liste complète des partages n'est visible que par ceux qui peuvent partager.
        if ($canShare) {
            $document->load('permissions.user:id,name,email');
        } else {
            $document->load(['permissions' => fn ($q) => $q->where('user_id', $user->id)]);
        }

        return $document;
    }

    /**
     * Crée le document et sa version n° 1.
     *
     * @param  array{titre: string, reference?: ?string, description?: ?string, mots_cles?: ?string, category_id: int}  $data
     */
    public function create(User $user, array $data, UploadedFile $file): Document
    {
        $document = DB::transaction(function () use ($user, $data, $file) {
            $document = new Document($data);
            $document->user_id = $user->id;
            $document->statut = Document::STATUT_ACTIF;
            $document->save();

            // La première version (n° 1) est créée avec le document.
            $this->versions->addVersion($document, $user, $file);

            return $document;
        });

        ActivityLogger::log($user, ActivityLog::DOCUMENT_CREATED, 'a créé le document '.ActivityLogger::label($document).'.', $document);

        return $document->load(self::RELATIONS);
    }

    public function update(Document $document, User $user, array $data): Document
    {
        $document->update($data);

        ActivityLogger::log($user, ActivityLog::DOCUMENT_UPDATED, 'a modifié les informations du document '.ActivityLogger::label($document).'.', $document);

        return $document->load(self::RELATIONS);
    }

    public function delete(Document $document, User $user): void
    {
        $id = $document->id;
        $label = ActivityLogger::label($document); // à capturer avant la suppression
        $document->delete(); // les versions et partages partent en cascade (clés étrangères)
        $this->versions->deleteFiles($id);

        // Sans lien vers le document (il n'existe plus) : la phrase garde son titre et son code.
        ActivityLogger::log($user, ActivityLog::DOCUMENT_DELETED, 'a supprimé le document '.$label.'.');
    }

    public function archive(Document $document, User $user): Document
    {
        if ($document->isArchived()) {
            throw new BusinessRuleException('Ce document est déjà archivé.', 409);
        }

        $document->statut = Document::STATUT_ARCHIVE;
        $document->archived_at = now();
        $document->save();

        ActivityLogger::log($user, ActivityLog::DOCUMENT_ARCHIVED, 'a archivé le document '.ActivityLogger::label($document).'.', $document);

        return $document->load(self::RELATIONS);
    }

    public function restore(Document $document, User $user): Document
    {
        if (! $document->isArchived()) {
            throw new BusinessRuleException('Ce document n\'est pas archivé.', 409);
        }

        $document->statut = Document::STATUT_ACTIF;
        $document->archived_at = null;
        $document->save();

        ActivityLogger::log($user, ActivityLog::DOCUMENT_RESTORED, 'a restauré le document '.ActivityLogger::label($document).'.', $document);

        return $document->load(self::RELATIONS);
    }
}
