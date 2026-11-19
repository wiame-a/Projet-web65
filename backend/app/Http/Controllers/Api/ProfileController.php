<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;

/** Compte de l'utilisateur connecté. La logique est dans ProfileService. */
class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profile)
    {
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        return new UserResource($this->profile->update($request->user(), $request->validated()));
    }

    public function password(UpdatePasswordRequest $request): JsonResponse
    {
        $this->profile->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return response()->json(['message' => 'Votre mot de passe a bien été modifié.']);
    }
}
