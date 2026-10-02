-- Snapshot the packed weight onto each order line.
--
-- Weights are copied at checkout rather than looked up later: if a product is
-- re-weighted afterwards, the parcel that actually shipped must keep the
-- figure it was declared with.

ALTER TABLE order_items
    ADD COLUMN weight DECIMAL(6,3) NOT NULL DEFAULT 0.500 AFTER price;