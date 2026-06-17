<?php
require_once __DIR__ . '/db_connect.php';

echo "Running sample approval migration...\n";

try {
    // Add sample_status to order_workflow
    $pdo->exec("ALTER TABLE order_workflow 
        ADD COLUMN IF NOT EXISTS `sample_status` enum('not_required','pending','submitted','approved','rejected') DEFAULT 'not_required' AFTER `completed_at`,
        ADD COLUMN IF NOT EXISTS `sample_submitted_at` datetime DEFAULT NULL AFTER `sample_status`");

    // Create sample_approvals table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sample_approvals` (
        `approval_id` bigint(20) NOT NULL AUTO_INCREMENT,
        `order_id` bigint(20) NOT NULL,
        `submitted_by` bigint(20) DEFAULT NULL,
        `reviewed_by` bigint(20) DEFAULT NULL,
        `status` enum('pending','approved','rejected') DEFAULT 'pending',
        `notes` text DEFAULT NULL,
        `rejection_reason` text DEFAULT NULL,
        `corrections_needed` text DEFAULT NULL,
        `file_path` varchar(255) DEFAULT NULL,
        `submitted_at` datetime DEFAULT current_timestamp(),
        `reviewed_at` datetime DEFAULT NULL,
        PRIMARY KEY (`approval_id`),
        KEY `order_id` (`order_id`),
        KEY `submitted_by` (`submitted_by`),
        KEY `reviewed_by` (`reviewed_by`),
        CONSTRAINT `sa_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
        CONSTRAINT `sa_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`),
        CONSTRAINT `sa_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Add sample_stage to constants if not already
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
