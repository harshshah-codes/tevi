<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\Order;
use App\Domain\Repositories\OrderRepositoryInterface;

final class OrderService
{
    /**
     * Allowed admin status transitions. An order can never jump straight from
     * `processing` to `delivered`, and a cancelled order has to be explicitly
     * reinstated before it can move again.
     *
     * @var array<string, string[]>
     */
    private const ADMIN_TRANSITIONS = [
        'processing' => ['shipped', 'cancelled'],
        'shipped'    => ['delivered', 'cancelled'],
        'delivered'  => [],
        'cancelled'  => ['processing'],
    ];

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

    /**
     * @return Order[]
     */
    public function getOrdersForUser(int $userId, int $limit = 50): array
    {
        return $this->repository->findByUser($userId, $limit);
    }

    public function getOrderForUser(int $userId, string $orderId): ?Order
    {
        return $this->repository->findByUserAndOrderId($userId, $orderId);
    }

    public function updateStatusForUser(int $id, int $userId, string $status): bool
    {
        return $this->repository->updateStatusForUser($id, $userId, $status);
    }

    /**
     * Cancel an order, guarded on its current status.
     *
     * @param string $expectedStatus the status the order must currently be in
     * @return bool false when it was not
     */
    public function cancelOrderForUser(int $id, int $userId, string $expectedStatus, ?string $reason = null): bool
    {
        return $this->repository->cancelForUser($id, $userId, $expectedStatus, $reason);
    }

    /**
     * Whether a customer may still cancel this order themselves.
     */
    public function isCancellable(Order $order): bool
    {
        return in_array($order->status(), ['processing'], true);
    }

    /**
     * Statuses an admin may move an order to, given where it is now.
     *
     * @return string[]
     */
    public function adminTransitionsFrom(string $currentStatus): array
    {
        return self::ADMIN_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Change an order's status from the admin panel.
     *
     * @param string $expectedStatus status the order must currently be in
     * @param string $changedBy admin username for the audit trail
     * @return bool false when the order was not in that status
     */
    public function adminUpdateStatus(int $id, string $expectedStatus, string $newStatus, ?string $note = null, string $changedBy = 'admin'): bool
    {
        $updated = $this->repository->updateStatusByAdmin($id, $expectedStatus, $newStatus, $note);

        if ($updated) {
            $this->repository->recordStatusChange($id, $expectedStatus, $newStatus, $note, $changedBy);
        }

        return $updated;
    }

    /**
     * @return array<int, array{from_status: string, to_status: string, note: string|null, changed_by: string, created_at: string}>
     */
    public function statusHistory(int $orderId): array
    {
        return $this->repository->statusHistoryFor($orderId);
    }

    /**
     * Shape an order for the JSON API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Order $order): array
    {
        return [
            'id'        => $order->id(),
            'orderId'   => $order->orderId(),
            'firstName' => $order->firstName(),
            'lastName'  => $order->lastName(),
            'email'     => $order->email(),
            'phone'     => $order->phone(),
            'address'   => $order->address(),
            'city'      => $order->city(),
            'state'     => $order->state(),
            'pincode'   => $order->pincode(),
            'payment'   => $order->paymentMethod(),
            'subtotal'  => $order->subtotal(),
            'shipping'  => $order->shipping(),
            'tax'       => $order->tax(),
            'total'     => $order->total(),
            'status'    => $order->status(),
            'cancelledAt' => $order->cancelledAt(),
            'cancelReason' => $order->cancelReason(),
            'canCancel' => $this->isCancellable($order),
            'date'      => $order->createdAt(),
            'items'     => $order->items(),
        ];
    }
}
