-- Add user_id to orders table

ALTER TABLE orders ADD COLUMN user_id INT UNSIGNED NULL AFTER id;
