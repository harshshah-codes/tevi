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
     */
    public function updateStatusByAdmin(int $id, string $expectedStatus, string $newStatus, ?string $note = null): bool;

    /**
     * Append an entry to an order's status history.
     *
     * @param string $changedBy username, e.g. "admin"
     */
    public function recordStatusChange(int $orderId, string $from, string $to, ?string $note = null, string $changedBy = 'admin'): void;

    /**
     * Remember the gateway order id we opened for this order.
     */
    public function attachRazorpayOrder(int $orderId, string $razorpayOrderId): bool;

    /**
     * Record the outcome of a payment. Marks paid_at when $paymentStatus is
     * 'paid'; that timestamp is the only record of when money arrived.
     */
    public function recordPayment(int $orderId, string $paymentStatus, ?string $razorpayPaymentId = null, ?string $signature = null): bool;

    public function findByRazorpayOrderId(string $razorpayOrderId): ?Order;

    /**
     * Store the shipment Shiprocket created for this order.
     *
     * Returns false when a shipment already exists, so a double click cannot
     * create two real shipments on the courier's side.
     */
    public function attachShipment(int $orderId, string $shipmentId, string $waybill, string $labelUrl, ?string $pickupToken = null): bool;

    /**
     * Log every delivery request, successful or not.
     */
    public function recordShipmentAttempt(int $orderId, ?string $shipmentId, ?string $waybill, ?string $labelUrl, ?string $pickupToken, string $status, ?string $error = null, bool $simulated = false): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function shipmentAttempts(int $orderId, int $limit = 20): array;

    public function recordCourierStatus(int $orderId, string $status, bool $delivered = false, ?string $awb = null): bool;

    public function findByShiprocketShipmentId(string $shipmentId): ?Order;

    public function findByWaybill(string $waybill): ?Order;

    /**
     * Cancel an order, but only if it is still in $expectedStatus.
     *
     * The status check lives in the WHERE clause so two concurrent cancel
     * clicks cannot both "win": the loser gets rowCount() === 0.
     */
    public function cancelForUser(int $id, int $userId, string $expectedStatus, ?string $reason): bool;
}
