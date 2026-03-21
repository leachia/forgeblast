<?php
$c = new mysqli('127.0.0.1', 'root', '', 'emailblast_db');
$res = $c->query('SHOW CREATE TABLE users');
$r = $res->fetch_row();
echo $r[1];
?>
