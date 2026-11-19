<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchDocumentRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Contrôleur fin : vérifie les droits (Policies), appelle DocumentService,
 * puis formate la réponse (Resources).
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    /** Liste + recherche multicritères (GET /documents et GET /documents/search). */
    public function index(SearchDocumentRequest $request)
    {
        return DocumentResource::collection(
            $this->documents->search($request->user(), $request->validated())
        );
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $document = $this->documents->create(
            $request->user(),
            $request->safe()->only(['titre', 'reference', 'description', 'mots_cles', 'category_id']),
            $request->file('fichier')
        );

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function show(Request $request, Document $document): DocumentResource
    {
        Gate::authorize('view', $document);

        $canShare = Gate::forUser($request->user())->allows('share', $document);

        return new DocumentResource($this->documents->loadForShow($document, $request->user(), $canShare));
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        Gate::authorize('update', $document); // refuse aussi si le document est archivé

        return new DocumentResource($this->documents->update($document, $request->user(), $request->validated()));
    }

    public function destroy(Request $request, Document $document): Response
    {
        Gate::authorize('delete', $document);

        $this->documents->delete($document, $request->user());

        return response()->noContent();
    }

    public function archive(Request $request, Document $document): DocumentResource
    {
        Gate::authorize('archive', $document);

        return new DocumentResource($this->documents->archive($document, $request->user()));
    }

    public function restore(Request $request, Document $document): DocumentResource
    {
        Gate::authorize('restore', $document);

        return new DocumentResource($this->documents->restore($document, $request->user()));
    }
}
