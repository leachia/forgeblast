<?php
require_once 'db.php';

$enterprise_migrations = [
    // 🚀 Advanced Queue & Worker
    "ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS priority INT DEFAULT 0",
    "ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS retry_count INT DEFAULT 0",
    "ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS next_retry_at DATETIME DEFAULT NULL",
    "ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS worker_instance_id VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS engagement_hour INT DEFAULT NULL",
    "ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    
    // 📊 Rich Campaign Analytics
    "ALTER TABLE campaigns ADD COLUMN IF NOT EXISTS adaptive_speed INT DEFAULT 100000", // in microseconds
    "ALTER TABLE campaigns ADD COLUMN IF NOT EXISTS deliverability_score DECIMAL(5,2) DEFAULT 100.00",
    "ALTER TABLE campaigns ADD COLUMN IF NOT EXISTS category VARCHAR(50) DEFAULT 'general'",
    "ALTER TABLE campaigns ADD COLUMN IF NOT EXISTS priority INT DEFAULT 0",
    
    // ⛓️ Immutable Audit Logs (Chained Hashing)
    "ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS log_hash VARCHAR(64) DEFAULT NULL",
    "ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS prev_log_hash VARCHAR(64) DEFAULT NULL",
    
    // 🛡️ Enhanced Security & User Roles
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS global_config_access TINYINT(1) DEFAULT 0",
    
    // 🧬 Lead Scoring & Intelligence
    "ALTER TABLE subscribers ADD COLUMN IF NOT EXISTS lead_score INT DEFAULT 0",
    "ALTER TABLE subscribers ADD COLUMN IF NOT EXISTS last_engagement DATETIME DEFAULT NULL",
    "ALTER TABLE subscribers ADD COLUMN IF NOT EXISTS tags TEXT DEFAULT NULL",
    
    // 📈 Global Performance Matrix & Alerts
    "CREATE TABLE IF NOT EXISTS system_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('critical', 'warning', 'info') DEFAULT 'info',
        source VARCHAR(50),
        message TEXT,
        is_resolved TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS global_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "INSERT IGNORE INTO global_settings (setting_key, setting_value) VALUES ('worker_parallel_limit', '5')",
    "INSERT IGNORE INTO global_settings (setting_key, setting_value) VALUES ('global_send_limit', '10000')",
    "INSERT IGNORE INTO global_settings (setting_key, setting_value) VALUES ('security_strict_mode', '1')",
    
    // 🏊‍♂️ SMTP Failover Pool (HIGH AVAILABILITY)
    "CREATE TABLE IF NOT EXISTS smtp_pools (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        host VARCHAR(255),
        port INT,
        username VARCHAR(255),
        password_encrypted TEXT,
        is_active TINYINT(1) DEFAULT 1,
        success_count INT DEFAULT 0,
        fail_count INT DEFAULT 0
    )"
];

echo "Starting Enterprise Migrations...\n";
foreach ($enterprise_migrations as $m) {
    if ($conn->query($m)) {
        echo "✅ Success: " . substr($m, 0, 50) . "...\n";
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }
}
echo "Enterprise Migrations Complete.\n";
