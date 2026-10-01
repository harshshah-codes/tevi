<?php
declare(strict_types=1);

namespace App\Domain\Models;

final class Order
{
    /**
     * @param array<int, array{product_id:?int, product_name:string, size:string, color:string, price:int, qty:int, image:string}> $items
     */
    public function __construct(
        private readonly int $id,
        private readonly string $orderId,
        private readonly int $userId, // <-- added
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly string $email,
        private readonly string $phone,
        private readonly string $address,
        private readonly string $city,
        private readonly string $state,
        private readonly string $pincode,
        private readonly string $paymentMethod,
        private readonly int $subtotal,
        private readonly int $shipping,
        private readonly int $tax,
        private readonly int $total,
        private readonly string $status,
        private readonly string $createdAt,
        private readonly array $items = [],
    ) {
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function address(): string
    {
        return $this->address;
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

    public function paymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function subtotal(): int
    {
        return $this->subtotal;
    }

    public function shipping(): int
    {
        return $this->shipping;
    }

    public function tax(): int
    {
        return $this->tax;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    /**
     * @return array<int, array{product_id:?int, product_name:string, size:string, color:string, price:int, qty:int, image:string}>
     */
    public function items(): array
    {
        return $this->items;
    }
}
