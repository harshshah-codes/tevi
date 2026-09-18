-- House of Viraasat — customer reviews
-- Run AFTER catalog.sql (depends on the `products` table).
-- Requires the `house_of_virasat` database.

USE house_of_virasat;

CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  author VARCHAR(100) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  review_text TEXT,
  created_at DATE NOT NULL,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_reviews_product (product_id),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO reviews (product_id, author, rating, review_text, created_at, is_visible) VALUES
(1, 'Vivaan Patel', 5, 'Elegant, comfortable and beautifully finished. The handwork detailing is subtle but adds a premium touch.', '2026-08-10', 1),
(1, 'Aarav Shah', 5, 'The craftsmanship is absolutely beautiful. Every outfit feels premium and perfectly designed.', '2026-08-03', 1),
(1, 'Rohan Mehta', 4, 'Amazing collection, excellent fitting and a truly premium shopping experience.', '2026-07-25', 1),
(1, 'Karan Sharma', 5, 'Perfect fit and the fabric feels luxurious. Highly recommended for festive events.', '2026-07-18', 1),
(1, 'Dipesh Joshi', 4, 'Lovely colour and finish. Sizing is true to the size chart.', '2026-06-30', 1),
(2, 'Arjun Singh', 5, 'Stunning sherwani! The midnight blue is even better in person and the fit is impeccable.', '2026-08-15', 1),
(2, 'Vedant Rao', 5, 'Wore it for a wedding and received endless compliments. Truly premium.', '2026-08-05', 1),
(2, 'Harsh Trivedi', 4, 'Beautiful fabric, great tailoring. Slightly heavy, but expected for velvet.', '2026-07-28', 0),
(3, 'Nikhil Malhotra', 5, 'The sage shade is subtle and elegant. Lightweight and very comfortable.', '2026-08-20', 1),
(3, 'Ronit Saxena', 4, 'Nice everyday festive piece. Fits well and the embroidery is neat.', '2026-08-09', 1),
(3, 'Yash Bansal', 5, 'Quality far above the price. Will order again.', '2026-07-19', 1),
(4, 'Manav Kulkarni', 5, 'The bandhgala set is regal. Tailoring is flawless.', '2026-08-12', 1),
(4, 'Pranav Iyer', 4, 'Great set for a destination wedding. The jacket fits beautifully.', '2026-07-22', 0),
(7, 'Ishaan Goyal', 5, 'Premium black sherwani with gold detailing. Looks expensive and feels even better.', '2026-08-25', 1);