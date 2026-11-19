<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    /** Administration : liste paginée des comptes. */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection($this->users->paginate((string) $request->query('q', '')));
    }

    /** Recherche légère pour la boîte de partage (tout utilisateur connecté). */
    public function lookup(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->users->lookup((string) $request->query('q', ''), $request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        Gate::authorize('update', $user);

        return new UserResource($this->users->changeRole($user, $request->user(), $request->validated('role')));
    }

    public function destroy(Request $request, User $user): Response
    {
        Gate::authorize('delete', $user);

        $this->users->delete($user, $request->user());

        return response()->noContent();
    }
}
