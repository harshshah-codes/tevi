-- Latest courier status pushed by Shiprocket webhooks

ALTER TABLE orders
    ADD COLUMN shiprocket_last_status VARCHAR(40) NULL DEFAULT NULL AFTER shiprocket_requested_at,
    ADD COLUMN shiprocket_last_update TIMESTAMP NULL DEFAULT NULL AFTER shiprocket_last_status,
    ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL AFTER shiprocket_last_update;