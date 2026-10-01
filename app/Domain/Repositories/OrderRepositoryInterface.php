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

    /**
     * Orders belonging to one customer, newest first.
     *
     * @return Order[]
     */
    public function findByUser(int $userId, int $limit = 50): array;

    public function findByUserAndOrderId(int $userId, string $orderId): ?Order;

    public function updateStatus(int $id, string $status): bool;

    public function updateStatusForUser(int $id, int $userId, string $status): bool;

    /**
     * Admin status change, guarded on the order's current status.
     *
     * Reinstating a cancelled order clears cancelled_at / cancel_reason, so
     * those columns always describe the *current* state rather than history.
     *
     * @param string[] $allowedFrom
     */
    public function updateStatusByAdmin(int $id, array $allowedFrom, string $status, ?string $note = null): bool;

    /**
     * Append an entry to an order's status history.
     *
     * @param string $changedBy username, e.g. "admin"
     */
    public function recordStatusChange(int $orderId, string $from, string $to, ?string $note = null, string $changedBy = 'admin'): void;

    /**
     * Cancel an order, but only if it is still in one of $allowedFrom statuses.
     *
     * The status check lives in the WHERE clause so two concurrent cancel
     * clicks cannot both "win": the loser gets rowCount() === 0.
     *
     * @param string[] $allowedFrom
     */
    public function cancelForUser(int $id, int $userId, array $allowedFrom, ?string $reason): bool;
}
