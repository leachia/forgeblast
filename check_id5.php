<?php
require_once 'config.php';
$res = $conn->query("SELECT * FROM users WHERE id = 5");
print_r($res->fetch_assoc());
?>
