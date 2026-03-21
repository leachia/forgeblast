<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'emailblast_db');

$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS referral_code VARCHAR(100) UNIQUE DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by_admin_id INT DEFAULT NULL");

$res = $conn->query("SELECT id FROM users WHERE role IN ('admin', 'super_admin') AND referral_code IS NULL");
while ($row = $res->fetch_assoc()) {
    $code = 'BLAST' . $row['id'] . strtoupper(substr(md5(uniqid()), 0, 4));
    $conn->query("UPDATE users SET referral_code = '$code' WHERE id = " . $row['id']);
}
echo "Migration Done";
$conn->close();
?>
