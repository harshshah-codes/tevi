-- Wishlist per customer.
--
-- The wishlist used to live only in the browser's localStorage, so it was
-- per-device, never synced, and lost on a cache clear. This is the server-side
-- home for it; guests still fall back to localStorage and merge on sign-in.

CREATE TABLE IF NOT EXISTS wishlist_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    size VARCHAR(20) NOT NULL DEFAULT '',
    color VARCHAR(50) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wishlist_user_product (user_id, product_id),
    KEY idx_wishlist_user (user_id),
    CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;