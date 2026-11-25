<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\InventoryEntry;
use App\Domain\Models\ProductVariant;
use App\Domain\Repositories\InventoryLedgerRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ProductVariantRepositoryInterface;
use PDO;
use RuntimeException;

/**
 * Stock, and the ledger that explains it.
 *
 * Stock is never edited in place: every change appends a signed row to
 * inventory_ledger and updates product_variants.stock inside the same
 * transaction, so the two can never disagree.
 */
final class InventoryService
{
    public function __construct(
        private readonly ProductVariantRepositoryInterface $variants,
        private readonly InventoryLedgerRepositoryInterface $ledger,
        private readonly ProductRepositoryInterface $products,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * The colour on an order line, whichever spelling it arrived in.
     *
     * The cart and order_items use `color`; anything written alongside the
     * variant model tends to use `colour`. Reading only one of them silently
     * matched the wrong variant, which reads as "out of stock" for every line.
     *
     * @param array<string, mixed> $item
     */
    private static function colourOf(array $item): string
    {
        return trim((string) ($item['color'] ?? $item['colour'] ?? ''));
    }

    /**
     * The key format used for bulk stock lookups: productId|colour|size.
     */
    private static function key(int $productId, string $colour, string $size): string
    {
        return $productId . '|' . mb_strtolower(trim($colour)) . '|' . mb_strtolower(trim($size));
    }

    /**
     * Make sure a product has a row for every colour x size it offers.
     *
     * Called when a product is saved, so adding a colour or size in the admin
     * creates the variant automatically rather than silently having no stock.
     *
     * @return int variants created
     */
    public function syncVariants(int $productId): int
    {
        $product = $this->products->findById($productId);
        if ($product === null) {
            return 0;
        }

        return $this->variants->syncFromProductOptions($productId, $product->sizes(), $product->colors());
    }

    /**
     * @return ProductVariant[]
     */
    public function variantsFor(int $productId): array
    {
        $this->syncVariants($productId);

        return $this->variants->findForProduct($productId);
    }

    /**
     * Record stock coming in: an opening balance or a later delivery.
     *
     * @return int the new balance
     */
    public function receive(
        int $variantId,
        int $quantity,
        string $entryType = InventoryEntry::RECEIPT,
        ?string $note = null,
        ?int $actorId = null
    ): int {
        if ($quantity === 0) {
            throw new RuntimeException('Quantity must not be zero');
        }

        $variant = $this->variants->findById($variantId);
        if ($variant === null) {
            throw new RuntimeException('Unknown variant');
        }

        return $this->postMovement($variantId, $quantity, $entryType, null, $note, $actorId);
    }

    /**
     * Set the stock level exactly, expressing the difference as a correction.
     *
     * This is what the admin's "set opening stock" does when a figure already
     * exists — an absolute set, recorded as a signed movement so the ledger
     * still explains how we got there.
     *
     * @return array{previous: int, now: int, delta: int}
     */
    public function setStock(int $variantId, int $absolute, ?string $note = null, ?int $actorId = null): array
    {
        $variant = $this->variants->findById($variantId);
        if ($variant === null) {
            throw new RuntimeException('Unknown variant');
        }

        if ($absolute < 0) {
            throw new RuntimeException('Stock cannot be negative');
        }

        $previous = $variant->stock();
        $delta = $absolute - $previous;

        if ($delta === 0) {
            return ['previous' => $previous, 'now' => $previous, 'delta' => 0];
        }

        $this->postMovement(
            $variantId,
            $delta,
            $delta > 0 ? InventoryEntry::OPENING : InventoryEntry::CORRECTION,
            null,
            $note ?? 'Stock set to ' . $absolute,
            $actorId
        );

        return ['previous' => $previous, 'now' => $absolute, 'delta' => $delta];
    }

    public function setLowStockThreshold(int $variantId, int $threshold): bool
    {
        return $this->variants->setLowStockThreshold($variantId, $threshold) > 0;
    }

    public function setActive(int $variantId, bool $active): bool
    {
        return $this->variants->setActive($variantId, $active) > 0;
    }

    /**
     * @return array<string, array{stock:int, inStock:bool, label:string}>
     */
    public function availabilityFor(int $productId): array
    {
        return $this->variants->availabilityForProduct($productId);
    }

    /**
     * Stock per "productId|colour|size", for the storefront to disable options.
     *
     * @param array<int, array{productId:int, colour?:string, size?:string}> $items
     * @return array<string, int>
     */
    public function availabilityForItems(array $items): array
    {
        $keys = [];
        foreach ($items as $item) {
            $keys[] = self::key(
                (int) $item['productId'],
                (string) ($item['colour'] ?? ''),
                (string) ($item['size'] ?? '')
            );
        }

        return $this->variants->stockForKeys(array_values(array_unique($keys)));
    }

    /**
     * Check an order's lines before it is placed.
     *
     * Blocking is the rule: if any line has no variant or not enough stock,
     * the order is refused with the detail the customer needs.
     *
     * @param array<int, array{product_id:?int, product_name:string, colour?:string, size:string, qty:int}> $items
     * @return array{ok: bool, problems: array<int, array{productName:string, colour:string, size:string, requested:int, available:int}>}
     */
    public function checkAvailability(array $items): array
    {
        $problems = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] !== null ? (int) $item['product_id'] : 0;
            $qty = max(1, (int) $item['qty']);
            $colour = self::colourOf($item);
            $size = (string) ($item['size'] ?? '');

            $variant = $productId > 0
                ? $this->variants->findByProductColourSize($productId, $colour, $size)
                : null;

            // A product nobody has given opening stock for cannot be sold
            // through inventory, and neither can an option that does not exist.
            $available = $variant !== null && $variant->isActive() ? $variant->stock() : 0;

            if ($available < $qty) {
                $problems[] = [
                    'productName' => (string) $item['product_name'],
                    'colour'      => $colour,
                    'size'        => $size,
                    'requested'   => $qty,
                    'available'   => $available,
                ];
            }
        }

        return ['ok' => $problems === [], 'problems' => $problems];
    }

    /**
     * Take stock for a placed order.
     *
     * Runs in one transaction and re-checks inside it, so two customers
     * checking out the last kurta cannot both succeed.
     *
     * @param array<int, array{product_id:?int, product_name:string, colour?:string, size:string, qty:int}> $items
     * @return int the order id the stock was committed against
     * @throws RuntimeException when stock ran out mid-transaction
     */
    public function commitForOrder(int $orderId, array $items, ?int $actorId = null): void
    {
        $this->begin();

        try {
            foreach ($items as $item) {
                $productId = $item['product_id'] !== null ? (int) $item['product_id'] : 0;
                $qty = max(1, (int) $item['qty']);
                $colour = self::colourOf($item);
                $size = (string) ($item['size'] ?? '');

                $variant = $productId > 0
                    ? $this->variants->findByProductColourSize($productId, $colour, $size)
                    : null;

                if ($variant === null) {
                    throw new RuntimeException(sprintf(
                        '%s in %s / %s is not in the inventory.',
                        (string) $item['product_name'],
                        $colour === '' ? 'no colour' : $colour,
                        $size === '' ? 'no size' : $size
                    ));
                }

                $this->take($variant, -$qty, $orderId, InventoryEntry::SALE, $actorId);
            }

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    /**
     * Put stock back when an order is cancelled.
     */
    public function releaseForOrder(int $orderId, ?int $actorId = null): int
    {
        $sales = $this->ledger->entriesForOrder($orderId);
        if ($sales === []) {
            return 0;
        }

        $this->begin();
        $released = 0;

        try {
            foreach ($sales as $sale) {
                $variant = $this->variants->findById($sale['variant_id']);
                if ($variant === null) {
                    continue;
                }

                $this->take($variant, -$sale['quantity'], $orderId, InventoryEntry::CANCELLATION, $actorId);
                $released++;
            }

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }

        return $released;
    }

    /**
     * @return InventoryEntry[]
     */
    public function historyForProduct(int $productId, int $limit = 100): array
    {
        $this->syncVariants($productId);

        return $this->ledger->historyForProduct($productId, $limit);
    }

    /**
     * @return InventoryEntry[]
     */
    public function historyForVariant(int $variantId, int $limit = 50): array
    {
        return $this->ledger->historyForVariant($variantId, $limit);
    }

    /**
     * Products with at least one variant out of stock or running low.
     *
     * @return array<int, array{productId:int, name:string, outOfStock:int, low:int, total:int}>
     */
    public function lowStockReport(int $threshold = 0): array
    {
        $statement = $this->pdo->query(
            'SELECT p.id, p.name,
                    SUM(CASE WHEN v.is_active = 1 AND v.stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
                    SUM(CASE WHEN v.is_active = 1 AND v.stock > 0 AND v.stock <= v.low_stock_threshold THEN 1 ELSE 0 END) AS low,
                    SUM(CASE WHEN v.is_active = 1 THEN 1 ELSE 0 END) AS total
               FROM product_variants v
               JOIN products p ON p.id = v.product_id
           GROUP BY p.id, p.name
             HAVING out_of_stock > 0 OR low > 0
           ORDER BY out_of_stock DESC, low DESC, p.name ASC'
        );

        $report = [];
        foreach ($statement->fetchAll() as $row) {
            $report[] = [
                'productId'   => (int) $row['id'],
                'name'        => (string) $row['name'],
                'outOfStock'  => (int) $row['out_of_stock'],
                'low'         => (int) $row['low'],
                'total'       => (int) $row['total'],
            ];
        }

        return $report;
    }

    /**
     * Append a movement and keep product_variants.stock in step.
     */
    private function postMovement(
        int $variantId,
        int $quantity,
        string $entryType,
        ?int $orderId,
        ?string $note,
        ?int $actorId
    ): int {
        $balance = $quantity + $this->variants->findById($variantId)?->stock() ?? 0;

        if ($balance < 0) {
            throw new RuntimeException('That would take stock below zero');
        }

        $this->applyStock($variantId, $balance);
        $this->ledger->append($variantId, $entryType, $quantity, $balance, $orderId, $note, $actorId);

        return $balance;
    }

    private function take(ProductVariant $variant, int $delta, ?int $orderId, string $entryType, ?int $actorId): void
    {
        $balance = $variant->stock() + $delta;

        if ($balance < 0) {
            throw new RuntimeException(sprintf(
                'Only %d left of %s (%s / %s).',
                $variant->stock(),
                $variant->productName(),
                $variant->colour() === '' ? 'no colour' : $variant->colour(),
                $variant->size() === '' ? 'no size' : $variant->size()
            ));
        }

        $this->applyStock($variant->id(), $balance);
        $this->ledger->append(
            $variant->id(),
            $entryType,
            $delta,
            $balance,
            $orderId,
            $entryType === InventoryEntry::SALE ? 'Order placed' : 'Order cancelled',
            $actorId
        );
    }

    private function applyStock(int $variantId, int $balance): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE product_variants SET stock = :stock WHERE id = :id'
        );
        $statement->execute([':stock' => $balance, ':id' => $variantId]);
    }

    private function begin(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    private function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    private function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}

