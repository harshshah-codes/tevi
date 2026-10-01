-- Every order must belong to a user

-- Drop any owner-less rows left over from before auth was enforced
DELETE FROM orders WHERE user_id IS NULL;

-- The old FK used ON DELETE SET NULL, which is impossible once the column is
-- NOT NULL, so redefine it to delete the user's orders together with the user
ALTER TABLE orders
    DROP FOREIGN KEY fk_orders_user;

ALTER TABLE orders
    MODIFY COLUMN user_id INT UNSIGNED NOT NULL AFTER id;

ALTER TABLE orders
    ADD CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE;
