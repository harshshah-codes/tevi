<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\Review;

interface ReviewRepositoryInterface
{
    /**
     * Visible reviews for a given product, newest first.
     *
     * @return Review[]
     */
    public function findByProductId(int $productId): array;

    /**
     * All visible reviews across all products, highest rating first.
     *
     * @return Review[]
     */
    public function findVisible(): array;

    /**
     * @return array{avg: float, count: int}
     */
    public function summaryByProductId(int $productId): array;
}