<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVersionRequest;
use App\Http\Resources\VersionResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVersionController extends Controller
{
    public function __construct(private readonly DocumentVersionService $service)
    {
    }

    /** Historique complet des versions (de la plus récente à la plus ancienne). */
    public function index(Document $document)
    {
        Gate::authorize('view', $document);

        return VersionResource::collection($document->versions()->with('author:id,name')->get());
    }

    /** Ajoute une version sans toucher aux précédentes. */
    public function store(StoreVersionRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('addVersion', $document); // refuse : lecture seule, archivé, sans accès

        $version = $this->service->addVersionToDocument($document, $request->user(), $request->file('fichier'));

        return (new VersionResource($version))->response()->setStatusCode(201);
    }

    /**
     * Téléchargement sécurisé : le fichier est dans un répertoire privé et
     * n'est envoyé (en flux binaire) qu'après vérification du droit de lecture.
     * La route utilise scopeBindings : la version doit appartenir au document.
     */
    public function download(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        Gate::authorize('view', $document);

        $this->service->recordDownload($document, $version, $request->user());

        return Storage::disk(DocumentVersionService::DISK)->download($version->chemin_fichier, $version->nom_original, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
