<?php
declare(strict_types=1);

namespace App\Infrastructure\Shipping;

use RuntimeException;

/**
 * Shiprocket courier API client.
 *
 * While `simulate` is on, no request leaves the server: a fake waybill is
 * returned so the admin flow can be exercised without credentials.
 *
 * The base URL is configurable because Shiprocket have published a second
 * host (apiv2.shiprocket.in/v1/external) alongside this one, and which one
 * answers depends on the account and the caller's IP — a 503 gateway page from
 * one host does not mean the other is the only valid one.
 */
final class ShiprocketClient
{
    /** How many extra attempts to make when the gateway returns 5xx. */
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly string $email,
        private readonly string $apiToken,
        private readonly bool $simulate = false,
        private readonly string $apiBase = 'https://api.shiprocket.in/v1',
        private readonly int $maxAttempts = self::MAX_ATTEMPTS,
    ) {
    }

    public function isSimulated(): bool
    {
        return $this->simulate;
    }

    public function apiBase(): string
    {
        return $this->apiBase;
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
                'shipment_id'     => $shipmentId,
                'shipment_status' => 'in transit',
                'simulated'       => true,
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
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            try {
                return $this->send($method, $path, $payload);
            } catch (ShiprocketUnavailableException $e) {
                // 5xx and transport failures are usually transient, so retry
                // with a short backoff before giving up.
                $lastError = $e;

                if ($attempt >= $this->maxAttempts) {
                    break;
                }

                usleep(400_000 * $attempt);
            }
        }

        throw $lastError ?? new RuntimeException('Shiprocket request failed');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $payload = []): array
    {
        $url = rtrim($this->apiBase, '/') . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: HouseOfViraasat/1.0',
            ],
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
            throw new ShiprocketUnavailableException('Could not reach Shiprocket: ' . $error);
        }

        return $this->parseResponse($status, (string) $body);
    }

    /**
     * Turn a Shiprocket response into an array, or explain why it is not one.
     *
     * Split out from the HTTP call so the error wording can be tested without
     * a live account.
     *
     * @return array<string, mixed>
     */
    private function parseResponse(int $status, string $body): array
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            // A non-JSON body means we never reached their API — typically an
            // HTML error page from a load balancer or a decommissioned host.
            throw new ShiprocketUnavailableException(
                'Shiprocket returned HTTP ' . $status . ' with a non-JSON body ('
                . $this->describe($body) . '). This usually means the API host is '
                . 'unavailable or has moved — check SHIPROCKET_API_BASE, which is set to '
                . $this->apiBase . '.',
                $status
            );
        }

        if ($status >= 400) {
            $message = $decoded['message'] ?? $decoded['error'] ?? 'unknown error';
            if (is_array($message)) {
                $message = implode('; ', $message);
            }

            // 5xx from the API itself is worth retrying; 4xx is our problem.
            if ($status >= 500) {
                throw new ShiprocketUnavailableException('Shiprocket error ' . $status . ': ' . $message, $status);
            }

            throw new RuntimeException('Shiprocket error ' . $status . ': ' . $message);
        }

        return $decoded;
    }

    /**
     * A one-line description of a non-JSON body, without dumping markup at the
     * admin who has to read it.
     */
    private function describe(string $body): string
    {
        if (preg_match('#<title>(.*?)</title>#is', $body, $m) === 1) {
            return trim(html_entity_decode($m[1]));
        }

        $flat = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');

        return $flat === '' ? 'empty response' : mb_substr($flat, 0, 120);
    }
}