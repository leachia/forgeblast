<?php
require_once 'config.php';
$res = $conn->query("SELECT id, email, role, referral_code FROM users");
while($row = $res->fetch_assoc()) {
    echo "ID: ".$row['id']." | EMAIL: ".$row['email']." | ROLE: ".$row['role']." | REF: ".$row['referral_code']."\n";
}
?>
