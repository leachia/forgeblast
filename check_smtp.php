<?php
require_once 'config.php';
$res = $conn->query("SELECT id, name, email, smtp_host, smtp_user, smtp_pass FROM users");
while($row = $res->fetch_assoc()) {
    echo "ID: ". $row['id'] . " | Name: ". $row['name'] . " | SMTP Host: ". $row['smtp_host'] . "\n";
}
