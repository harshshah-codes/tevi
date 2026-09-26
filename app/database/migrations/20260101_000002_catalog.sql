-- House of Viraasat — catalog: products, categories, product_category (many-to-many)
-- Run in phpMyAdmin (or mysql CLI). Requires the `house_of_virasat` database.

USE house_of_virasat;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  image VARCHAR(500) NOT NULL DEFAULT '',
  tagline VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  description TEXT,
  price INT UNSIGNED NOT NULL DEFAULT 0,
  image VARCHAR(500) NOT NULL DEFAULT '',
  badge ENUM('', 'Bestseller', 'Just In', 'New', 'Limited', 'Signature') NOT NULL DEFAULT '',
  sizes JSON NULL,
  colors JSON NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_slug (slug),
  KEY idx_products_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_category (
  product_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  KEY idx_product_category_category (category_id),
  CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (id, name, slug, image, tagline) VALUES
(1, 'Kurtas', 'kurta', 'https://images.unsplash.com/photo-1597983073493-88cd35cf93c7?auto=format&fit=crop&w=700&q=90', 'Timeless Everyday Style'),
(2, 'Sherwanis', 'sherwani', 'https://images.unsplash.com/photo-1610189020179-5f3cdb8b6f72?auto=format&fit=crop&w=700&q=90', 'Made For Grand Celebrations'),
(3, 'Nehru Jackets', 'nehru-jacket', 'https://images.unsplash.com/photo-1627225924765-552d49cf47ad?auto=format&fit=crop&w=700&q=90', 'Refined Traditional Style'),
(4, 'Indo-Western', 'indo-western', 'https://images.unsplash.com/photo-1608234807905-4466023792f5?auto=format&fit=crop&w=700&q=90', 'Tradition Meets Modern'),
(5, 'Wedding Wear', 'wedding-wear', 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=700&q=90', 'Crafted For Your Big Day');

INSERT INTO products (id, name, slug, description, price, image, badge, sizes, colors, is_featured) VALUES
(1, 'Ivory Handwork Kurta', 'ivory-handwork-kurta', 'Ivory handwork kurta with refined embroidery.', '2899', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=900&q=85', 'Bestseller', '["S","M","L","XL","2XL"]', '["Ivory","Gold"]', 1),
(2, 'Midnight Velvet Sherwani', 'midnight-velvet-sherwani', 'Midnight velvet sherwani for grand occasions.', '8999', 'https://images.unsplash.com/photo-1597983073493-88cd35cf93c7?auto=format&fit=crop&w=900&q=85', 'Signature', '["S","M","L","XL","2XL"]', '["Midnight Blue","Black"]', 1),
(3, 'Sage Embroidered Kurta', 'sage-embroidered-kurta', 'Sage embroidered kurta in a modern silhouette.', '3499', 'https://images.unsplash.com/photo-1622445275576-721325763afe?auto=format&fit=crop&w=900&q=85', 'New', '["S","M","L","XL","2XL"]', '["Sage","Beige"]', 1),
(4, 'Regal Bandhgala Set', 'regal-bandhgala-set', 'Regal bandhgala set for celebratory moments.', '7499', 'https://images.unsplash.com/photo-1610189020179-5f3cdb8b6f72?auto=format&fit=crop&w=900&q=85', 'Limited', '["S","M","L","XL","2XL"]', '["Charcoal","Navy"]', 1),
(5, 'Maroon Silk Kurta', 'maroon-silk-kurta', 'Maroon silk kurta, just in.', '3299', 'https://images.unsplash.com/photo-1597983073493-88cd35cf93c7?auto=format&fit=crop&w=900&q=85', 'Just In', '["S","M","L","XL","2XL"]', '["Maroon","Rust"]', 0),
(6, 'Sandstone Textured Kurta', 'sandstone-textured-kurta', 'Sandstone textured kurta, just in.', '2999', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=900&q=85', 'Just In', '["S","M","L","XL","2XL"]', '["Sandstone","Beige"]', 0),
(7, 'Black Royal Sherwani', 'black-royal-sherwani', 'Black royal sherwani, premium edition.', '9499', 'https://images.unsplash.com/photo-1610189020179-5f3cdb8b6f72?auto=format&fit=crop&w=900&q=85', 'Signature', '["S","M","L","XL","2XL"]', '["Black","Gold"]', 0),
(8, 'Olive Festive Sherwani', 'olive-festive-sherwani', 'Olive festive sherwani, new season.', '8299', 'https://images.unsplash.com/photo-1622445275576-721325763afe?auto=format&fit=crop&w=900&q=85', 'New', '["S","M","L","XL","2XL"]', '["Olive","Beige"]', 0);

INSERT INTO product_category (product_id, category_id) VALUES
(1, 1),
(2, 2), (2, 5),
(3, 1), (3, 4),
(4, 2), (4, 3), (4, 5),
(5, 1),
(6, 1),
(7, 2), (7, 5),
(8, 2);