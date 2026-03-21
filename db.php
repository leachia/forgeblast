<?php
// Centralized Database & Security Layer
require_once 'config.php';

// ── Core Table Definitions ───────────────────────────────────────────────────
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        firstName VARCHAR(100) DEFAULT NULL,
        lastName VARCHAR(100) DEFAULT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
        status ENUM('active', 'suspended') DEFAULT 'active',
        avatar VARCHAR(500) DEFAULT 'https://ui-avatars.com/api/?background=random',
        bio TEXT,
        age INT DEFAULT NULL,
        gender VARCHAR(20) DEFAULT NULL,
        birthday DATE DEFAULT NULL,
        location VARCHAR(255) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        facebook VARCHAR(500) DEFAULT NULL,
        instagram VARCHAR(500) DEFAULT NULL,
        gmail VARCHAR(255) DEFAULT NULL,
        id_info TEXT DEFAULT NULL,
        referral_code VARCHAR(20) UNIQUE,
        referred_by_admin_id INT DEFAULT NULL,
        smtp_host VARCHAR(255) DEFAULT NULL,
        smtp_user VARCHAR(255) DEFAULT NULL,
        smtp_pass VARCHAR(255) DEFAULT NULL,
        smtp_port INT DEFAULT NULL,
        google_access_token TEXT DEFAULT NULL,
        google_refresh_token TEXT DEFAULT NULL,
        google_token_expiry INT DEFAULT NULL,
        otp_code VARCHAR(6) DEFAULT NULL,
        is_verified TINYINT(1) DEFAULT 0,
        is_online TINYINT(1) DEFAULT 0,
        dark_mode TINYINT(1) DEFAULT 0,
        email_notifications TINYINT(1) DEFAULT 1,
        last_login DATETIME DEFAULT NULL,
        last_seen DATETIME DEFAULT NULL,
        last_activity DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (referred_by_admin_id) REFERENCES users(id) ON DELETE SET NULL
    )",
    "subscribers" => "CREATE TABLE IF NOT EXISTS subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        status ENUM('active', 'unsubscribed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_email (user_id, email),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "campaigns" => "CREATE TABLE IF NOT EXISTS campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        attachments TEXT DEFAULT NULL,
        sent_count INT DEFAULT 0,
        status ENUM('draft', 'pending', 'sent', 'rejected') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "user_logs" => "CREATE TABLE IF NOT EXISTS user_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action ENUM('login','logout','register','profile_update','campaign_sent') NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "activity_logs" => "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255),
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "email_templates" => "CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        subject VARCHAR(255),
        body TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "rate_limiting" => "CREATE TABLE IF NOT EXISTS rate_limiting (
        ip_address VARCHAR(45),
        action VARCHAR(50),
        attempt_count INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (ip_address, action)
    )"
];

foreach ($tables as $name => $sql) {
    if (!$conn->query($sql)) {
        error_log("Failed to create table $name: " . $conn->error);
    }
}

// ── Helper to log user actions ────────────────────────────────────────────────
function logUserAction($conn, $userId, $action, $notes = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 499);
    $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address, user_agent, notes) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issss", $userId, $action, $ip, $ua, $notes);
        $stmt->execute();
    }
}

// ── Migration: Safely add columns that may not exist yet ─────────────────────
$migrations = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS firstName VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS lastName VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','suspended') DEFAULT 'active'",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS age INT DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS gender VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS birthday DATE DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS facebook VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS instagram VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS gmail VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS id_info TEXT DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen DATETIME DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_activity DATETIME DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_access_token TEXT DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_refresh_token TEXT DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_token_expiry INT DEFAULT NULL",
];

foreach ($migrations as $m) {
    $conn->query($m); // Silently attempt each migration
}
