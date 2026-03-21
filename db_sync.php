<?php
require_once 'config.php';
header('Content-Type: text/plain');

$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS smtp_pass VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS smtp_port INT DEFAULT 587",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by_admin_id INT DEFAULT NULL",
    "ALTER TABLE campaigns ADD COLUMN IF NOT EXISTS template VARCHAR(50) DEFAULT NULL"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "SYNC SUCCESS: " . substr($q, 0, 30) . "...\n";
    } else {
        echo "SYNC ERROR: " . $conn->error . "\n";
    }
}

echo "\n--- DATABASE PROTOCOL SYNCED ---";
?>
