<?php
require_once 'config.php';

echo "🚀 Starting Enterprise Migration (v2.0)\n";

// 1. Update Users Table for Monthly Limits
$queries[] = "ALTER TABLE users 
              ADD COLUMN IF NOT EXISTS monthly_limit INT DEFAULT 60000,
              ADD COLUMN IF NOT EXISTS sent_count_this_month INT DEFAULT 0,
              ADD COLUMN IF NOT EXISTS last_reset_month CHAR(7) DEFAULT NULL"; // e.g. '2026-03'

// 2. Update Campaigns Table for Queue Support
$queries[] = "ALTER TABLE campaigns 
              ADD COLUMN IF NOT EXISTS total_targets INT DEFAULT 0,
              ADD COLUMN IF NOT EXISTS sent_success INT DEFAULT 0,
              ADD COLUMN IF NOT EXISTS sent_failed INT DEFAULT 0,
              MODIFY COLUMN status ENUM('draft','queued','sending','completed','paused','sent','rejected') DEFAULT 'draft'";

// 3. Create Email Queue Table
$queries[] = "CREATE TABLE IF NOT EXISTS email_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255),
                status ENUM('pending','processing','sent','failed') DEFAULT 'pending',
                error_message TEXT,
                attempt_count INT DEFAULT 0,
                processed_at TIMESTAMP NULL,
                INDEX (status),
                INDEX (campaign_id)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "[SUCCESS] Query processed.\n";
    } else {
        echo "[ERROR] " . $conn->error . "\n";
    }
}

echo "✅ Migration Complete.\n";
