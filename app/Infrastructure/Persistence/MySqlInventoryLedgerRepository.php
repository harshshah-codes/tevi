<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Models\InventoryEntry;
use App\Domain\Repositories\InventoryLedgerRepositoryInterface;
use PDO;

final class MySqlInventoryLedgerRepository implements InventoryLedgerRepositoryInterface
{
    private const SELECT = 'SELECT l.id, l.variant_id, l.order_id, l.entry_type, l.quantity_delta,
                                   l.balance_after, l.note, l.created_at,
                                   u.email AS created_by,
                                   v.colour, v.size
                              FROM inventory_ledger l
                         LEFT JOIN users u ON u.id = l.created_by
                         LEFT JOIN product_variants v ON v.id = l.variant_id';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function historyForVariant(int $variantId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . ' WHERE l.variant_id = :variant_id ORDER BY l.id DESC LIMIT :lim'
        );
        $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function historyForProduct(int $productId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . ' WHERE v.product_id = :product_id ORDER BY l.id DESC LIMIT :lim'
        );
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function balanceForVariant(int $variantId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantity_delta), 0) FROM inventory_ledger WHERE variant_id = :variant_id'
        );
        $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function append(
        int $variantId,
        string $entryType,
        int $quantityDelta,
        int $balanceAfter,
        ?int $orderId = null,
        ?string $note = null,
        ?int $createdBy = null
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventory_ledger
                (variant_id, order_id, entry_type, quantity_delta, balance_after, note, created_by)
             VALUES (:variant_id, :order_id, :entry_type, :quantity_delta, :balance_after, :note, :created_by)'
        );
        $stmt->execute([
            ':variant_id'     => $variantId,
            ':order_id'       => $orderId,
            ':entry_type'     => $entryType,
            ':quantity_delta' => $quantityDelta,
            ':balance_after'  => $balanceAfter,
            ':note'           => $note,
            ':created_by'     => $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function entriesForOrder(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT variant_id, quantity_delta, entry_type
               FROM inventory_ledger
              WHERE order_id = :order_id AND entry_type = :type'
        );
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':type', InventoryEntry::SALE);
        $stmt->execute();

        return array_map(static fn(array $r): array => [
            'variant_id' => (int) $r['variant_id'],
            'quantity'   => (int) $r['quantity_delta'],
            'entry_type' => (string) $r['entry_type'],
        ], $stmt->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): InventoryEntry
    {
        $label = isset($row['colour'], $row['size'])
            ? trim(implode(' / ', array_filter([$row['colour'], $row['size']])))
            : null;

        return new InventoryEntry(
            (int) $row['id'],
            (int) $row['variant_id'],
            $row['order_id'] !== null ? (int) $row['order_id'] : null,
            (string) $row['entry_type'],
            (int) $row['quantity_delta'],
            (int) $row['balance_after'],
            $row['note'] !== null ? (string) $row['note'] : null,
            $row['created_by'] !== null ? (string) $row['created_by'] : null,
            (string) $row['created_at'],
            $label,
        );
    }
}
