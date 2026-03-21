<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'emailblast_db');

$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS firstName VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS lastName VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS birthday DATE DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS facebook VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS instagram VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS gmail VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications TINYINT(1) DEFAULT 1",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS dark_mode TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS documents_uploaded TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture_uploaded TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS birthday_added TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS social_links_added TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS has_seen_tour TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'active'",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_activity TIMESTAMP NULL DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_hash VARCHAR(64) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expires_at TIMESTAMP NULL DEFAULT NULL"
];

foreach ($queries as $q) {
    if (!$conn->query($q)) {
        $conn->query(str_replace(" ADD COLUMN IF NOT EXISTS", " ADD COLUMN", $q));
    }
}

// Split 'name' into firstName and lastName for existing users
$res = $conn->query("SELECT id, name FROM users WHERE firstName IS NULL");
while ($row = $res->fetch_assoc()) {
    $parts = explode(' ', $row['name'], 2);
    $f = $conn->real_escape_string($parts[0] ?? '');
    $l = $conn->real_escape_string($parts[1] ?? '');
    $conn->query("UPDATE users SET firstName = '$f', lastName = '$l' WHERE id = " . $row['id']);
}
$conn->close();
echo "Migration Done";
?>
