-- LMS Inventory System Database Update Migration Script
-- Run this on your MySQL database to add necessary inventory fields

ALTER TABLE `catalog_titles` 
ADD COLUMN `category` VARCHAR(100) DEFAULT 'Circulation',
ADD COLUMN `imprint` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `copyright_year` VARCHAR(20) DEFAULT NULL,
ADD COLUMN `shelf_location` VARCHAR(100) DEFAULT NULL;

-- Optional: Index on category for faster tab-filtering
CREATE INDEX `idx_catalog_titles_category` ON `catalog_titles` (`category`);
