SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'context');
SET @sql := IF(@exist = 0, 'ALTER TABLE reviews ADD COLUMN context VARCHAR(255) NOT NULL DEFAULT '''' AFTER review_text', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;