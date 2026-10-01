-- Razorpay payment tracking on orders

ALTER TABLE orders
    ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER payment_method,
    ADD COLUMN razorpay_order_id VARCHAR(100) NULL DEFAULT NULL AFTER payment_status,
    ADD COLUMN razorpay_payment_id VARCHAR(100) NULL DEFAULT NULL AFTER razorpay_order_id,
    ADD COLUMN razorpay_signature VARCHAR(255) NULL DEFAULT NULL AFTER razorpay_payment_id,
    ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL AFTER razorpay_signature;

-- Razorpay's own API is the source of truth for an order lookup
ALTER TABLE orders
    ADD UNIQUE KEY uq_orders_razorpay_order (razorpay_order_id);
