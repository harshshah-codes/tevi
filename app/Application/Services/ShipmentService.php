<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\Order;
use App\Infrastructure\Shipping\ShiprocketClient;
use RuntimeException;

final class ShipmentService
{
    /** Shiprocket only accepts these order_status values. */
    private const STATUS_MAP = [
        'processing' => 'processing',
        'shipped'    => 'shipped',
        'delivered'  => 'delivered',
        'cancelled'  => 'cancelled',
    ];

    public function __construct(
        private readonly ShiprocketClient $shiprocket,
    ) {
    }

    public function isSimulated(): bool
    {
        return $this->shiprocket->isSimulated();
    }

    /**
     * Only an order that has actually been handed over should be shipped.
     */
    public function canRequestDelivery(Order $order): bool
    {
        return $order->status() === 'shipped' && $order->shiprocketWaybill() === null;
    }

    /**
     * @return array<string, mixed> Shiprocket-compatible order payload
     */
    public function buildPayload(Order $order): array
    {
        $orderStatus = self::STATUS_MAP[$order->status()] ?? 'processing';
        $isCod = $order->paymentMethod() === 'cod';

        $address = [
            'shipping_res_name'     => $order->firstName() . ' ' . $order->lastName(),
            'shipping_res_address1' => mb_substr($order->address(), 0, 255),
            'shipping_res_address2' => '',
            'shipping_res_city'     => $order->city(),
            'shipping_res_state'    => $order->state(),
            'shipping_res_country'  => 'India',
            'shipping_res_pincode'  => (string) $order->pincode(),
            'shipping_res_phone'    => preg_replace('/\D/', '', $order->phone()) ?: '9999999999',
            'shipping_res_email'    => $order->email(),
        ];

        // We have a single free-text address column, so billing mirrors shipping.
        $billing = [];
        foreach ($address as $key => $value) {
            $billing[str_replace('shipping_res_', 'billing_res_', $key)] = $value;
        }

        $lineItems = [];
        $totalWeight = 0.0;
        foreach ($order->items() as $index => $item) {
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $price = (int) ($item['price'] ?? 0);
            // Weight is snapshotted onto the order line at checkout, so a
            // parcel shipped today keeps the weight it was declared with even
            // if the product is re-weighted later. Older orders fall back to
            // 0.5kg per garment.
            $weight = (float) ($item['weight'] ?? 0.5);
            $weight = $weight > 0 ? $weight : 0.5;
            $totalWeight += $weight * $qty;

            $lineItems[] = [
                'sku'         => (string) ($item['product_id'] ?? ('HOV-' . ($index + 1))),
                'name'        => mb_substr((string) ($item['product_name'] ?? 'Item'), 0, 100),
                'qty'         => $qty,
                'price'       => $price,
                'discount'    => 0,
                'total'       => $price * $qty,
                'weight'      => $weight,
                'units'       => 'kg',
                'image_url'   => (string) ($item['image'] ?? ''),
                'description' => '',
                'category'    => 'apparel',
                'brand'       => 'House of Viraasat',
                'hsn'         => '',
            ];
        }

        if ($lineItems === []) {
            throw new RuntimeException('This order has no items to ship');
        }

        return array_merge($address, $billing, [
            'order_id'        => $order->orderId(),
            'order_date'      => date('d-M-Y H:i:s', strtotime($order->createdAt()) ?: time()),
            'order_status'    => $orderStatus,
            'channel'         => 'own_channel',
            'payment_method'  => $isCod ? 'COD' : 'Prepaid',
            'shipping_method' => 'Express',
            'invoice_no'      => $order->orderId(),
            'line_items'      => $lineItems,
            'packages'        => [[
                'length' => 30,
                'breadth' => 25,
                'height'  => 8,
                'weight'  => max(0.5, round($totalWeight, 2)),
                'units'   => 'kg',
            ]],
            'transaction_id' => $isCod ? null : ('HOV' . $order->id()),
        ]);
    }

    /**
     * Ask Shiprocket to arrange a pickup.
     *
     * @return array{shipment_id: string, waybill: string, label_url: string, pickup_token: string, shipment_status: string, simulated: bool}
     */
    public function requestDelivery(Order $order): array
    {
        if (!$this->canRequestDelivery($order)) {
            throw new RuntimeException(
                $order->shiprocketWaybill() !== null
                    ? 'A shipment already exists for this order'
                    : 'Only orders in the shipped state can be handed to Shiprocket'
            );
        }

        return $this->shiprocket->createShipment($this->buildPayload($order));
    }
}
