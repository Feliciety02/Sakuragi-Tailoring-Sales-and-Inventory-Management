<?php
/**
 * AQL Sampling QC Migration
 * Adds lot-based AQL (Acceptable Quality Level) inspection tables
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

    // AQL Configuration per order
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `aql_config` (
            `aql_config_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `order_id` BIGINT NOT NULL,
            `aql_level` VARCHAR(10) DEFAULT '2.5',
            `inspection_level` VARCHAR(10) DEFAULT 'II',
            `critical_allowed` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
            UNIQUE KEY `uq_order` (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Lot-based QC inspections
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `qc_lot_inspections` (
            `lot_inspection_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `order_id` BIGINT NOT NULL,
            `workflow_id` BIGINT DEFAULT NULL,
            `inspector_id` BIGINT DEFAULT NULL,
            `lot_size` INT NOT NULL DEFAULT 0,
            `sample_size` INT NOT NULL DEFAULT 0,
            `aql_level` VARCHAR(10) DEFAULT '2.5',
            `critical_defects` INT DEFAULT 0,
            `major_defects` INT DEFAULT 0,
            `minor_defects` INT DEFAULT 0,
            `verdict` ENUM('Passed','Failed','Pending') DEFAULT 'Pending',
            `notes` TEXT DEFAULT NULL,
            `inspected_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
            FOREIGN KEY (workflow_id) REFERENCES order_workflow(workflow_id) ON DELETE SET NULL,
            FOREIGN KEY (inspector_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Individual sample items inspected within a lot
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `qc_lot_items` (
            `lot_item_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `lot_inspection_id` BIGINT NOT NULL,
            `order_detail_id` BIGINT DEFAULT NULL,
            `item_label` VARCHAR(100) DEFAULT NULL,
            `critical_defects` INT DEFAULT 0,
            `major_defects` INT DEFAULT 0,
            `minor_defects` INT DEFAULT 0,
            `defect_details` TEXT DEFAULT NULL,
            `passed` TINYINT(1) DEFAULT 1,
            `notes` TEXT DEFAULT NULL,
            FOREIGN KEY (lot_inspection_id) REFERENCES qc_lot_inspections(lot_inspection_id) ON DELETE CASCADE,
            FOREIGN KEY (order_detail_id) REFERENCES order_details(order_detail_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "AQL QC migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
