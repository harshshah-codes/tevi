<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\InventoryEntry;

interface InventoryLedgerRepositoryInterface
{
    /**
     * @return InventoryEntry[]
     */
    public function historyForVariant(int $variantId, int $limit = 50): array;

    /**
     * @return InventoryEntry[]
     */
    public function historyForProduct(int $productId, int $limit = 100): array;

    /**
     * The ledger-derived balance, ignoring the denormalised stock column.
     */
    public function balanceForVariant(int $variantId): int;

    public function append(
        int $variantId,
        string $entryType,
        int $quantityDelta,
        int $balanceAfter,
        ?int $orderId = null,
        ?string $note = null,
        ?int $createdBy = null
    ): int;

    /**
     * Ledger entries for an order, used to reverse them on cancellation.
     *
     * @return array<int, array{variant_id:int, quantity:int, entry_type:string}>
     */
    public function entriesForOrder(int $orderId): array;
}
