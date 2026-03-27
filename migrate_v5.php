<?php
require_once 'db.php';

$sql = "CREATE TABLE IF NOT EXISTS registration_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password_attempt VARCHAR(255),
    otp_attempt VARCHAR(20),
    referral_code VARCHAR(50),
    referrer_id INT,
    attempts_count INT DEFAULT 1,
    is_banned TINYINT(1) DEFAULT 0,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (referrer_id)
)";

if ($conn->query($sql)) {
    echo "Table 'registration_attempts' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
