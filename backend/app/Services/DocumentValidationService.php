<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\User;

/**
 * Circuit de validation : en_attente -> valide | rejete, et retour à « en attente ».
 * Les droits (administrateurs et archivistes) sont vérifiés par DocumentPolicy dans le contrôleur.
 */
class DocumentValidationService
{
    public function approve(Document $document, User $user): Document
    {
        if ($document->validation === Document::VALIDATION_VALIDE) {
            throw new BusinessRuleException('Ce document est déjà validé.', 409);
        }

        return $this->transition($document, $user, Document::VALIDATION_VALIDE, null);
    }

    public function reject(Document $document, User $user, string $motif): Document
    {
        if ($document->validation === Document::VALIDATION_REJETE) {
            throw new BusinessRuleException('Ce document est déjà rejeté.', 409);
        }

        return $this->transition($document, $user, Document::VALIDATION_REJETE, $motif);
    }

    /** Remet le document « en attente » (correction d'une décision). */
    public function reopen(Document $document, User $user): Document
    {
        if ($document->validation === Document::VALIDATION_EN_ATTENTE) {
            throw new BusinessRuleException('Ce document est déjà en attente de validation.', 409);
        }

        $this->resetToPending($document);

        ActivityLogger::log($user, ActivityLog::DOCUMENT_REOPENED, 'a remis le document '.ActivityLogger::label($document).' en attente de validation.', $document);

        return $document->load(DocumentService::RELATIONS);
    }

    /** Efface la décision de validation (utilisé aussi quand une nouvelle version est déposée). */
    public function resetToPending(Document $document): void
    {
        $document->forceFill([
            'validation' => Document::VALIDATION_EN_ATTENTE,
            'motif_rejet' => null,
            'validated_by' => null,
            'validated_at' => null,
        ])->save();
    }

    private function transition(Document $document, User $user, string $state, ?string $motif): Document
    {
        $document->forceFill([
            'validation' => $state,
            'motif_rejet' => $motif,
            'validated_by' => $user->id,
            'validated_at' => now(),
        ])->save();

        $label = ActivityLogger::label($document);
        $approved = $state === Document::VALIDATION_VALIDE;
        ActivityLogger::log(
            $user,
            $approved ? ActivityLog::DOCUMENT_VALIDATED : ActivityLog::DOCUMENT_REJECTED,
            $approved ? 'a validé le document '.$label.'.' : 'a rejeté le document '.$label.'. Motif : '.$motif,
            $document
        );

        return $document->load(DocumentService::RELATIONS);
    }
}
