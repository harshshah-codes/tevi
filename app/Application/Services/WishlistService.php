<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\WishlistItem;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\WishlistRepositoryInterface;

final class WishlistService
{
    public function __construct(
        private readonly WishlistRepositoryInterface $repository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @return WishlistItem[]
     */
    public function listForUser(int $userId): array
    {
        return $this->repository->findForUser($userId);
    }

    public function contains(int $userId, int $productId): bool
    {
        return $this->repository->contains($userId, $productId);
    }

    /**
     * @return WishlistItem|null
     */
    public function add(int $userId, int $productId, string $size = '', string $color = ''): ?WishlistItem
    {
        // A product that no longer exists must not be savable, or the wishlist
        // would fill with rows that render as blanks.
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            return null;
        }

        $this->repository->add($userId, $productId, $size, $color);

        foreach ($this->repository->findForUser($userId) as $item) {
            if ($item->productId() === $productId) {
                return $item;
            }
        }

        return null;
    }

    public function updateOptions(int $userId, int $productId, string $size = '', string $color = ''): bool
    {
        return $this->repository->updateOptions($userId, $productId, $size, $color);
    }

    public function remove(int $userId, int $productId): bool
    {
        return $this->repository->remove($userId, $productId);
    }

    public function clear(int $userId): int
    {
        return $this->repository->clear($userId);
    }

    /**
     * Fold a guest's localStorage wishlist into their account on sign-in.
     *
     * Only product ids that still exist in the catalog are taken, so a stale
     * guest list cannot resurrect deleted products.
     *
     * @param array<int, int> $productIds
     * @return array{added: int, skipped: int}
     */
    public function merge(int $userId, array $productIds): array
    {
        $valid = [];

        foreach (array_unique($productIds) as $productId) {
            $productId = (int) $productId;
            if ($productId > 0 && $this->productRepository->findById($productId) !== null) {
                $valid[] = $productId;
            }
        }

        $skipped = count(array_unique($productIds)) - count($valid);

        return [
            'added'   => $this->repository->addMissing($userId, $valid),
            'skipped' => max(0, $skipped),
        ];
    }
}