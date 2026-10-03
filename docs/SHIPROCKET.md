# Shiprocket integration

How courier pickup and tracking are wired into House of Viraasat, what you need
from Shiprocket to switch it on, and where each credential comes from.

---

## What you need, and where to get it

| # | What | Where to get it | Used for |
|---|------|-----------------|----------|
| 1 | **Account** | [shiprocket.in](https://www.shiprocket.in) → Sign up | Basic access |
| 2 | **Email address** | The one on your Shiprocket account | HTTP Basic auth username |
| 3 | **API token** | Shiprocket dashboard → **Settings → API → API Key → Generate Token** (or **Settings → Developer/API**) | HTTP Basic auth password |
| 4 | **Pickup PIN code** | Set under **Settings → Pickup → Pickup Address**, verified with a location pin | Origin address on every shipment |
| 5 | **Webhook URL** | You supply it: `https://<your-domain>/api/webhooks/tracking` | Courier status updates |
| 6 | **Webhook API key** | You generate it (`openssl rand -hex 32`) and send it as the `x-api-key` header | Authenticates the callback |

> Item 3 is the one people get stuck on: the API token is **not** your login
> password. It's generated on the API settings page and can be regenerated
> there. Regenerating it immediately invalidates the old one.

### Callback URL to paste into Shiprocket

Shiprocket sends the status of your *shipments*. Configure it under
**Settings → Webhooks → Add Webhook**, or per-courier if you only want one
carrier. Paste:

```
https://your-domain.example/api/webhooks/tracking
```

Then set an `x-api-key` header on that webhook carrying your secret. The URL
itself holds no credential, so nothing sensitive lands in access logs, proxy
logs or `Referer` headers.

> If your webhook form genuinely cannot set custom headers, set
> `SHIPROCKET_WEBHOOK_ALLOW_QUERY_TOKEN=true` and use `?token=<secret>`
> instead. It is off by default because a secret in a query string leaks into
> exactly those logs.

---

## Environment variables

Add to `.env` (see `.env.example`):

```ini
SHIPROCKET_EMAIL=you@yourdomain.com
SHIPROCKET_API_TOKEN=paste_token_here
SHIPROCKET_SIMULATE=false
SHIPROCKET_WEBHOOK_SECRET=paste_generated_secret_here
```

`SHIPROCKET_SIMULATE` is ours, not Shiprocket's:

| Value | Behaviour |
|-------|-----------|
| `true` (default) | **Request Delivery** issues a local test AWB like `SRD3FBE95C`. Nothing leaves the server. Safe for staging and for developing without credentials. |
| `false` | Real API calls. Requires valid credentials above. |

The admin UI states plainly which mode is active, and every simulated shipment
is flagged `(sandbox)` in the attempt log.

---

## The admin flow

1. `/admin/orders` → open an order
2. Move it to **Shipped** (only shipped orders can be handed to the courier)
3. The **Delivery** card's **Request Delivery** button appears
4. Clicking it POSTs to `/admin/orders/request-delivery`
5. On success the card shows the **AWB**, shipment id, pickup token, a
   **Download label** link, and every attempt in the log below it

The button is CSRF-protected and appears at most once per order — the insert
that records a shipment is guarded by `WHERE shiprocket_shipment_id IS NULL`,
so a double click cannot create two real shipments with the courier.

---

## Inbound tracking (webhook)

`POST /api/webhooks/tracking` — no session, authenticated by the shared
secret in the `x-api-key` header (the `x-shiprocket-webhook-secret` alias is
also accepted). A `?token=` query parameter is refused unless
`SHIPROCKET_WEBHOOK_ALLOW_QUERY_TOKEN=true`.

Shiprocket courier statuses are mapped onto our order lifecycle:

| Shiprocket status | Order status |
|-------------------|--------------|
| `PICKED_UP`, `PACKED`, `SHIPPED`, `IN_TRANSIT`, `OUT_FOR_DELIVERY`, … | `shipped` |
| `DELIVERED` | `delivered` (also stamps `delivered_at`) |
| `RTO`, `DROPPED`, `LOST`, `DAMAGED`, `RETURNED`, `DELIVERY_FAILED`, … | `cancelled` |

Design decisions worth knowing:

- **Transitions are still guarded.** A webhook cannot drag a `delivered` order
  back to `shipped`, or cancel one that is already delivered. The event is
  still recorded — it's just refused, and the response says why.
- **Idempotent.** Shiprocket retries anything that isn't 2xx and repeats
  events. Duplicate `DELIVERED` callbacks return `changed: false` with no
  second transition.
- **Unknown shipments return 200.** If we never created that shipment, we
  answer `{"matched": false}` rather than a 4xx, so Shiprocket stops retrying
  something we have no record of.
- **The raw event is always logged** to `order_shipments`, even when the
  transition is refused.

Every response body tells you what happened:

```json
{"success": true, "matched": true, "changed": true,  "status": "delivered"}
{"success": true, "matched": true, "changed": false, "status": "delivered"}
{"success": true, "matched": true, "changed": false, "status": "delivered",
 "note": "Refused \"cancelled\" from \"delivered\" (allowed: none)"}
```

---

## What the integration sends Shiprocket

`ShipmentService::buildPayload()` produces a standard Shiprocket order:

- `order_id`, `order_date`, `order_status`, `channel: own_channel`
- `payment_method`: `COD` or `Prepaid`
- `shipping_res_*` from the order, `billing_res_*` mirrored from it
- `line_items[]` — SKU, name, qty, price, total, weight, image
- `packages[]` — one parcel, dimensions and weight in kg

Weight comes from each product's `weight` column, entered by the admin under
**Products → Add/Edit → Weight (kg)**. That figure is **snapshotted onto the
order line at checkout** (`order_items.weight`), so re-weighting a product
afterwards cannot rewrite what a parcel that already shipped was declared with.
Orders placed before this existed fall back to 0.5 kg per garment.

Shiprocket bills on the declared weight, so these numbers should be the real
packed weight. Getting them wrong means under-declared parcels and surprise COD
collection failures.

Phone numbers are stripped to digits (`+91 98765 43210` → `919876543210`);
Shiprocket rejects formatted numbers.

---

## Files

| Path | Role |
|------|------|
| `app/Infrastructure/Shipping/ShiprocketClient.php` | HTTP client for `api.shiprocket.in/v1` |
| `app/Application/Services/ShipmentService.php` | Payload builder + status mapping |
| `app/Infrastructure/Http/Controllers/AdminOrdersController.php` | `requestDelivery()` action |
| `app/Infrastructure/Http/Controllers/WebhookController.php` | Inbound courier events |
| `app/Infrastructure/Persistence/MySqlOrderRepository.php` | Shipment persistence and guarded attach |
| `app/database/migrations/20260101_000015_shiprocket_shipments.sql` | Shipment columns + attempt log |
| `app/database/migrations/20260101_000016_shiprocket_webhook_status.sql` | Latest courier status, `delivered_at` |
| `app/database/migrations/20260101_000017_product_weight.sql` | `products.weight` in kg, editable in the admin |
| `app/database/migrations/20260101_000018_order_item_weight.sql` | `order_items.weight` snapshot |

---

## Go-live checklist

- [ ] `SHIPROCKET_SIMULATE=false`
- [ ] Real `SHIPROCKET_EMAIL` + `SHIPROCKET_API_TOKEN`
- [ ] Pickup PIN code verified in Shiprocket settings
- [ ] Webhook URL configured in Shiprocket, with an `x-api-key` header matching `SHIPROCKET_WEBHOOK_SECRET`
- [ ] Weights entered for real products under **Products → Edit**, and one
      parcel checked against the courier's expected figure
- [ ] `ADMIN_PASSWORD` set in `.env` (the hardcoded `admin`/`password` fallback is still live otherwise)
- [ ] One real test order end-to-end: shipped → Request Delivery → AWB → webhook `DELIVERED`