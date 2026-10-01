<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\Order;
use App\Infrastructure\Payment\RazorpayClient;

final class PaymentService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    public function __construct(
        private readonly RazorpayClient $razorpay,
    ) {
    }

    public function keyId(): string    {
        return $this->razorpay->keyId();
    }

    public function isSimulated(): bool
    {
        return $this->razorpay->isSimulated();
    }

    /**
     * Open a Razorpay order for one of ours.
     *
     * @return array{id: string, amount: int, currency: string, status: string, simulated: bool}
     */
    public function createGatewayOrder(Order $order): array
    {
        return $this->razorpay->createOrder(
            $this->amountInPaise($order),
            $order->orderId(),
            [
                'order_id'  => $order->orderId(),
                'user_id'   => (string) $order->userId(),
                'email'     => $order->email(),
                'phone'     => $order->phone(),
            ]
        );
    }

    public function amountInPaise(Order $order): int
    {
        return (int) round($order->total() * 100);
    }

    /**
     * Confirm a payment: verify the signature, then optionally ask Razorpay
     * what it thinks happened before we mark the order paid.
     *
     * @return array{verified: bool, paymentStatus: string, payment: array<string, mixed>}
     */
    public function confirmPayment(string $razorpayOrderId, string $razorpayPaymentId, string $signature): array
    {
        if (!$this->razorpay->verifySignature($razorpayOrderId, $razorpayPaymentId, $signature)) {
            return [
                'verified'     => false,
                'paymentStatus' => self::STATUS_FAILED,
                'payment'      => [],
            ];
        }

        $payment = $this->razorpay->fetchPayment($razorpayPaymentId);

        // Razorpay is authoritative for whether money actually moved.
        $captured = ($payment['status'] ?? '') === 'captured';

        return [
            'verified'     => $captured,
            'paymentStatus' => $captured ? self::STATUS_PAID : self::STATUS_FAILED,
            'payment'      => $payment,
        ];
    }
}
