<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\Order;

interface OrderRepositoryInterface
{
    public function create(Order $order): int;

    public function findById(int $id): ?Order;

    public function findByOrderId(string $orderId): ?Order;

    /**
     * @return Order[]
     */
    public function findAll(int $limit = 100): array;

    public function updateStatus(int $id, string $status): bool;
}
