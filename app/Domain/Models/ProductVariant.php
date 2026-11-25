<?php
declare(strict_types=1);

namespace App\Domain\Models;

/**
 * One sellable combination: a product in a colour and size.
 *
 * products.sizes / products.colors are JSON arrays that feed the picker;
 * this is the countable row. Stock lives here and the ledger explains it.
 */
final class ProductVariant
{
    public function __construct(
        private readonly int $id,
        private readonly int $productId,
        private readonly string $productName,
        private readonly string $colour,
        private readonly string $size,
        private readonly int $stock,
        private readonly int $lowStockThreshold,
        private readonly bool $isActive,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function productName(): string
    {
        return $this->productName;
    }

    public function colour(): string
    {
        return $this->colour;
    }

    public function size(): string
    {
        return $this->size;
    }

    public function stock(): int
    {
        return $this->stock;
    }

    public function lowStockThreshold(): int
    {
        return $this->lowStockThreshold;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isInStock(): bool
    {
        return $this->isActive && $this->stock > 0;
    }

    public function isLow(): bool
    {
        return $this->stock <= $this->lowStockThreshold;
    }

    /**
     * Stable label used by the admin UI and the ledger ("Sage / M").
     */
    public function label(): string
    {
        $parts = array_filter([$this->colour, $this->size]);

        return $parts === [] ? 'Default' : implode(' / ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'productId'        => $this->productId,
            'productName'      => $this->productName,
            'colour'           => $this->colour,
            'size'             => $this->size,
            'label'            => $this->label(),
            'stock'            => $this->stock,
            'lowStockThreshold' => $this->lowStockThreshold,
            'isActive'         => $this->isActive,
            'isInStock'        => $this->isInStock(),
            'isLow'            => $this->isLow(),
        ];
    }
}
