<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DocumentVersionService
{
    public function __construct(private readonly DocumentValidationService $validation)
    {
    }

    /** Disque privé (storage/app/private) : jamais accessible directement par URL. */
    public const DISK = 'local';

    /**
     * Ajoute une nouvelle version à un document. Les versions précédentes
     * ne sont jamais modifiées ni supprimées.
     *
     * Le numéro est calculé dans une transaction avec verrou sur le document,
     * pour que deux envois simultanés n'obtiennent pas le même numéro (en plus
     * de la contrainte unique (document_id, numero_version) en base).
     */
    public function addVersion(Document $document, User $author, UploadedFile $file): DocumentVersion
    {
        return DB::transaction(function () use ($document, $author, $file) {
            Document::whereKey($document->getKey())->lockForUpdate()->first();

            $next = ((int) $document->versions()->reorder()->max('numero_version')) + 1;

            // Nom stocké aléatoire : le nom d'origine du client n'est jamais utilisé comme chemin.
            $extension = strtolower($file->getClientOriginalExtension());
            $storedName = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
            $path = $file->storeAs('documents/'.$document->getKey(), $storedName, self::DISK);

            try {
                return $document->versions()->create([
                    'user_id' => $author->getKey(),
                    'numero_version' => $next,
                    'chemin_fichier' => $path,
                    'nom_original' => $file->getClientOriginalName(),
                    'taille' => $file->getSize(),
                ]);
            } catch (Throwable $e) {
                // Pas de fichier orphelin si l'insertion échoue.
                Storage::disk(self::DISK)->delete($path);
                throw $e;
            }
        });
    }

    /**
     * Dépose une nouvelle version et journalise l'action.
     *
     * Le contenu change : une décision de validation / rejet ne vaut plus, le document
     * repasse « en attente » pour être revalidé. (save() met aussi updated_at à jour.)
     */
    public function addVersionToDocument(Document $document, User $author, UploadedFile $file): DocumentVersion
    {
        $version = $this->addVersion($document, $author, $file);

        $wasDecided = $document->validation !== Document::VALIDATION_EN_ATTENTE;
        if ($wasDecided) {
            $this->validation->resetToPending($document);
        } else {
            $document->touch();
        }

        ActivityLogger::log(
            $author,
            ActivityLog::VERSION_ADDED,
            'a ajouté la version '.$version->numero_version.' au document '.ActivityLogger::label($document).'.'
                .($wasDecided ? ' Le document repasse en attente de validation.' : ''),
            $document
        );

        return $version->load('author:id,name');
    }

    /**
     * Vérifie que le fichier existe sur le disque privé et journalise le téléchargement.
     * Le contrôleur envoie ensuite le flux binaire.
     */
    public function recordDownload(Document $document, DocumentVersion $version, User $user): void
    {
        if (! Storage::disk(self::DISK)->exists($version->chemin_fichier)) {
            throw new BusinessRuleException('Fichier introuvable sur le serveur.', 404);
        }

        ActivityLogger::log($user, ActivityLog::VERSION_DOWNLOADED, 'a téléchargé la version '.$version->numero_version.' du document '.ActivityLogger::label($document).'.', $document);
    }

    /** Supprime tous les fichiers physiques d'un document. */
    public function deleteFiles(int $documentId): void
    {
        Storage::disk(self::DISK)->deleteDirectory('documents/'.$documentId);
    }
}
