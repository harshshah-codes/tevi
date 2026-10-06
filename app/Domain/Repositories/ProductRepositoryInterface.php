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

    /**
     * @return Product[]
     */
    public function findAll(): array;

    /**
     * Search the catalog by name, description or slug.
     *
     * @return Product[]
     */
    public function search(string $term, int $limit = 60): array;

    /**
     * List the catalog, optionally narrowed to a badge or category.
     *
     * @return Product[]
     */
    public function listFiltered(int $limit = 60, ?string $badge = null, ?string $categorySlug = null): array;

    public function create(Product $product): int;

    public function update(Product $product): bool;

    public function delete(int $id): bool;

    public function attachCategories(int $productId, array $categoryIds): bool;

    public function detachCategories(int $productId): bool;
}