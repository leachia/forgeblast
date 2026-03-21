<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'emailblast_db');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_access_token TEXT DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_refresh_token VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_token_expiry INT DEFAULT NULL"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Query success: $q\n";
    } else {
        // Fallback for older MySQL without IF NOT EXISTS on columns
        $conn->query(str_replace(" ADD COLUMN IF NOT EXISTS", " ADD COLUMN", $q));
        echo "Query run (fallback): $q\n";
    }
}
$conn->close();
?>
