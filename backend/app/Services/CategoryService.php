<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function all(): Collection
    {
        return Category::withCount('documents')->orderBy('nom')->get();
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->loadCount('documents');
    }

    public function delete(Category $category): void
    {
        if ($category->documents()->exists()) {
            throw new BusinessRuleException('Suppression impossible : cette catégorie contient encore des documents.', 409);
        }

        $category->delete();
    }
}
