<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Règle métier violée (document déjà archivé, catégorie non vide, etc.).
 * Les services la lèvent ; elle est convertie en réponse JSON { "message": ... }
 * avec le code HTTP voulu (409, 422, 404...). Les contrôleurs n'ont donc plus
 * à construire ces réponses d'erreur eux-mêmes.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 409)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** Erreur attendue : un report() qui ne renvoie pas false évite de la consigner dans les logs. */
    public function report(): void
    {
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
