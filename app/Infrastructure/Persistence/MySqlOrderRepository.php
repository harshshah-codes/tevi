<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Models\Order;
use App\Domain\Repositories\OrderRepositoryInterface;
use PDO;

final class MySqlOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(Order $order): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO orders (order_id, user_id, first_name, last_name, email, phone, address, city, state, pincode,
                                     payment_method, subtotal, shipping, tax, total, status)
                 VALUES (:order_id, :user_id, :first_name, :last_name, :email, :phone, :address, :city, :state, :pincode,
                         :payment_method, :subtotal, :shipping, :tax, :total, :status)'
            );
            $stmt->execute([
                ':order_id'       => $order->orderId(),
                ':user_id'        => $order->userId(),
                ':first_name'     => $order->firstName(),
                ':last_name'      => $order->lastName(),
                ':email'          => $order->email(),
                ':phone'          => $order->phone(),
                ':address'        => $order->address(),
                ':city'           => $order->city(),
                ':state'          => $order->state(),
                ':pincode'        => $order->pincode(),
                ':payment_method' => $order->paymentMethod(),
                ':subtotal'       => $order->subtotal(),
                ':shipping'       => $order->shipping(),
                ':tax'            => $order->tax(),
                ':total'          => $order->total(),
                ':status'         => $order->status(),
            ]);

            $orderId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, size, color, price, qty, image, weight)
                 VALUES (:order_id, :product_id, :product_name, :size, :color, :price, :qty, :image, :weight)'
            );

            foreach ($order->items() as $item) {
                $itemStmt->execute([
                    ':order_id'     => $orderId,
                    ':product_id'   => $item['product_id'],
                    ':product_name' => $item['product_name'],
                    ':size'         => $item['size'],
                    ':color'        => $item['color'],
                    ':price'        => $item['price'],
                    ':qty'          => $item['qty'],
                    ':image'        => $item['image'],
                    ':weight'       => $item['weight'] ?? 0.5,
                ]);
            }

            $this->pdo->commit();

            return $orderId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->mapOrder($row);
    }

    public function findByOrderId(string $orderId): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE order_id = :order_id LIMIT 1');
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->mapOrder($row);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $result = $stmt->execute([
            ':id'     => $id,
            ':status' => $status,
        ]);

        return $result && $stmt->rowCount() > 0;
    }

    /**
     * @return Order[]
     */
    public function findAll(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $orders = [];
        foreach ($stmt->fetchAll() as $row) {
            $orders[] = $this->mapOrder($row);
        }

        return $orders;
    }

    /**
     * @return Order[]
     */
    public function findByUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT :lim'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $orders = [];
        foreach ($stmt->fetchAll() as $row) {
            $orders[] = $this->mapOrder($row);
        }

        return $orders;
    }

    public function findByUserAndOrderId(int $userId, string $orderId): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE user_id = :user_id AND order_id = :order_id LIMIT 1');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->mapOrder($row);
    }

    public function updateStatusForUser(int $id, int $userId, string $status): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders SET status = :status WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            ':id'      => $id,
            ':user_id' => $userId,
            ':status'  => $status,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param string[] $allowedFrom
     */
    public function cancelForUser(int $id, int $userId, string $expectedStatus, ?string $reason): bool
    {
        if ($expectedStatus === '') {
            return false;
        }

        // Guarding on the *current* status inside the UPDATE makes the
        // transition atomic: only the first request to cancel changes a row.
        $stmt = $this->pdo->prepare(
            'UPDATE orders
                SET status = :new_status,
                    cancelled_at = CURRENT_TIMESTAMP,
                    cancel_reason = :reason
              WHERE id = :id
                AND user_id = :user_id
                AND status = :expected_status'
        );
        $stmt->execute([
            ':id'              => $id,
            ':user_id'         => $userId,
            ':new_status'      => 'cancelled',
            ':reason'          => $reason,
            ':expected_status' => $expectedStatus,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateStatusByAdmin(int $id, string $expectedStatus, string $newStatus, ?string $note = null): bool
    {
        if ($expectedStatus === '' || $newStatus === '') {
            return false;
        }

        // Guarded on the current status, and moving away from `cancelled`
        // clears the cancellation columns so they always describe the
        // present; the durable record lives in order_status_history.
        $stmt = $this->pdo->prepare(
            'UPDATE orders
                SET status = :new_status,
                    cancelled_at = CASE WHEN :new_status2 = \'cancelled\' THEN CURRENT_TIMESTAMP ELSE NULL END,
                    cancel_reason = CASE WHEN :new_status3 = \'cancelled\' THEN :note ELSE NULL END
              WHERE id = :id
                AND status = :expected_status'
        );
        $stmt->execute([
            ':id'              => $id,
            ':new_status'      => $newStatus,
            ':new_status2'     => $newStatus,
            ':new_status3'     => $newStatus,
            ':note'            => $note,
            ':expected_status' => $expectedStatus,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function recordStatusChange(int $orderId, string $from, string $to, ?string $note = null, string $changedBy = 'admin'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_status_history (order_id, from_status, to_status, note, changed_by)
             VALUES (:order_id, :from_status, :to_status, :note, :changed_by)'
        );
        $stmt->execute([
            ':order_id'    => $orderId,
            ':from_status' => $from,
            ':to_status'   => $to,
            ':note'        => $note,
            ':changed_by'  => $changedBy !== '' ? $changedBy : 'admin',
        ]);
    }

    /**
     * @return array<int, array{from_status: string, to_status: string, note: string|null, changed_by: string, created_at: string}>
     */
    public function statusHistoryFor(int $orderId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT from_status, to_status, note, changed_by, created_at
               FROM order_status_history
              WHERE order_id = :order_id
              ORDER BY id DESC
              LIMIT :lim'
        );
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $history = [];
        foreach ($stmt->fetchAll() as $row) {
            $history[] = [
                'from_status' => (string) $row['from_status'],
                'to_status'   => (string) $row['to_status'],
                'note'        => $row['note'] !== null ? (string) $row['note'] : null,
                'changed_by'  => (string) $row['changed_by'],
                'created_at'  => (string) $row['created_at'],
            ];
        }

        return $history;
    }

    public function findByRazorpayOrderId(string $razorpayOrderId): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE razorpay_order_id = :rzp_id LIMIT 1');
        $stmt->execute([':rzp_id' => $razorpayOrderId]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->mapOrder($row);
    }

    public function attachRazorpayOrder(int $orderId, string $razorpayOrderId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders SET razorpay_order_id = :rzp_id WHERE id = :id AND razorpay_order_id IS NULL'
        );
        $stmt->execute([
            ':rzp_id' => $razorpayOrderId,
            ':id'     => $orderId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function recordPayment(int $orderId, string $paymentStatus, ?string $razorpayPaymentId = null, ?string $signature = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders
                SET payment_status = :payment_status,
                    razorpay_payment_id = :payment_id,
                    razorpay_signature = :signature,
                    paid_at = CASE WHEN :paid_flag = \'paid\' THEN CURRENT_TIMESTAMP ELSE paid_at END
              WHERE id = :id'
        );
        $stmt->execute([
            ':payment_status' => $paymentStatus,
            ':payment_id'     => $razorpayPaymentId,
            ':signature'      => $signature,
            ':paid_flag'      => $paymentStatus,
            ':id'             => $orderId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function findByShiprocketShipmentId(string $shipmentId): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE shiprocket_shipment_id = :shipment_id LIMIT 1');
        $stmt->execute([':shipment_id' => $shipmentId]);

        $row = $stmt->fetch();

        return $row ? $this->mapOrder($row) : null;
    }

    public function findByWaybill(string $waybill): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE shiprocket_waybill = :waybill LIMIT 1');
        $stmt->execute([':waybill' => $waybill]);

        $row = $stmt->fetch();

        return $row ? $this->mapOrder($row) : null;
    }

    /**
     * Store the latest courier status reported by a Shiprocket webhook.
     *
     * This is an observation, not a command: it must succeed even when the
     * order is already delivered or cancelled, which is why it deliberately
     * does not reuse the guarded status transition.
     */
    public function recordCourierStatus(int $orderId, string $status, bool $delivered = false, ?string $awb = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders
                SET shiprocket_last_status = :status,
                    shiprocket_last_update = CURRENT_TIMESTAMP,
                    delivered_at = CASE WHEN :delivered = 1 THEN COALESCE(delivered_at, CURRENT_TIMESTAMP) ELSE delivered_at END,
                    shiprocket_waybill = COALESCE(:awb, shiprocket_waybill)
              WHERE id = :id'
        );
        $stmt->execute([
            ':status'    => $status,
            ':delivered' => $delivered ? 1 : 0,
            ':awb'       => $awb,
            ':id'        => $orderId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function attachShipment(int $orderId, string $shipmentId, string $waybill, string $labelUrl, ?string $pickupToken = null): bool
    {
        // The `shiprocket_shipment_id IS NULL` guard is what makes this
        // idempotent under a double click or a retried request.
        $stmt = $this->pdo->prepare(
            'UPDATE orders
                SET shiprocket_shipment_id = :shipment_id,
                    shiprocket_waybill = :waybill,
                    shiprocket_label_url = :label_url,
                    shiprocket_pickup_token = :pickup_token,
                    shiprocket_requested_at = CURRENT_TIMESTAMP
              WHERE id = :id
                AND shiprocket_shipment_id IS NULL'
        );
        $stmt->execute([
            ':shipment_id'  => $shipmentId,
            ':waybill'      => $waybill,
            ':label_url'    => $labelUrl,
            ':pickup_token' => $pickupToken,
            ':id'           => $orderId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function recordShipmentAttempt(int $orderId, ?string $shipmentId, ?string $waybill, ?string $labelUrl, ?string $pickupToken, string $status, ?string $error = null, bool $simulated = false): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_shipments (order_id, shipment_id, waybill, label_url, pickup_token, shipment_status, error_message, simulated)
             VALUES (:order_id, :shipment_id, :waybill, :label_url, :pickup_token, :shipment_status, :error_message, :simulated)'
        );
        $stmt->execute([
            ':order_id'        => $orderId,
            ':shipment_id'     => $shipmentId,
            ':waybill'         => $waybill,
            ':label_url'       => $labelUrl,
            ':pickup_token'    => $pickupToken,
            ':shipment_status' => $status,
            ':error_message'   => $error,
            ':simulated'       => $simulated ? 1 : 0,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function shipmentAttempts(int $orderId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_shipments WHERE order_id = :order_id ORDER BY id DESC LIMIT :lim'
        );
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapOrder(array $row): Order
    {
        $stmt = $this->pdo->prepare(
            'SELECT product_id, product_name, size, color, price, qty, image, weight
               FROM order_items WHERE order_id = :order_id ORDER BY id ASC'
        );
        $stmt->bindValue(':order_id', (int) $row['id'], PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll() as $item) {
            $items[] = [
                'product_id'   => $item['product_id'] !== null ? (int) $item['product_id'] : null,
                'product_name' => (string) $item['product_name'],
                'size'         => (string) $item['size'],
                'color'        => (string) $item['color'],
                'price'        => (int) $item['price'],
                'qty'          => (int) $item['qty'],
                'image'        => (string) $item['image'],
                'weight'       => isset($item['weight']) ? (float) $item['weight'] : 0.5,
            ];
        }

        return new Order(
            (int) $row['id'],
            (string) $row['order_id'],
            (int) $row['user_id'], // <-- added
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['email'],
            (string) $row['phone'],
            (string) $row['address'],
            (string) $row['city'],
            (string) $row['state'],
            (string) $row['pincode'],
            (string) $row['payment_method'],
            (int) $row['subtotal'],
            (int) $row['shipping'],
            (int) $row['tax'],
            (int) $row['total'],
            (string) $row['status'],
            (string) $row['created_at'],
            $items,
            isset($row['cancelled_at']) && $row['cancelled_at'] !== null ? (string) $row['cancelled_at'] : null,
            isset($row['cancel_reason']) && $row['cancel_reason'] !== null ? (string) $row['cancel_reason'] : null,
            isset($row['payment_status']) && $row['payment_status'] !== null ? (string) $row['payment_status'] : 'pending',
            isset($row['shiprocket_waybill']) && $row['shiprocket_waybill'] !== null ? (string) $row['shiprocket_waybill'] : null,
            isset($row['shiprocket_label_url']) && $row['shiprocket_label_url'] !== null ? (string) $row['shiprocket_label_url'] : null,
            isset($row['shiprocket_shipment_id']) && $row['shiprocket_shipment_id'] !== null ? (string) $row['shiprocket_shipment_id'] : null,
            isset($row['shiprocket_pickup_token']) && $row['shiprocket_pickup_token'] !== null ? (string) $row['shiprocket_pickup_token'] : null,
            isset($row['shiprocket_requested_at']) && $row['shiprocket_requested_at'] !== null ? (string) $row['shiprocket_requested_at'] : null,
            isset($row['shiprocket_last_status']) && $row['shiprocket_last_status'] !== null ? (string) $row['shiprocket_last_status'] : null,
            isset($row['shiprocket_last_update']) && $row['shiprocket_last_update'] !== null ? (string) $row['shiprocket_last_update'] : null,
            isset($row['delivered_at']) && $row['delivered_at'] !== null ? (string) $row['delivered_at'] : null,
        );
    }
}
