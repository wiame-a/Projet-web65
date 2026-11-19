<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/** Partage d'un document avec un autre utilisateur (droit de lecture ou de modification). */
class DocumentPermissionService
{
    public function list(Document $document): Collection
    {
        return $document->permissions()->with('user:id,name,email')->get();
    }

    /** Crée le partage, ou met à jour le droit s'il existe déjà. */
    public function share(Document $document, User $actor, int $targetUserId, string $droit): DocumentPermission
    {
        if ($targetUserId === (int) $document->user_id) {
            throw ValidationException::withMessages([
                'user_id' => 'Le propriétaire a déjà tous les droits sur ce document.',
            ]);
        }

        $permission = DocumentPermission::updateOrCreate(
            ['document_id' => $document->id, 'user_id' => $targetUserId],
            ['droit' => $droit]
        );

        $permission->load('user:id,name,email');
        ActivityLogger::log(
            $actor,
            ActivityLog::SHARE_GRANTED,
            'a partagé le document '.ActivityLogger::label($document).' avec '.$permission->user->name.' ('.($droit === 'modification' ? 'modification' : 'lecture seule').').',
            $document
        );

        return $permission;
    }

    public function revoke(Document $document, User $actor, User $target): void
    {
        $deleted = $document->permissions()->where('user_id', $target->id)->delete();

        if ($deleted === 0) {
            throw new BusinessRuleException('Ce document n\'est pas partagé avec cet utilisateur.', 404);
        }

        ActivityLogger::log($actor, ActivityLog::SHARE_REVOKED, 'a retiré l\'accès de '.$target->name.' au document '.ActivityLogger::label($document).'.', $document);
    }
}
