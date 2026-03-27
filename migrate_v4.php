<?php
require_once 'db.php';

// 1. Alter Users Table Role ENUM to include 'staff'
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'staff', 'admin', 'super_admin') DEFAULT 'user'");

// 2. Create Internal Messages Inbox Table
$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(receiver_id) REFERENCES users(id) ON DELETE CASCADE
)");

echo "Migration v4 Completed Successfully. Role ENUM updated and Messages table added.";
?>
