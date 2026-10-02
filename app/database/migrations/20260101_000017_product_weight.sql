-- Shipping weight per product, in kilograms.
-- Shiprocket bills on declared weight, so a guessed 0.5kg per garment means
-- wrong courier charges; this column is where the real figure belongs.

ALTER TABLE products
    ADD COLUMN weight DECIMAL(6,3) NOT NULL DEFAULT 0.500 AFTER price;