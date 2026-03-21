<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'emailblast_db');

$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS age INT DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS gender VARCHAR(20) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS id_info TEXT DEFAULT NULL");

$conn->close();
echo "Migration Done";
?>
