<?php
$host = 'localhost';
$dbname = 'sakuragi_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Migration 004: Fix missing schema\n\n";

    // 1. Create order_materials table
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
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
            FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`inventory_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK: order_materials\n";

    // 2. Create material_consumption_log table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `material_consumption_log` (
            `log_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `order_id` BIGINT NOT NULL,
            `inventory_id` BIGINT NOT NULL,
            `allocation_id` BIGINT DEFAULT NULL,
            `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `consumed_by` BIGINT DEFAULT NULL,
            `consumption_type` VARCHAR(50) DEFAULT 'consumed',
            `notes` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
            FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`inventory_id`) ON DELETE CASCADE,
            FOREIGN KEY (`allocation_id`) REFERENCES `order_materials`(`allocation_id`) ON DELETE SET NULL,
            FOREIGN KEY (`consumed_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK: material_consumption_log\n";

    // 3. Create garment_tracking table (with corrected FK)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `garment_tracking` (
            `track_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `order_detail_id` BIGINT NOT NULL,
            `order_id` BIGINT NOT NULL,
            `stage` VARCHAR(100) NOT NULL DEFAULT 'Order Received',
            `employee_id` BIGINT DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`order_detail_id`) REFERENCES `order_details`(`order_detail_id`) ON DELETE CASCADE,
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
            FOREIGN KEY (`employee_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK: garment_tracking\n";

    // 4. Create garment_log table (with corrected FK)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `garment_log` (
            `log_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `order_detail_id` BIGINT NOT NULL,
            `order_id` BIGINT NOT NULL,
            `from_stage` VARCHAR(100) DEFAULT NULL,
            `to_stage` VARCHAR(100) NOT NULL,
            `employee_id` BIGINT DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`order_detail_id`) REFERENCES `order_details`(`order_detail_id`) ON DELETE CASCADE,
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
            FOREIGN KEY (`employee_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK: garment_log\n";

    // 5. Add workflow_notes to order_workflow
    $pdo->exec("ALTER TABLE `order_workflow` ADD COLUMN IF NOT EXISTS `workflow_notes` TEXT DEFAULT NULL AFTER `completed_at`");
    echo "  OK: order_workflow.workflow_notes\n";

    // 6. Add proof_file_name to payments
    $pdo->exec("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `proof_file_name` VARCHAR(255) DEFAULT NULL AFTER `proof_file_path`");
    echo "  OK: payments.proof_file_name\n";

    // 7. Add design_file_path to orders
    $pdo->exec("ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `design_file_path` VARCHAR(255) DEFAULT NULL AFTER `design_file_id`");
    echo "  OK: orders.design_file_path\n";

    echo "\nMigration 004 completed successfully!\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
