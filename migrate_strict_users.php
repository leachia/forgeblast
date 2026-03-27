<?php
require_once 'db.php';

// Strict enforcement on database level
$conn->query("UPDATE users SET email = 'temp_email_' || id WHERE email = '' OR email IS NULL"); // Just in case any are left
$conn->query("ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NOT NULL");

echo "Database Level constraints updated: Email set to NOT NULL.";
?>
