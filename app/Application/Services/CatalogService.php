<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\Category;
use App\Domain\Models\Product;
use App\Domain\Models\Review;
use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ReviewRepositoryInterface;

final class CatalogService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
        private readonly ReviewRepositoryInterface $reviews,
    ) {
    }

    /**
     * @return Category[]
     */
    public function getCategories(): array
    {
        return $this->categories->findAll();
    }

    /**
     * @return Product[]
     */
    public function getFeaturedProducts(): array
    {
        return $this->products->findFeatured();
    }

    /**
     * @return Product[]
     */
    public function getNewArrivals(int $limit = 8): array
    {
        return $this->products->findNewArrivals($limit);
    }

    public function getProductBySlug(string $slug): ?Product
    {
        return $slug === '' ? null : $this->products->findBySlug($slug);
    }

    public function getProductById(int $id): ?Product
    {
        return $id > 0 ? $this->products->findById($id) : null;
    }

    /**
     * @return Product[]
     */
    public function getRelatedProducts(Product $product, int $limit = 4): array
    {
        $categoryIds = array_map(
            static fn (Category $category): int => $category->id(),
            $product->categories()
        );

        $related = $this->products->findRelated($categoryIds, $product->id(), $limit);

        if ($related === []) {
            $related = array_values(array_filter(
                $this->products->findFeatured(),
                static fn (Product $candidate): bool => $candidate->id() !== $product->id()
            ));
        }

        return array_slice($related, 0, $limit);
    }

    /**
     * @return Review[]
     */
    public function getReviewsForProduct(Product $product): array
    {
        return $this->reviews->findByProductId($product->id());
    }

    /**
     * All visible reviews across all products, highest rating first.
     *
     * @return Review[]
     */
    public function getVisibleReviews(): array
    {
        return $this->reviews->findVisible();
    }

    /**
     * @return array{avg: float, count: int}
     */
    public function getReviewSummary(Product $product): array
    {
        return $this->reviews->summaryByProductId($product->id());
    }
}