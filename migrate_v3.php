<?php
require_once 'config.php';

echo "🚀 Starting Elite Security & Stability Migration (v3.0)\n";

// 1. SMTP Vault & Backup Support
$queries[] = "ALTER TABLE users 
              ADD COLUMN IF NOT EXISTS smtp_pass_encrypted TEXT DEFAULT NULL,
              ADD COLUMN IF NOT EXISTS backup_smtp_host VARCHAR(255) DEFAULT NULL,
              ADD COLUMN IF NOT EXISTS backup_smtp_user VARCHAR(255) DEFAULT NULL,
              ADD COLUMN IF NOT EXISTS backup_smtp_pass_encrypted TEXT DEFAULT NULL,
              ADD COLUMN IF NOT EXISTS backup_smtp_port INT DEFAULT 587";

// 2. Advanced Audit Tracking
$queries[] = "ALTER TABLE activity_logs 
              ADD COLUMN IF NOT EXISTS old_data JSON DEFAULT NULL,
              ADD COLUMN IF NOT EXISTS new_data JSON DEFAULT NULL";

// 3. A/B Testing Support for Campaigns
$queries[] = "ALTER TABLE campaigns 
              ADD COLUMN IF NOT EXISTS subject_b VARCHAR(255) DEFAULT NULL,
              ADD COLUMN IF NOT EXISTS subject_b_weight INT DEFAULT 0"; // % weight for B (0-100)

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "[SUCCESS] Query processed.\n";
    } else {
        echo "[ERROR] " . $conn->error . "\n";
    }
}

echo "✅ Migration Complete.\n";
