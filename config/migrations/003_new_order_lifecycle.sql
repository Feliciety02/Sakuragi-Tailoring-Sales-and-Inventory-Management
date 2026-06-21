-- Migration 003: New Order Lifecycle
-- Adds NCR tracking, inventory reservations, and updates order structure

-- NCR (Non-Conformance Report) table — formal QC failure documentation
CREATE TABLE IF NOT EXISTS `ncr_reports` (
  `ncr_id`          BIGINT AUTO_INCREMENT PRIMARY KEY,
  `ncr_number`      VARCHAR(20) NOT NULL UNIQUE,
  `order_id`        BIGINT NOT NULL,
  `inspector_id`    BIGINT DEFAULT NULL,
  `stage_at_fault`  VARCHAR(100) NOT NULL,
  `defect_type`     ENUM('major','minor','critical') NOT NULL DEFAULT 'major',
  `description`     TEXT NOT NULL,
  `root_cause`      TEXT DEFAULT NULL,
  `corrective_action` TEXT DEFAULT NULL,
  `assigned_to`     BIGINT DEFAULT NULL,
  `status`          ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`     DATETIME DEFAULT NULL,
  INDEX `idx_ncr_order` (`order_id`),
  INDEX `idx_ncr_status` (`status`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
  FOREIGN KEY (`inspector_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory reservations — material held for a specific order
CREATE TABLE IF NOT EXISTS `inventory_reservations` (
  `reservation_id`   BIGINT AUTO_INCREMENT PRIMARY KEY,
  `order_id`         BIGINT NOT NULL,
  `inventory_id`     BIGINT NOT NULL,
  `reserved_qty`     DECIMAL(10,2) NOT NULL DEFAULT 0,
  `unit`             VARCHAR(20) DEFAULT 'piece',
  `status`           ENUM('reserved','consumed','released') DEFAULT 'reserved',
  `reserved_by`      BIGINT DEFAULT NULL,
  `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  `consumed_at`      DATETIME DEFAULT NULL,
  INDEX `idx_ir_order` (`order_id`),
  INDEX `idx_ir_inventory` (`inventory_id`),
  UNIQUE KEY `uq_ir_order_inv` (`order_id`, `inventory_id`, `status`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
  FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`inventory_id`) ON DELETE CASCADE,
  FOREIGN KEY (`reserved_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link NCR to rework_log
ALTER TABLE `rework_log`
  ADD COLUMN `ncr_id` BIGINT DEFAULT NULL AFTER `notes`,
  ADD INDEX `idx_rl_ncr` (`ncr_id`),
  ADD FOREIGN KEY (`ncr_id`) REFERENCES `ncr_reports`(`ncr_id`) ON DELETE SET NULL;

-- Add release tracking to orders
ALTER TABLE `orders`
  ADD COLUMN `released_at` DATETIME DEFAULT NULL AFTER `completion_date`,
  ADD COLUMN `released_by` BIGINT DEFAULT NULL AFTER `released_at`,
  ADD INDEX `idx_orders_released` (`released_at`),
  ADD FOREIGN KEY (`released_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL;
