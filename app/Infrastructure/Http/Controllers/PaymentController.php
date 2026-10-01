<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\OrderService;
use App\Application\Services\PaymentService;

final class PaymentController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly OrderService $orders,
    ) {
    }

    /**
     * GET /api/payments/config — what the storefront needs to open Razorpay.
     */
    public function config(): void
    {
        $this->header();

        $this->respond([
            'success'   => true,
            'keyId'     => $this->payments->keyId(),
            'simulated' => $this->payments->isSimulated(),
            'methods'   => ['card', 'upi', 'netbanking'],
            'cod'       => 'Pay when your order arrives',
        ]);
    }

    /**
     * POST /api/payments/order  { orderId }
     *
     * Opens a Razorpay order for one of ours and returns its id plus the
     * amount in paise. Only the signed-in customer's orders are accepted.
     */
    public function createOrder(): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();

        $data = $this->body();
        $orderId = trim((string) ($data['orderId'] ?? ''));
        if ($orderId === '') {
            $this->respond(['success' => false, 'error' => 'orderId is required'], 422);
        }

        $order = $this->orders->getOrderForUser($userId, $orderId);
        if ($order === null) {
            $this->respond(['success' => false, 'error' => 'Order not found'], 404);
        }

        if ($order->paymentMethod() === 'cod') {
            $this->respond(['success' => false, 'error' => 'This order is cash on delivery'], 409);
        }

        if ($order->paymentStatus() === PaymentService::STATUS_PAID) {
            $this->respond(['success' => false, 'error' => 'This order is already paid'], 409);
        }

        try {
            $gatewayOrder = $this->payments->createGatewayOrder($order);
        } catch (\Throwable $e) {
            $this->respond([
                'success' => false,
                'error'   => 'Could not start the payment: ' . $e->getMessage(),
            ], 502);
        }

        $this->orders->attachRazorpayOrder($order->id(), $gatewayOrder['id']);

        $this->respond([
            'success'    => true,
            'razorpayOrderId' => $gatewayOrder['id'],
            'amount'     => $gatewayOrder['amount'],
            'currency'   => $gatewayOrder['currency'],
            'keyId'      => $this->payments->keyId(),
            'simulated'  => $gatewayOrder['simulated'],
        ]);
    }

    /**
     * POST /api/payments/verify
     *   { razorpayOrderId, razorpayPaymentId, razorpaySignature }
     *
     * The signature is checked with the secret before anything is marked paid,
     * and the order is looked up by gateway id *and* session ownership.
     */
    public function verify(): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();

        $data = $this->body();
        $rzpOrderId = trim((string) ($data['razorpayOrderId'] ?? ''));
        $rzpPaymentId = trim((string) ($data['razorpayPaymentId'] ?? ''));
        $signature = trim((string) ($data['razorpaySignature'] ?? ''));

        if ($rzpOrderId === '' || $rzpPaymentId === '' || $signature === '') {
            $this->respond(['success' => false, 'error' => 'Missing payment verification fields'], 422);
        }

        $order = $this->orders->getOrderByRazorpayOrderId($rzpOrderId);
        if ($order === null || $order->userId() !== $userId) {
            $this->respond(['success' => false, 'error' => 'Order not found'], 404);
        }

        try {
            $result = $this->payments->confirmPayment($rzpOrderId, $rzpPaymentId, $signature);
        } catch (\Throwable $e) {
            $this->respond([
                'success' => false,
                'error'   => 'Could not verify the payment: ' . $e->getMessage(),
            ], 502);
        }

        $this->orders->recordPayment(
            $order->id(),
            $result['paymentStatus'],
            $rzpPaymentId,
            $signature
        );

        if (!$result['verified']) {
            $this->respond([
                'success'       => false,
                'error'         => 'Payment could not be verified',
                'paymentStatus' => $result['paymentStatus'],
            ], 400);
        }

        $this->respond([
            'success'       => true,
            'paymentStatus' => $result['paymentStatus'],
            'orderId'       => $order->orderId(),
        ]);
    }

    /**
     * POST /api/payments/simulate  { orderId }
     *
     * Sandbox-only shortcut used while RAZORPAY_SIMULATE is on and the keys
     * are placeholders. Refuses to do anything once real keys are configured,
     * so it can never stand in for a genuine signature check.
     */
    public function simulate(): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        if (!$this->payments->isSimulated()) {
            $this->respond([
                'success' => false,
                'error'   => 'Simulated payments are disabled',
            ], 403);
        }

        $userId = $this->requireUser();

        $data = $this->body();
        $orderId = trim((string) ($data['orderId'] ?? ''));
        if ($orderId === '') {
            $this->respond(['success' => false, 'error' => 'orderId is required'], 422);
        }

        $order = $this->orders->getOrderForUser($userId, $orderId);
        if ($order === null) {
            $this->respond(['success' => false, 'error' => 'Order not found'], 404);
        }

        if ($order->paymentStatus() === PaymentService::STATUS_PAID) {
            $this->respond(['success' => true, 'paymentStatus' => PaymentService::STATUS_PAID, 'orderId' => $order->orderId()]);
        }

        $paymentId = 'sim_pay_' . bin2hex(random_bytes(8));
        $this->orders->recordPayment(
            $order->id(),
            PaymentService::STATUS_PAID,
            $paymentId,
            'simulated'
        );

        $this->respond([
            'success'       => true,
            'simulated'     => true,
            'paymentStatus' => PaymentService::STATUS_PAID,
            'orderId'       => $order->orderId(),
        ]);
    }

    private function header(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    }

    private function respond(array $payload, int $status = 200): void
    {
        ob_end_clean();
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }

    private function requireUser(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->respond(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        return (int) $_SESSION['user_id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);

        if (!is_array($data)) {
            $this->respond(['success' => false, 'error' => 'Invalid JSON payload'], 400);
        }

        return $data;
    }
}
