-- Cancellation audit trail

ALTER TABLE orders
    MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'processing';

ALTER TABLE orders
    ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER status,
    ADD COLUMN cancel_reason VARCHAR(255) NULL DEFAULT NULL AFTER cancelled_at;

-- Lets the UI ask "can I still cancel this?" without loading the row first
ALTER TABLE orders
    ADD INDEX idx_orders_user_status (user_id, status);
