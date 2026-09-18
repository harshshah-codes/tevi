<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\Product;

interface ProductRepositoryInterface
{
    /**
     * @return Product[]
     */
    public function findFeatured(): array;

    /**
     * @return Product[]
     */
    public function findNewArrivals(int $limit = 8): array;

    public function findById(int $id): ?Product;

    public function findBySlug(string $slug): ?Product;

    /**
     * Products sharing at least one of the given categories, excluding one product.
     *
     * @param int[] $categoryIds
     * @return Product[]
     */
    public function findRelated(array $categoryIds, int $excludeProductId, int $limit = 4): array;
}