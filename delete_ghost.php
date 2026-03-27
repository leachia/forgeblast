<?php
require_once 'config.php';
$conn->query("DELETE FROM users WHERE id = 5 OR email = '' OR email IS NULL");
echo "Ghost accounts deleted. Current count: " . $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
?>
