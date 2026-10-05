<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\WishlistItem;

interface WishlistRepositoryInterface
{
    /**
     * @return WishlistItem[]
     */
    public function findForUser(int $userId): array;

    public function contains(int $userId, int $productId): bool;

    /**
     * Add a product, or return the existing row when it is already saved.
     *
     * @return int the wishlist row id
     */
    public function add(int $userId, int $productId, string $size = '', string $color = ''): int;

    /**
     * Change the saved size/colour of an item.
     */
    public function updateOptions(int $userId, int $productId, string $size = '', string $color = ''): bool;

    public function remove(int $userId, int $productId): bool;

    public function clear(int $userId): int;

    /**
     * @param array<int, int> $productIds
     * @return int number actually added
     */
    public function addMissing(int $userId, array $productIds): int;
}