<?php
declare(strict_types=1);

namespace App\Domain\Models;

/**
 * One immutable line of the inventory ledger.
 *
 * Stock is never edited: a change appends a signed movement, and
 * balance_after records the stock at that moment so a single row can be read
 * on its own.
 */
final class InventoryEntry
{
    public const OPENING = 'opening';
    public const RECEIPT = 'receipt';
    public const SALE = 'sale';
    public const CANCELLATION = 'cancellation';
    public const RETURN = 'return';
    public const CORRECTION = 'correction';
    public const WRITE_OFF = 'write_off';

    public function __construct(
        private readonly int $id,
        private readonly int $variantId,
        private readonly ?int $orderId,
        private readonly string $entryType,
        private readonly int $quantityDelta,
        private readonly int $balanceAfter,
        private readonly ?string $note,
        private readonly ?string $createdBy,
        private readonly string $createdAt,
        private readonly ?string $variantLabel = null,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function variantId(): int
    {
        return $this->variantId;
    }

    public function orderId(): ?int
    {
        return $this->orderId;
    }

    public function entryType(): string
    {
        return $this->entryType;
    }

    public function quantityDelta(): int
    {
        return $this->quantityDelta;
    }

    public function balanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function createdBy(): ?string
    {
        return $this->createdBy;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function variantLabel(): ?string
    {
        return $this->variantLabel;
    }

    public function isPositive(): bool
    {
        return $this->quantityDelta > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'variantId'     => $this->variantId,
            'variantLabel'  => $this->variantLabel,
            'orderId'       => $this->orderId,
            'entryType'     => $this->entryType,
            'quantityDelta' => $this->quantityDelta,
            'balanceAfter'  => $this->balanceAfter,
            'note'          => $this->note,
            'createdBy'     => $this->createdBy,
            'createdAt'     => $this->createdAt,
        ];
    }
}
