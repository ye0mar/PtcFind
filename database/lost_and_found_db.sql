-- =========================================================
-- LOST & FOUND MANAGEMENT SYSTEM (FoundU)
-- Database: lost_and_found_db1
-- Compatible with MySQL 5.7+ / MySQL 8.0+ / MariaDB / phpMyAdmin
-- =========================================================

CREATE DATABASE IF NOT EXISTS `lost_and_found_db1` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `lost_and_found_db1`;

-- ---------------------------------------------------------
-- USERS TABLE
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(120) NOT NULL,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- ITEM CATEGORIES
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- LOST ITEMS
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lost_items` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_name` VARCHAR(150) NOT NULL,
  `category_id` INT(11) UNSIGNED DEFAULT NULL,
  `description` TEXT,
  `date_lost` DATE NOT NULL,
  `location_lost` VARCHAR(180) DEFAULT NULL,
  `contact_info` VARCHAR(180) DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('open','matched','returned','closed') NOT NULL DEFAULT 'open',
  `reported_by` INT(11) UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category_id`),
  KEY `idx_reported_by` (`reported_by`),
  CONSTRAINT `fk_lost_reporter` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_lost_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- FOUND ITEMS
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `found_items` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_name` VARCHAR(150) NOT NULL,
  `category_id` INT(11) UNSIGNED DEFAULT NULL,
  `description` TEXT,
  `date_found` DATE NOT NULL,
  `location_found` VARCHAR(180) DEFAULT NULL,
  `storage_location` VARCHAR(180) DEFAULT NULL,
  `notes` TEXT,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('available','matched','returned','closed') NOT NULL DEFAULT 'available',
  `recorded_by` INT(11) UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category_id`),
  KEY `idx_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_found_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_found_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- MATCHES (links lost <-> found)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `matches` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `lost_item_id` INT(11) UNSIGNED NOT NULL,
  `found_item_id` INT(11) UNSIGNED NOT NULL,
  `confirmed_by` INT(11) UNSIGNED NOT NULL,
  `notes` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lost` (`lost_item_id`),
  KEY `idx_found` (`found_item_id`),
  CONSTRAINT `fk_match_lost` FOREIGN KEY (`lost_item_id`) REFERENCES `lost_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_match_found` FOREIGN KEY (`found_item_id`) REFERENCES `found_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_match_user` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- ACTIVITY LOG
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `target_type` VARCHAR(60) DEFAULT NULL,
  `target_id` INT(11) UNSIGNED DEFAULT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- SYSTEM SETTINGS
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` TEXT,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- BACKUPS LOG
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `backup_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `size_bytes` BIGINT UNSIGNED DEFAULT 0,
  `action` ENUM('backup','restore') NOT NULL,
  `status` ENUM('success','failed') NOT NULL,
  `notes` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_backup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- ROLE-BASED NOTIFICATIONS
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient_user_id` INT(11) UNSIGNED NOT NULL,
  `sender_user_id` INT(11) UNSIGNED NOT NULL,
  `sender_role` ENUM('admin','staff') NOT NULL,
  `related_item_id` INT(11) UNSIGNED NOT NULL,
  `related_item_type` ENUM('lost_item','found_item') NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_notification_recipient_item` (`recipient_user_id`, `sender_user_id`, `related_item_type`, `related_item_id`),
  KEY `idx_recipient_read` (`recipient_user_id`, `is_read`, `created_at`),
  KEY `idx_sender_user` (`sender_user_id`),
  CONSTRAINT `fk_notif_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- SEED DATA
-- Default password for 'admin' is: admin123
-- Default password for 'staff' is: staff123
-- ---------------------------------------------------------
INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `password_hash`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin', 'admin@foundu.edu', '$2y$12$H/5jeQskHbOpzuAmqRx20erx/N9lCHuDZKdQGMMtcr9V9WX/t2xJC', 'admin', 'active'),
(2, 'Campus Staff Officer', 'staff', 'staff@foundu.edu', '$2y$12$VPlN3q5mzujE2XhINqXXvOxFsL/n/qDcTjU8yZwcq/N54N/73xRUK', 'staff', 'active')
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Electronics'),
(2, 'Bags & Wallets'),
(3, 'Clothing & Accessories'),
(4, 'Books & Documents'),
(5, 'Keys & IDs'),
(6, 'Jewelry & Watches'),
(7, 'Sports Equipment'),
(8, 'Toys & Games'),
(9, 'Medicine & Health'),
(10, 'Others')
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('system_name', 'Lost & Found Management System'),
('organization_name', 'Polytechnic Campus Administration'),
('default_status_lost', 'open'),
('default_status_found', 'available'),
('items_per_page', '15')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

INSERT INTO `lost_items` (`id`, `item_name`, `category_id`, `description`, `date_lost`, `location_lost`, `contact_info`, `status`, `reported_by`) VALUES
(1, 'MacBook Pro 14"', 1, 'Space Gray, sticker of campus tech club on back lid', '2026-11-15', 'Main Library - 2nd Floor', 'john.student@foundu.edu', 'open', 1),
(2, 'Brown Leather Bi-fold Wallet', 2, 'Contains student ID and transit card', '2026-11-16', 'University Cafeteria', 'sarah.k@foundu.edu', 'open', 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `found_items` (`id`, `item_name`, `category_id`, `description`, `date_found`, `location_found`, `storage_location`, `notes`, `status`, `recorded_by`) VALUES
(1, 'Apple Laptop (MacBook)', 1, 'Space Gray MacBook found in library study lounge 4', '2026-11-15', 'Library 2nd Floor', 'Security Locker #12', 'Turned in by student assistant', 'available', 1),
(2, 'Blue Hydro Flask Bottle', 10, '32oz water bottle with mountain climbing stickers', '2026-11-17', 'Gymnasium Bleachers', 'Storage Bin A-3', 'Cleaned and safely stored', 'available', 1)
ON DUPLICATE KEY UPDATE `id`=`id`;
