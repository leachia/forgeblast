<?php
$c = new mysqli('127.0.0.1', 'root', '');
$c->query('DROP DATABASE IF EXISTS emailblast_db');
echo "Database Dropped. The next time you open the app, it will recreate standard SAAS columns.";
?>
