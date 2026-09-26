<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\Category;
use App\Domain\Models\Product;
use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;

final class AdminProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    /**
     * @return Product[]
     */
    public function getAllProducts(): array
    {
        return $this->productRepository->findAll();
    }

    public function getProduct(int $id): ?Product
    {
        return $this->productRepository->findById($id);
    }

    public function createProduct(Product $product): int
    {
        return $this->productRepository->create($product);
    }

    public function updateProduct(Product $product): bool
    {
        return $this->productRepository->update($product);
    }

    public function deleteProduct(int $id): bool
    {
        return $this->productRepository->delete($id);
    }

    /**
     * @return Category[]
     */
    public function getAllCategories(): array
    {
        return $this->categoryRepository->findAll();
    }
}