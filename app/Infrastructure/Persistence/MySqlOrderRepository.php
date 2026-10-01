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
                'INSERT INTO order_items (order_id, product_id, product_name, size, color, price, qty, image)
                 VALUES (:order_id, :product_id, :product_name, :size, :color, :price, :qty, :image)'
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
    public function cancelForUser(int $id, int $userId, array $allowedFrom, ?string $reason): bool
    {
        if ($allowedFrom === []) {
            return false;
        }

        $placeholders = [];
        $params = [
            ':id'         => $id,
            ':user_id'    => $userId,
            ':status'     => 'cancelled',
            ':reason'     => $reason,
        ];

        foreach (array_values($allowedFrom) as $i => $from) {
            $key = ':from' . $i;
            $placeholders[] = $key;
            $params[$key] = $from;
        }

        // Guarding on the *current* status inside the UPDATE makes the
        // transition atomic: only the first request to cancel changes a row.
        $stmt = $this->pdo->prepare(
            'UPDATE orders
                SET status = :status,
                    cancelled_at = CURRENT_TIMESTAMP,
                    cancel_reason = :reason
              WHERE id = :id
                AND user_id = :user_id
                AND status IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param string[] $allowedFrom
     */
    public function updateStatusByAdmin(int $id, array $allowedFrom, string $status, ?string $note = null): bool
    {
        if ($allowedFrom === []) {
            return false;
        }

        $placeholders = [];
        $params = [
            ':id'      => $id,
            ':status'  => $status,
            ':note'    => $note,
        ];

        foreach (array_values($allowedFrom) as $i => $from) {
            $key = ':from' . $i;
            $placeholders[] = $key;
            $params[$key] = $from;
        }

        // Leaving `cancelled` clears the cancellation columns so they always
        // describe the order's current state, not its history.
        $stmt = $this->pdo->prepare(
            'UPDATE orders
                SET status = :status,
                    cancelled_at = CASE WHEN :status2 = \'cancelled\' THEN CURRENT_TIMESTAMP ELSE NULL END,
                    cancel_reason = CASE WHEN :status3 = \'cancelled\' THEN :note ELSE NULL END
              WHERE id = :id
                AND status IN (' . implode(', ', $placeholders) . ')'
        );
        $params[':status2'] = $status;
        $params[':status3'] = $status;
        $stmt->execute($params);

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

    /**
     * @param array<string, mixed> $row
     */
    private function mapOrder(array $row): Order
    {
        $stmt = $this->pdo->prepare(
            'SELECT product_id, product_name, size, color, price, qty, image
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
        );
    }
}
