# Inventory

Stock is counted per **variant** — a product in one colour and one size — and
every change is an append-only row in `inventory_ledger`.

## Why a variants table when products already has sizes and colours

`products.sizes` and `products.colors` are JSON arrays that build the picker.
They answer "which options may the customer choose?", not "how many are left".
`product_variants` holds one row per colour × size, and that is where stock
lives.

| | `products.sizes` / `.colors` | `product_variants` |
|---|---|---|
| Shape | JSON array of strings | one row per (product, colour, size) |
| Answers | which options exist | how many are left |
| Holds stock | no | yes |
| Relations | none | FK to product, FK from the ledger |

Variants are generated automatically from the two arrays whenever a product is
viewed or saved, so adding a colour or size in the admin creates the row
itself — at zero stock, ready for opening stock to be entered.

## The ledger

`inventory_ledger` is append-only. Stock is never edited: each change appends a
signed `quantity_delta` and records `balance_after`, so any row can be read on
its own ("what did we have at that moment?").

| `entry_type` | Typical cause |
|---|---|
| `opening` | first stock entered for a variant |
| `receipt` | a later delivery from the supplier |
| `sale` | an order was placed (negative) |
| `cancellation` | that order was cancelled (positive) |
| `correction` | an absolute "set stock" that differed |
| `return` / `write_off` | customer return, or damaged stock |

`product_variants.stock` is denormalised for fast reads and is updated inside
the same transaction as the ledger row, so the two cannot disagree.

## Rules for selling

Per your decision, stock is **blocking** — not warn-and-sell:

1. **Add to Bag** is disabled when the chosen colour/size has no stock. The
   option chips for exhausted sizes and colours are struck through too.
2. **Checkout refuses the order** with HTTP 409 and per-line detail — the
   product, the option, how many were asked for and how many exist.

The button state is advisory: stock can change between the page load and the
customer pressing pay, so `OrderController::store()` re-checks, and
`InventoryService::commitForOrder()` checks again inside the transaction.
Two customers racing for the last kurta cannot both succeed; the loser gets a
409 and no order is left behind.

Quick Add on the homepage and the shop page picks a **real variant** (first
colour, first size) and checks that one, rather than a placeholder.

Cancelling an order returns its stock and writes a `cancellation` entry.

## Using it

**Admin → Inventory** (in the sidebar):

- pick a product to see every colour × size with its stock and low-stock marker
- **Set** replaces the figure and records a `correction`
- **Add** increments it and records a `receipt`
- *Needs attention* lists products with any variant out of stock
- the **Ledger** table below shows every movement, newest first, with the note
  and the order it belonged to

The rule to remember: **nothing is ever changed silently.** If the number is
wrong, fixing it still leaves a line in the ledger explaining the correction.

## Files

| Path | Role |
|---|---|
| `app/database/migrations/20260101_000020_inventory.sql` | `product_variants`, `inventory_ledger`, `order_items.variant_id` |
| `app/Domain/Models/ProductVariant.php` | a countable colour × size |
| `app/Domain/Models/InventoryEntry.php` | one immutable ledger line |
| `app/Domain/Repositories/*Variant*`, `*InventoryLedger*` | persistence |
| `app/Application/Services/InventoryService.php` | stock rules, transactions |
| `app/Infrastructure/Http/Controllers/InventoryController.php` | `GET /api/inventory` (public lookup) |
| `app/Infrastructure/Http/Controllers/AdminInventoryController.php` | admin page |
| `public/js/inventory.js` | storefront availability checks |
| `tools/test-inventory.php` | 26 checks against the real database |

## Known gaps

- **No stock on hand for a product with no variants** means it cannot be sold
  through the storefront until opening stock is entered. This is the intended
  consequence of blocking, but it does mean a newly added product looks
  unavailable until someone sets its stock.
- **Existing orders** have no `variant_id`, so cancelling an order placed
  before this existed returns no stock. Their `size`/`colour` strings are still
  available if a backfill is ever wanted.
- **`/api/inventory` is public.** It exposes stock levels by product, which is
  fine for a shop (competitors can read it anywhere) but worth knowing.
