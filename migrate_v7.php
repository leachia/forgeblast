<?php
require_once 'db.php';

$sql = "ALTER TABLE users ADD COLUMN is_approved TINYINT(1) DEFAULT 0 AFTER is_verified";
if ($conn->query($sql)) {
    // Standard procedure: Mark existing users as approved
    $conn->query("UPDATE users SET is_approved = 1");
    echo "Column 'is_approved' added and existing users activated.";
} else {
    echo "Error updating table: " . $conn->error;
}
?>
