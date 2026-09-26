<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\Category;
use App\Domain\Repositories\CategoryRepositoryInterface;

final class AdminCategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
    ) {
    }

    /**
     * @return Category[]
     */
    public function getAllCategories(): array
    {
        return $this->repository->findAll();
    }

    public function getCategory(int $id): ?Category
    {
        return $this->repository->findById($id);
    }

    public function createCategory(Category $category): int
    {
        return $this->repository->create($category);
    }

    public function updateCategory(Category $category): bool
    {
        return $this->repository->update($category);
    }

    public function deleteCategory(int $id): bool
    {
        return $this->repository->delete($id);
    }
}