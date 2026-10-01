-- Clear existing orders (pre-auth test data) and enforce user linkage

-- Existing orders were created before auth existed, so they have no user_id
DELETE FROM orders;

-- Query support for "my orders"
ALTER TABLE orders
    ADD INDEX idx_orders_user_id (user_id);

ALTER TABLE orders
    ADD CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
