<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\OrderService;
use App\Application\Services\PricingService;
use App\Domain\Models\Order;

final class OrderController
{
    /**
     * Statuses a customer may move an order to themselves, and the statuses
     * each transition is allowed from.
     *
     * processing -> cancelled            (customer, before it ships)
     * cancelled -> processing            (admin reinstatement)
     * processing|packed|shipped -> ...   (admin, via updateStatus)
     */
    private const CUSTOMER_TRANSITIONS = [
        'processing' => ['cancelled'],
        'cancelled'  => ['processing'],
    ];

    /** A customer may only cancel; reinstatement is an admin action. */
    private const CUSTOMER_TARGETS = ['cancelled'];

    public function __construct(
        private readonly OrderService $service,
        private readonly PricingService $pricing,
    ) {
    }

    /**
     * GET /api/orders — the signed-in customer's order history.
     */
    public function index(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (empty($_SESSION['user_id'])) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            exit;
        }

        $orders = $this->service->getOrdersForUser((int) $_SESSION['user_id']);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'orders'  => array_map(fn(Order $o) => $this->service->toArray($o), $orders),
        ]);
        exit;
    }

    /**
     * GET /api/orders/{orderId}
     */
    public function show(string $orderId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (empty($_SESSION['user_id'])) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            exit;
        }

        $order = $this->service->getOrderForUser((int) $_SESSION['user_id'], $orderId);
        if ($order === null) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            exit;
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'order' => $this->service->toArray($order)]);
        exit;
    }

    /**
     * POST /api/orders/{orderId}/cancel
     *
     * Body: { "reason": "..." } (optional). Idempotent: cancelling an already
     * cancelled order succeeds and reports the existing status.
     */
    public function cancel(string $orderId): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_end_clean();
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        if (empty($_SESSION['user_id'])) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            exit;
        }

        $order = $this->service->getOrderForUser((int) $_SESSION['user_id'], $orderId);
        if ($order === null) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            exit;
        }

        // Already cancelled: nothing to do, do not fail the click.
        if ($order->status() === 'cancelled') {
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'orderId' => $order->orderId(),
                'status'  => 'cancelled',
                'message' => 'Order was already cancelled.',
            ]);
            exit;
        }

        $allowedFrom = self::CUSTOMER_TRANSITIONS['cancelled'] ?? [];
        if (!in_array($order->status(), $allowedFrom, true)) {            ob_end_clean();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error'   => 'This order can no longer be cancelled',
                'status'  => $order->status(),
            ]);
            exit;
        }

        $reason = $this->cancelReason();

        $updated = $this->service->cancelOrderForUser(
            $order->id(),
            (int) $_SESSION['user_id'],
            $order->status(),
            $reason
        );

        if (!$updated) {
            // Lost a race with a concurrent status change; report current state.
            $fresh = $this->service->getOrderForUser((int) $_SESSION['user_id'], $orderId);
            ob_end_clean();
            http_response_code($fresh && $fresh->status() === 'cancelled' ? 200 : 409);
            echo json_encode([
                'success' => $fresh !== null && $fresh->status() === 'cancelled',
                'error'   => 'Could not cancel this order',
                'status'  => $fresh?->status(),
            ]);
            exit;
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'orderId' => $order->orderId(),
            'status'  => 'cancelled',
            'reason'  => $reason,
            'refund'  => $order->paymentMethod() === 'cod'
                ? 'No payment was taken, so there is nothing to refund.'
                : 'Refund initiated to the original payment method in 5-7 working days.',
        ]);
        exit;
    }

    /**
     * @return string
     */
    private function cancelReason(): string
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        $reason = is_array($data) ? trim((string) ($data['reason'] ?? '')) : '';

        return mb_substr($reason, 0, 255);
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

        // Check if user is logged in
        if (empty($_SESSION['user_id'])) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not logged in']);
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

        $required = ['orderId', 'firstName', 'lastName', 'email', 'phone', 'address', 'city', 'state', 'pincode', 'items'];        foreach ($required as $field) {
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

        // Money is recomputed from catalog prices; the payload's subtotal,
        // shipping, tax and total are deliberately ignored.
        $paymentMethod = (string) ($data['payment'] ?? 'cod');
        $quote = $this->pricing->quote($items, $paymentMethod);

        if ($quote['items'] === []) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error'   => 'None of the items in this order exist in the catalog',
            ]);
            exit;
        }

        $order = new Order(
            0,
            (string) $data['orderId'],
            (int) $_SESSION['user_id'], // <-- set user_id from session
            trim((string) $data['firstName']),
            trim((string) $data['lastName']),
            trim((string) $data['email']),
            trim((string) $data['phone']),
            trim((string) $data['address']),
            trim((string) $data['city']),
            trim((string) $data['state']),
            trim((string) $data['pincode']),
            $paymentMethod,
            $quote['subtotal'],
            $quote['shipping'],
            $quote['tax'],
            $quote['total'],
            'processing',
            date('Y-m-d H:i:s'),
            $quote['items'],
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
            'pricing' => [
                'subtotal' => $quote['subtotal'],
                'shipping' => $quote['shipping'],
                'tax'      => $quote['tax'],
                'total'    => $quote['total'],
            ],
        ]);
        exit;
    }
}
