<?php
require_once 'config.php';
$res = $conn->query("SELECT COUNT(*) FROM subscribers");
echo "Subscribers: ". $res->fetch_row()[0];
