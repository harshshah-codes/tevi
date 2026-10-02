<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\OrderService;
use App\Application\Services\PaymentService;
use App\Application\Services\ShipmentService;

/**
 * Inbound courier events.
 *
 * Shiprocket does not sign its webhooks, so authentication rests on a shared
 * secret carried in the callback URL or a header. Shiprocket retries anything
 * that is not 2xx, and it repeats events, so every handler here is idempotent.
 */
final class WebhookController
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ShipmentService $shipments,
        private readonly string $webhookSecret,
    ) {
    }

    /**
     * POST /api/webhooks/shiprocket
     */
    public function shiprocket(): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        if (!$this->authorised()) {
            $this->respond(['success' => false, 'error' => 'Invalid webhook token'], 401);
        }

        $event = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($event)) {
            $this->respond(['success' => false, 'error' => 'Invalid JSON payload'], 400);
        }

        $shipmentId = $this->str($event, ['shipment_id', 'id', 'ShipmentID']);
        $waybill = $this->str($event, ['awb', 'AWB', 'awb_number']);
        $courierStatus = strtoupper($this->str($event, ['shipment_status', 'ShipmentStatus']));
        $orderStatus = strtoupper($this->str($event, ['order_status', 'OrderStatus']));

        if ($shipmentId === '' && $waybill === '') {
            $this->respond(['success' => false, 'error' => 'No shipment id or AWB in payload'], 422);
        }

        $order = $this->orders->findOrderForShipment($shipmentId !== '' ? $shipmentId : null, $waybill !== '' ? $waybill : null);
        if ($order === null) {
            // Nothing to update. Answer 200 so Shiprocket stops retrying a
            // shipment we have never heard of.
            $this->respond(['success' => true, 'matched' => false]);
        }

        $mapped = $this->mapStatus($courierStatus !== '' ? $courierStatus : $orderStatus);
        $target = $mapped['orderStatus'];
        $delivered = $mapped['delivered'];

        // Record the observation first — this is the part we must never lose.
        $this->orders->recordCourierStatus($order->id(), $courierStatus !== '' ? $courierStatus : 'UNKNOWN', $delivered, $waybill !== '' ? $waybill : null);
        $this->orders->recordShipmentAttempt(
            $order->id(),
            $shipmentId !== '' ? $shipmentId : null,
            $waybill !== '' ? $waybill : null,
            null,
            null,
            'webhook:' . ($courierStatus !== '' ? $courierStatus : 'UNKNOWN'),
            null,
            $this->shipments->isSimulated()
        );

        if ($target === null || $target === $order->status()) {
            // Already in that state — a duplicate event, or one we deliberately
            // refuse to act on. Either way it is not an error.
            $this->respond([
                'success' => true,
                'matched'  => true,
                'status'   => $order->status(),
                'changed'  => false,
            ]);
        }

        $allowed = $this->orders->adminTransitionsFrom($order->status());
        if (!in_array($target, $allowed, true)) {
            $this->respond([
                'success' => true,
                'matched' => true,
                'status'  => $order->status(),
                'changed' => false,
                'note'    => 'Refused "' . $target . '" from "' . $order->status() . '" (allowed: '
                    . ($allowed === [] ? 'none' : implode(', ', $allowed)) . ')',
            ]);
        }

        $changed = $this->orders->adminUpdateStatus(
            $order->id(),
            $order->status(),
            $target,
            'Shiprocket webhook: ' . ($courierStatus !== '' ? $courierStatus : 'UNKNOWN'),
            'shiprocket'
        );

        $this->respond([
            'success' => true,
            'matched' => true,
            'changed' => $changed,
            'status'  => $changed ? $target : $order->status(),
        ]);
    }

    /**
     * Shiprocket's courier statuses onto our order lifecycle.
     *
     * Terminal courier outcomes (RTO, dropped, lost) cancel the order, since
     * the parcel will never reach the customer.
     *
     * @return array{orderStatus: string|null, delivered: bool}
     */
    private function mapStatus(string $courierStatus): array
    {
        return match ($courierStatus) {
            'PICKED_UP', 'PICKUP_SCHEDULED', 'PACKED', 'READY_FOR_PICKUP',
            'SHIPPED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY' => ['orderStatus' => 'shipped', 'delivered' => false],

            'DELIVERED' => ['orderStatus' => 'delivered', 'delivered' => true],

            'CANCELLED', 'CANCELED', 'RTO', 'RTO_INITIATED', 'DROPPED',
            'LOST', 'DAMAGED', 'RETURNED', 'RETURN_ACCEPTED', 'RETURN_DISPATCHED',
            'DELIVERY_FAILED', 'ADDRESS_CHANGED' => ['orderStatus' => 'cancelled', 'delivered' => false],

            // Informational states we record but never act on.
            default => ['orderStatus' => null, 'delivered' => false],
        };
    }

    /**
     * @param array<string, mixed> $event
     */
    private function str(array $event, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($event[$key]) && is_scalar($event[$key])) {
                $value = trim((string) $event[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function authorised(): bool
    {
        if ($this->webhookSecret === '') {
            // No secret configured: refuse rather than accept anything.
            return false;
        }

        $header = (string) ($_SERVER['HTTP_X_SHIPROCKET_WEBHOOK_SECRET'] ?? '');
        $query = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

        foreach ([$header, $query] as $candidate) {
            if ($candidate !== '' && hash_equals($this->webhookSecret, $candidate)) {
                return true;
            }
        }

        return false;
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
}