<?php
declare(strict_types=1);

namespace App\Infrastructure\Shipping;

use RuntimeException;

/**
 * Shiprocket courier API client.
 *
 * While `simulate` is on, no request leaves the server: a fake waybill is
 * returned so the admin flow can be exercised without credentials.
 */
final class ShiprocketClient
{
    private const API_BASE = 'https://api.shiprocket.in/v1';

    public function __construct(
        private readonly string $email,
        private readonly string $apiToken,
        private readonly bool $simulate = false,
    ) {
    }

    public function isSimulated(): bool
    {
        return $this->simulate;
    }

    /**
     * Create (or fetch) a shipment for an order.
     *
     * Shiprocket returns the same shipment_id if the same order_id is sent
     * again, which is what makes a retry safe.
     *
     * @param array<string, mixed> $payload
     * @return array{shipment_id: string, waybill: string, label_url: string, pickup_token: string, shipment_status: string, simulated: bool}
     */
    public function createShipment(array $payload): array
    {
        if ($this->simulate) {
            $waybill = 'SR' . strtoupper(bin2hex(random_bytes(4)));

            return [
                'shipment_id'     => 'sim_shipment_' . bin2hex(random_bytes(6)),
                'waybill'         => $waybill,
                'label_url'       => 'https://example.test/label/' . $waybill . '.pdf',
                'pickup_token'    => 'sim_pickup_' . bin2hex(random_bytes(4)),
                'shipment_status' => 'pickup scheduled',
                'simulated'       => true,
            ];
        }

        $response = $this->request('POST', '/orders/', $payload);

        return [
            'shipment_id'     => (string) ($response['shipment_id'] ?? ''),
            'waybill'         => (string) ($response['awb'] ?? ''),
            'label_url'       => (string) ($response['label_url'] ?? ''),
            'pickup_token'    => (string) ($response['pickup_token'] ?? ''),
            'shipment_status' => (string) ($response['shipment_status'] ?? 'processing'),
            'simulated'       => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipmentStatus(string $shipmentId): array
    {
        if ($this->simulate) {
            return [
                'shipment_id' => $shipmentId,
                'shipment_status' => 'in transit',
                'simulated' => true,
            ];
        }

        return $this->request('GET', '/shipments/' . rawurlencode($shipmentId) . '/');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $ch = curl_init(self::API_BASE . $path);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_USERPWD        => $this->email . ':' . $this->apiToken,
            CURLOPT_TIMEOUT        => 25,
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
            throw new RuntimeException('Could not reach Shiprocket: ' . $error);
        }

        $decoded = json_decode((string) $body, true);

        if ($status >= 400 || !is_array($decoded)) {
            // Shiprocket puts the useful part in `message`, sometimes a list.
            $message = is_array($decoded)
                ? (is_array($decoded['message'] ?? null)
                    ? implode('; ', $decoded['message'])
                    : (string) ($decoded['message'] ?? 'unknown error'))
                : (string) $body;

            throw new RuntimeException('Shiprocket error ' . $status . ': ' . $message);
        }

        return $decoded;
    }
}
