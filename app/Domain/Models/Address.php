<?php
declare(strict_types=1);

namespace App\Domain\Models;

final class Address
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly string $label,
        private readonly string $recipientName,
        private readonly string $phone,
        private readonly string $line1,
        private readonly ?string $line2,
        private readonly string $city,
        private readonly string $state,
        private readonly string $pincode,
        private readonly bool $isDefault,
        private readonly string $createdAt,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function recipientName(): string
    {
        return $this->recipientName;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function line1(): string
    {
        return $this->line1;
    }

    public function line2(): ?string
    {
        return $this->line2;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function pincode(): string
    {
        return $this->pincode;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    /**
     * Single-line rendering, used by checkout prefill and order history.
     */
    public function oneLine(): string
    {
        $parts = array_filter([$this->line1, $this->line2, $this->city, $this->state, $this->pincode]);

        return implode(', ', $parts);
    }

    /**
     * @return array{id: int, label: string, recipientName: string, phone: string, line1: string, line2: string|null, city: string, state: string, pincode: string, isDefault: bool, oneLine: string}
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'label'        => $this->label,
            'recipientName' => $this->recipientName,
            'phone'        => $this->phone,
            'line1'        => $this->line1,
            'line2'        => $this->line2,
            'city'         => $this->city,
            'state'        => $this->state,
            'pincode'      => $this->pincode,
            'isDefault'    => $this->isDefault,
            'oneLine'      => $this->oneLine(),
        ];
    }
}
