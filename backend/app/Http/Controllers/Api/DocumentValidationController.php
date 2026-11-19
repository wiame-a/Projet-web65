<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Circuit de validation. Réservé aux administrateurs et aux archivistes (voir DocumentPolicy).
 * Les règles et les changements d'état sont dans DocumentValidationService.
 */
class DocumentValidationController extends Controller
{
    public function __construct(private readonly DocumentValidationService $validation)
    {
    }

    public function approve(Request $request, Document $document): DocumentResource
    {
        Gate::authorize('validate', $document);

        return new DocumentResource($this->validation->approve($document, $request->user()));
    }

    public function reject(RejectDocumentRequest $request, Document $document): DocumentResource
    {
        Gate::authorize('reject', $document);

        return new DocumentResource(
            $this->validation->reject($document, $request->user(), $request->validated('motif'))
        );
    }

    /** Remet le document « en attente » (correction d'une décision). */
    public function reopen(Request $request, Document $document): DocumentResource
    {
        Gate::authorize('reopen', $document);

        return new DocumentResource($this->validation->reopen($document, $request->user()));
    }
}
