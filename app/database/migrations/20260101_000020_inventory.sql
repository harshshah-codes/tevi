-- Inventory: per-variant stock, kept honest by an append-only ledger.
--
-- products.sizes and products.colors are JSON blobs, which is fine for a
-- variant picker but useless for "how many ivory size M do I have left". This
-- table turns each real combination into a row we can count.

CREATE TABLE IF NOT EXISTS product_variants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    colour VARCHAR(50) NOT NULL DEFAULT '',
    size VARCHAR(20) NOT NULL DEFAULT '',
    -- Denormalised stock. The ledger is the source of truth; this is kept in
    -- step inside the same transaction so reads are a single row lookup.
    stock INT NOT NULL DEFAULT 0,
    -- Set when stock drops to or below this; the shop treats it as an alert,
    -- never as a hard block, because a pre-order is still a sale.
    low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 3,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_variant_product_colour_size (product_id, colour, size),
    KEY idx_variant_product (product_id, is_active),
    CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only: every change to stock is a row here. The balance for a variant
-- is SUM(quantity_delta), which is why corrections are expressed as signed
-- movements rather than edits.
CREATE TABLE IF NOT EXISTS inventory_ledger (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    variant_id INT UNSIGNED NOT NULL,
    -- NULL for opening stock entered before any customer existed.
    order_id INT UNSIGNED NULL DEFAULT NULL,
    entry_type ENUM('opening', 'receipt', 'sale', 'cancellation', 'return', 'correction', 'write_off') NOT NULL,
    quantity_delta INT NOT NULL,
    -- Stock after this entry, denormalised so a ledger row can be read on its
    -- own ("what did we have at that moment?").
    balance_after INT NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ledger_variant (variant_id, id),
    KEY idx_ledger_order (order_id),
    KEY idx_ledger_type (entry_type, id),
    CONSTRAINT fk_ledger_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
    CONSTRAINT fk_ledger_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
    CONSTRAINT fk_ledger_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remember which variant an order line consumed, so a cancellation can put
-- exactly that stock back without guessing from the free-text size/colour.
ALTER TABLE order_items
    ADD COLUMN variant_id INT UNSIGNED NULL DEFAULT NULL AFTER product_id,
    ADD INDEX idx_order_items_variant (variant_id),
    ADD CONSTRAINT fk_order_item_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE SET NULL;