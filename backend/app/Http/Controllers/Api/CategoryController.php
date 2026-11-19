<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories)
    {
    }

    public function index()
    {
        Gate::authorize('viewAny', Category::class);

        return CategoryResource::collection($this->categories->all());
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $category = $this->categories->create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        return new CategoryResource($this->categories->update($category, $request->validated()));
    }

    public function destroy(Category $category): Response
    {
        Gate::authorize('delete', $category);

        $this->categories->delete($category);

        return response()->noContent();
    }
}
