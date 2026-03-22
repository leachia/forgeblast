<?php
require_once 'config.php';
$res = $conn->query("SELECT id, email, name, user_id FROM subscribers LIMIT 2");
$subs = $res->fetch_all(MYSQLI_ASSOC);
echo json_encode(["subscribers" => $subs]);
