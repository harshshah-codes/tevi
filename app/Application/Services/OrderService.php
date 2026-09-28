<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\Order;
use App\Domain\Repositories\OrderRepositoryInterface;

final class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
    ) {
    }

    public function createOrder(Order $order): int
    {
        return $this->repository->create($order);
    }

    public function getOrder(int $id): ?Order
    {
        return $this->repository->findById($id);
    }

    public function getOrderByOrderId(string $orderId): ?Order
    {
        return $this->repository->findByOrderId($orderId);
    }

    /**
     * @return Order[]
     */
    public function getAllOrders(int $limit = 100): array
    {
        return $this->repository->findAll($limit);
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->repository->updateStatus($id, $status);
    }
}
