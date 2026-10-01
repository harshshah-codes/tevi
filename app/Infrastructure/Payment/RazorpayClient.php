<?php
declare(strict_types=1);

namespace App\Infrastructure\Payment;

use RuntimeException;

/**
 * Thin Razorpay Orders + Payments API client.
 *
 * While `simulate` is on, every call is answered locally so the storefront
 * flow can be exercised end-to-end without real credentials. The simulated
 * ids are prefixed `sim_` so they can never be mistaken for real ones.
 */
final class RazorpayClient
{
    private const API_BASE = 'https://api.razorpay.com/v1';

    public function __construct(
        private readonly string $keyId,
        private readonly string $keySecret,
        private readonly bool $simulate = false,
    ) {
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    public function isSimulated(): bool
    {
        return $this->simulate;
    }

    /**
     * Create a Razorpay order for the given amount (in paise).
     *
     * @param array<string, string> $notes
     * @return array{id: string, amount: int, currency: string, status: string, simulated: bool}
     */
    public function createOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        if ($this->simulate) {
            return [
                'id'        => 'sim_order_' . bin2hex(random_bytes(8)),
                'amount'    => $amountPaise,
                'currency'  => 'INR',
                'status'    => 'created',
                'simulated' => true,
            ];
        }

        $payload = [
            'amount'      => $amountPaise,
            'currency'    => 'INR',
            'receipt'     => mb_substr($receipt, 0, 40),
            'payment_capture' => 1,
        ];

        if ($notes !== []) {
            $payload['notes'] = $notes;
        }

        $response = $this->request('POST', '/orders', $payload);

        return [
            'id'        => (string) $response['id'],
            'amount'    => (int) $response['amount'],
            'currency'  => (string) $response['currency'],
            'status'    => (string) ($response['status'] ?? 'created'),
            'simulated' => false,
        ];
    }

    /**
     * Razorpay signs `razorpay_order_id|razorpay_payment_id` with the secret.
     */
    public function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $signature): bool
    {
        if ($this->simulate) {
            // Simulated payments carry the sim_ prefix and their own signature.
            return str_starts_with($razorpayOrderId, 'sim_order_')
                && str_starts_with($razorpayPaymentId, 'sim_pay_')
                && hash_equals($this->expectedSimulatedSignature($razorpayOrderId, $razorpayPaymentId), $signature);
        }

        $expected = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $this->keySecret);

        return hash_equals($expected, $signature);
    }

    /**
     * Ask Razorpay what actually happened to a payment.
     *
     * @return array<string, mixed>
     */
    public function fetchPayment(string $razorpayPaymentId): array
    {
        if ($this->simulate) {
            return [
                'id'     => $razorpayPaymentId,
                'status' => 'captured',
                'method' => 'simulated',
                'amount' => 0,
                'simulated' => true,
            ];
        }

        return $this->request('GET', '/payments/' . rawurlencode($razorpayPaymentId));
    }

    /**
     * Signature the simulated checkout hands back to the storefront.
     */
    public function expectedSimulatedSignature(string $razorpayOrderId, string $razorpayPaymentId): string
    {
        return hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $this->keySecret);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $url = self::API_BASE . $path;

        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERPWD        => $this->keyId . ':' . $this->keySecret,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Could not reach Razorpay: ' . $error);
        }

        $decoded = json_decode((string) $body, true);

        if ($status >= 400 || !is_array($decoded)) {
            $message = is_array($decoded) ? ($decoded['error']['description'] ?? 'unknown error') : (string) $body;
            throw new RuntimeException('Razorpay error ' . $status . ': ' . $message);
        }

        return $decoded;
    }
}
