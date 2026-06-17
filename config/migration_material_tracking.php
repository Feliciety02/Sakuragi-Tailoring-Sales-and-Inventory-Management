<?php
/**
 * Material/Trim Tracking Migration
 * Adds order material allocation and consumption tracking
 */
$host = 'localhost';
$dbname = 'sakuragi_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Order materials allocation
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `order_materials` (
            `allocation_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `order_id` BIGINT NOT NULL,
            `inventory_id` BIGINT NOT NULL,
            `allocated_qty` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `consumed_qty` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `unit` VARCHAR(20) DEFAULT 'piece',
            `notes` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
            FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON DELETE CASCADE,
            UNIQUE KEY `uq_order_inventory` (order_id, inventory_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Material consumption log
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `material_consumption_log` (
            `consumption_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `order_id` BIGINT NOT NULL,
            `inventory_id` BIGINT NOT NULL,
            `allocation_id` BIGINT DEFAULT NULL,
            `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `consumed_by` BIGINT DEFAULT NULL,
            `consumption_type` ENUM('allocated','additional','returned') DEFAULT 'allocated',
            `notes` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
            FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON DELETE CASCADE,
            FOREIGN KEY (allocation_id) REFERENCES order_materials(allocation_id) ON DELETE SET NULL,
            FOREIGN KEY (consumed_by) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "Material tracking migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
