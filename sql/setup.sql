-- Create Database
CREATE DATABASE IF NOT EXISTS `auction_platform` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `auction_platform`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(50),
    `last_name` VARCHAR(50),
    `balance` DECIMAL(10, 2) DEFAULT 0,
    `is_admin` BOOLEAN DEFAULT FALSE,
    `status` ENUM('active', 'suspended', 'pending') DEFAULT 'active',
    `email_verified` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auctions Table
CREATE TABLE IF NOT EXISTS `auctions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `image_url` VARCHAR(500),
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `ticket_price` DECIMAL(10, 2) NOT NULL,
    `total_tickets` INT NOT NULL,
    `tickets_sold` INT DEFAULT 0,
    `bids_per_ticket` INT DEFAULT 5,
    `guaranteed` BOOLEAN DEFAULT TRUE,
    `status` ENUM('upcoming', 'active', 'completed', 'cancelled') DEFAULT 'upcoming',
    `winner_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`winner_id`) REFERENCES `users`(`id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_end_time` (`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Tickets Table
CREATE TABLE IF NOT EXISTS `user_tickets` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `auction_id` INT NOT NULL,
    `tickets_count` INT NOT NULL,
    `total_bids` INT NOT NULL,
    `used_bids` INT DEFAULT 0,
    `purchase_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`auction_id`) REFERENCES `auctions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_auction` (`user_id`, `auction_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_auction_id` (`auction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bids Table
CREATE TABLE IF NOT EXISTS `bids` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `auction_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `bid_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`auction_id`) REFERENCES `auctions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_auction_id` (`auction_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_bid_time` (`bid_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions Table
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `auction_id` INT,
    `type` ENUM('deposit', 'ticket_purchase', 'refund', 'withdrawal', 'admin_adjustment') NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    `description` TEXT,
    `payment_method` VARCHAR(50),
    `transaction_id` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`auction_id`) REFERENCES `auctions`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Methods Table
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `card_last_four` VARCHAR(4),
    `card_brand` VARCHAR(50),
    `is_default` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Sample Admin User
INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `balance`, `is_admin`, `status`, `email_verified`)
VALUES ('admin', 'admin@auctionbay.com', '$2y$10$9fRNWAyEBp/4W5A9L7EBZeFmPJqNjQ5HYgME2dR.9g1V1VJ3W0yZm', 'Admin', 'User', 1000, TRUE, 'active', TRUE)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Insert Sample Regular User
INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `balance`, `is_admin`, `status`, `email_verified`)
VALUES ('user', 'user@auctionbay.com', '$2y$10$9fRNWAyEBp/4W5A9L7EBZeFmPJqNjQ5HYgME2dR.9g1V1VJ3W0yZm', 'Test', 'User', 500, FALSE, 'active', TRUE)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Insert Sample Auctions
INSERT INTO `auctions` (`title`, `description`, `image_url`, `start_time`, `end_time`, `ticket_price`, `total_tickets`, `bids_per_ticket`, `guaranteed`, `status`)
VALUES 
    ('iPhone 16 Pro Max', 'Latest Apple iPhone 16 Pro Max 256GB Space Black', 'https://via.placeholder.com/400x300?text=iPhone+16', NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), 5.00, 500, 5, TRUE, 'active'),
    ('PlayStation 5 Slim', 'Sony PS5 Slim Console with Controller', 'https://via.placeholder.com/400x300?text=PS5+Slim', DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 3 HOUR), 4.00, 300, 5, TRUE, 'upcoming'),
    ('MacBook Pro M4', 'Apple MacBook Pro 16" M4 Max', 'https://via.placeholder.com/400x300?text=MacBook+Pro', DATE_ADD(NOW(), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 6 HOUR), 10.00, 200, 5, TRUE, 'upcoming'),
    ('Samsung 65" 4K TV', 'Samsung QN65QN90DAFXZA 65-Inch QLED', 'https://via.placeholder.com/400x300?text=Samsung+TV', DATE_ADD(NOW(), INTERVAL 8 HOUR), DATE_ADD(NOW(), INTERVAL 10 HOUR), 3.00, 400, 5, TRUE, 'upcoming'),
    ('DJI Air 3S Drone', 'DJI Air 3S 4K Camera Drone', 'https://via.placeholder.com/400x300?text=DJI+Drone', DATE_ADD(NOW(), INTERVAL 12 HOUR), DATE_ADD(NOW(), INTERVAL 14 HOUR), 6.00, 250, 5, TRUE, 'upcoming'),
    ('Rolex Submariner', 'Rolex Submariner Stainless Steel Watch', 'https://via.placeholder.com/400x300?text=Rolex+Watch', DATE_ADD(NOW(), INTERVAL 16 HOUR), DATE_ADD(NOW(), INTERVAL 18 HOUR), 15.00, 100, 5, TRUE, 'upcoming');
