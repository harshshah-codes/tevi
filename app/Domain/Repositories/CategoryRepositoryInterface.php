<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\Category;

interface CategoryRepositoryInterface
{
    /**
     * @return Category[]
     */
    public function findAll(): array;

    public function findById(int $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    public function create(Category $category): int;

    public function update(Category $category): bool;

    public function delete(int $id): bool;
}