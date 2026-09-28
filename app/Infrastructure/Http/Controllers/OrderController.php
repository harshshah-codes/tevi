<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\OrderService;
use App\Domain\Models\Order;

final class OrderController
{
    public function __construct(
        private readonly OrderService $service,
    ) {
    }

    public function store(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_end_clean();
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);

        if (!is_array($data)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
            exit;
        }

        $required = ['orderId', 'firstName', 'lastName', 'email', 'phone', 'address', 'city', 'state', 'pincode', 'items'];
        foreach ($required as $field) {
            if (empty($data[$field]) && $data[$field] !== '0') {
                ob_end_clean();
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Missing field: ' . $field]);
                exit;
            }
        }

        if (!is_array($data['items']) || $data['items'] === []) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Order must contain at least one item']);
            exit;
        }

        $items = [];
        foreach ($data['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'product_id'   => isset($item['productId']) ? (int) $item['productId'] : null,
                'product_name' => (string) ($item['name'] ?? ''),
                'size'         => (string) ($item['size'] ?? ''),
                'color'        => (string) ($item['color'] ?? ''),
                'price'        => (int) ($item['price'] ?? 0),
                'qty'          => max(1, (int) ($item['qty'] ?? 1)),
                'image'        => (string) ($item['image'] ?? ''),
            ];
        }

        if ($items === []) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'No valid items in order']);
            exit;
        }

        $order = new Order(
            0,
            (string) $data['orderId'],
            trim((string) $data['firstName']),
            trim((string) $data['lastName']),
            trim((string) $data['email']),
            trim((string) $data['phone']),
            trim((string) $data['address']),
            trim((string) $data['city']),
            trim((string) $data['state']),
            trim((string) $data['pincode']),
            (string) ($data['payment'] ?? 'cod'),
            (int) ($data['subtotal'] ?? 0),
            (int) ($data['shipping'] ?? 0),
            (int) ($data['tax'] ?? 0),
            (int) ($data['total'] ?? 0),
            'processing',
            date('Y-m-d H:i:s'),
            $items,
        );

        try {
            $id = $this->service->createOrder($order);
        } catch (\Throwable $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save order: ' . $e->getMessage()]);
            exit;
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'id'      => $id,
            'orderId' => $order->orderId(),
        ]);
        exit;
    }
}
