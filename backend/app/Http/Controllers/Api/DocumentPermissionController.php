<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShareDocumentRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DocumentPermissionController extends Controller
{
    public function __construct(private readonly DocumentPermissionService $permissions)
    {
    }

    public function index(Document $document)
    {
        Gate::authorize('share', $document);

        return PermissionResource::collection($this->permissions->list($document));
    }

    /** Crée le partage, ou met à jour le droit s'il existe déjà. */
    public function store(ShareDocumentRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('share', $document);

        $data = $request->validated();
        $permission = $this->permissions->share($document, $request->user(), (int) $data['user_id'], $data['droit']);

        return (new PermissionResource($permission))
            ->response()
            ->setStatusCode($permission->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, Document $document, User $user): Response
    {
        Gate::authorize('share', $document);

        $this->permissions->revoke($document, $request->user(), $user);

        return response()->noContent();
    }
}
