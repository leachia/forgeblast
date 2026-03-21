<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'emailblast_db');
$res = $conn->query("SHOW COLUMNS FROM users");
while($row = $res->fetch_assoc()) echo $row['Field'] . ", ";
?>
