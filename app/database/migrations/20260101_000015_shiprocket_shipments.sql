-- Shiprocket shipment tracking on orders

ALTER TABLE orders
    ADD COLUMN shiprocket_shipment_id VARCHAR(100) NULL DEFAULT NULL AFTER paid_at,
    ADD COLUMN shiprocket_waybill VARCHAR(50) NULL DEFAULT NULL AFTER shiprocket_shipment_id,
    ADD COLUMN shiprocket_label_url VARCHAR(500) NULL DEFAULT NULL AFTER shiprocket_waybill,
    ADD COLUMN shiprocket_pickup_token VARCHAR(100) NULL DEFAULT NULL AFTER shiprocket_label_url,
    ADD COLUMN shiprocket_requested_at TIMESTAMP NULL DEFAULT NULL AFTER shiprocket_pickup_token;

-- One live shipment per order; guards against a double "Request Delivery"
-- click creating two real shipments on Shiprocket's side.
ALTER TABLE orders
    ADD UNIQUE KEY uq_orders_shiprocket_shipment (shiprocket_shipment_id);

-- Every attempt, successful or not, so repeated failures stay auditable
CREATE TABLE IF NOT EXISTS order_shipments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    shipment_id VARCHAR(100) NULL DEFAULT NULL,
    waybill VARCHAR(50) NULL DEFAULT NULL,
    label_url VARCHAR(500) NULL DEFAULT NULL,
    pickup_token VARCHAR(100) NULL DEFAULT NULL,
    shipment_status VARCHAR(30) NOT NULL DEFAULT 'requested',
    error_message VARCHAR(500) NULL DEFAULT NULL,
    simulated TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_shipments_order (order_id, id),
    CONSTRAINT fk_order_shipments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
