<?php
declare(strict_types=1);

namespace App\Infrastructure\Shipping;

use RuntimeException;

/**
 * Shiprocket courier API client, per their External API docs.
 *
 * Authentication is a bearer token, not HTTP Basic: POST the API user's
 * email and password to /auth/login, then send
 * `Authorization: Bearer <token>` on every call. Tokens last 240 hours
 * (10 days), so they are cached and refreshed shortly before expiry.
 *
 * While `simulate` is on, no request leaves the server: a fake waybill is
 * returned so the admin flow can be exercised without credentials.
 */
final class ShiprocketClient
{
    /** How many extra attempts to make when the gateway returns 5xx. */
    private const MAX_ATTEMPTS = 3;

    /** Refresh a token this many seconds before it actually expires. */
    private const TOKEN_EXPIRY_MARGIN = 120;

    /** @var array{token: string, expiresAt: int}|null in-process memo */
    private ?array $token = null;

    public function __construct(
        private readonly string $email,
        private readonly string $apiPassword,
        private readonly bool $simulate = false,
        private readonly string $apiBase = 'https://apiv2.shiprocket.in/v1/external',
        private readonly int $maxAttempts = self::MAX_ATTEMPTS,
        private readonly ?string $tokenCacheFile = null,
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
     * Create a shipment and arrange its pickup.
     *
     * Their documented sequence is three calls, not one:
     *   1. POST /orders/create/adhoc   -> shipment_id
     *   2. POST /courier/assign/awb    -> awb_code
     *   3. POST /courier/generate/pickup -> pickup_token
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

        $created = $this->request('POST', '/orders/create/adhoc', $payload);
        $shipmentId = (string) ($created['shipment_id'] ?? '');

        if ($shipmentId === '') {
            throw new RuntimeException('Shiprocket did not return a shipment id');
        }

        // AWB assignment is a separate call; without it there is no tracking
        // number and no pickup.
        $assigned = $this->request('POST', '/courier/assign/awb', [
            'shipment_id' => [$shipmentId],
        ]);

        $waybill = $this->firstAwb($assigned, $shipmentId);

        $pickup = $this->request('POST', '/courier/generate/pickup', [
            'shipment_id' => [$shipmentId],
        ]);

        return [
            'shipment_id'     => $shipmentId,
            'waybill'         => $waybill,
            'label_url'       => (string) ($this->firstService($pickup)['label_url'] ?? ''),
            'pickup_token'    => (string) ($this->firstService($pickup)['pickup_token'] ?? ''),
            'shipment_status' => (string) ($this->firstService($pickup)['status'] ?? 'processing'),
            'simulated'       => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getShipmentStatus(string $awb): array
    {
        if ($this->simulate) {
            return [
                'awb'             => $awb,
                'shipment_status' => 'in transit',
                'simulated'       => true,
            ];
        }

        return $this->request('GET', '/courier/track/awb/' . rawurlencode($awb));
    }

    /**
     * Couriers that can ship this pincode, with their charges.
     *
     * @return array<int, array<string, mixed>>
     */
    public function serviceability(string $pickupPincode, string $deliveryPincode, int $weightGrams = 500): array
    {
        if ($this->simulate) {
            return [['courier_name' => 'Simulated Courier', 'rate' => 0, 'delivery_days' => 3]];
        }

        $response = $this->request('GET', '/courier/serviceability/?pickup_pincode='
            . rawurlencode($pickupPincode)
            . '&delivery_pincode=' . rawurlencode($deliveryPincode)
            . '&weight=' . $weightGrams);

        return array_values(array_filter($response, 'is_array'));
    }

    /**
     * Fetch (and cache) a bearer token.
     */
    public function token(): string
    {
        $cached = $this->cachedToken();
        if ($cached !== null) {
            $this->token = $cached;
            return $cached['token'];
        }

        if ($this->simulate) {
            return 'sim_token';
        }

        $response = $this->request('POST', '/auth/login', [
            'email'    => $this->email,
            'password' => $this->apiPassword,
        ], false);

        $token = (string) ($response['token'] ?? $response['data']['token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Shiprocket did not return an auth token');
        }

        $this->token = [
            'token'     => $token,
            // Documented as 240 hours; assume the worst case if they do not say.
            'expiresAt' => time() + (int) ($response['expires_in'] ?? 240 * 3600),
        ];
        $this->writeCachedToken($this->token);

        return $token;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = [], bool $authenticate = true): array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            try {
                return $this->send($method, $path, $payload, $authenticate);
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
    private function send(string $method, string $path, array $payload, bool $authenticate): array
    {
        $url = rtrim($this->apiBase, '/') . $path;

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: HouseOfViraasat/1.0',
        ];

        if ($authenticate && !$this->simulate) {
            $headers[] = 'Authorization: Bearer ' . $this->token();
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
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
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function firstService(array $response, ?string $shipmentId = null): array
    {
        $services = $response['services'] ?? $response['data'] ?? $response;

        if (!is_array($services)) {
            return [];
        }

        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            if ($shipmentId !== null && ($service['shipment_id'] ?? null) !== $shipmentId) {
                continue;
            }

            return $service;
        }

        return is_array($services) && $services !== [] && is_array(reset($services)) ? (array) reset($services) : [];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function firstAwb(array $response, string $shipmentId): string
    {
        $services = $this->firstService($response, $shipmentId);

        return (string) ($services['awb_code'] ?? $services['awb'] ?? '');
    }

    /**
     * @return array{token: string, expiresAt: int}|null
     */
    private function cachedToken(): ?array
    {
        if ($this->token !== null && $this->token['expiresAt'] - self::TOKEN_EXPIRY_MARGIN > time()) {
            return $this->token;
        }

        if ($this->tokenCacheFile === null || !is_readable($this->tokenCacheFile)) {
            return null;
        }

        $raw = @file_get_contents($this->tokenCacheFile);
        if ($raw === false) {
            return null;
        }

        $cached = json_decode($raw, true);
        if (!is_array($cached) || !isset($cached['token'], $cached['expiresAt'])) {
            return null;
        }

        // Refresh slightly early so a token cannot expire mid-request.
        if ((int) $cached['expiresAt'] - self::TOKEN_EXPIRY_MARGIN <= time()) {
            return null;
        }

        return ['token' => (string) $cached['token'], 'expiresAt' => (int) $cached['expiresAt']];
    }

    /**
     * @param array{token: string, expiresAt: int} $token
     */
    private function writeCachedToken(array $token): void
    {
        if ($this->tokenCacheFile === null) {
            return;
        }

        $dir = dirname($this->tokenCacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($this->tokenCacheFile, json_encode($token), LOCK_EX);
        @chmod($this->tokenCacheFile, 0600);
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