<?php
require_once 'db.php';
$res = $conn->query("SELECT id, name, email, role, referred_by_admin_id FROM users");
while($u = $res->fetch_assoc()) {
    echo "ID: {$u['id']} | Name: {$u['name']} | Email: {$u['email']} | Role: {$u['role']} | Referrer: {$u['referred_by_admin_id']}\n";
}
?>
