<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\Review;
use App\Domain\Repositories\ReviewRepositoryInterface;

final class AdminReviewService
{
    public function __construct(
        private readonly ReviewRepositoryInterface $repository,
    ) {
    }

    /**
     * @return Review[]
     */
    public function getAllReviews(): array
    {
        return $this->repository->findAll();
    }

    public function getReview(int $id): ?Review
    {
        return $this->repository->findById($id);
    }

    public function createReview(Review $review): int
    {
        return $this->repository->create($review);
    }

    public function updateReview(Review $review): bool
    {
        return $this->repository->update($review);
    }

    public function deleteReview(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function setVisibility(int $id, bool $visible): bool
    {
        return $this->repository->setVisibility($id, $visible);
    }
}