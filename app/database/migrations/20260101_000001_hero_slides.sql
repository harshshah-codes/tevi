-- House of Viraasat — hero carousel table
-- Run this in phpMyAdmin (or mysql CLI) to create the database and seed slides.

CREATE DATABASE IF NOT EXISTS house_of_virasat
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE house_of_virasat;

CREATE TABLE IF NOT EXISTS hero_slides (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tagline VARCHAR(255) NOT NULL DEFAULT '',
  headline VARCHAR(255) NOT NULL DEFAULT '',
  paragraph TEXT,
  button VARCHAR(100) NOT NULL DEFAULT '',
  cta_link VARCHAR(500) NOT NULL DEFAULT '',
  image_url VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO hero_slides (tagline, headline, paragraph, button, cta_link, image_url) VALUES
('THE FESTIVE EDIT · 2026', 'Tradition,<br><em>Tailored</em> Beautifully.', 'Timeless Indian silhouettes, refined fabrics and details made for your most memorable occasions.', 'Explore Collection', '#featured', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=2200&q=90'),
('ROYAL SHERWANIS', 'Make an<br><em>Entrance</em> to Remember.', 'Statement sherwanis crafted with a modern eye and a deep respect for heritage.', 'Shop Sherwanis', '#featured', 'https://images.unsplash.com/photo-1597983073493-88cd35cf93c7?auto=format&fit=crop&w=2200&q=90'),
('EVERYDAY ETHNIC', 'Quiet Luxury,<br><em>Indian Soul.</em>', 'Elevated kurtas designed to move effortlessly from intimate gatherings to grand celebrations.', 'Discover New Arrivals', '#new-arrivals', 'https://images.unsplash.com/photo-1622445275576-721325763afe?auto=format&fit=crop&w=2200&q=90');