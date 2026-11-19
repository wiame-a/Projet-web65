<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'stats' => $this->dashboard->stats($user),
            'recents' => DocumentResource::collection($this->dashboard->recents($user))->resolve($request),
        ]);
    }
}
