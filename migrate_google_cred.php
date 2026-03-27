<?php
require_once 'db.php';

// Add Google Client ID and Client Secret columns to users table
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS google_client_id VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS google_client_secret VARCHAR(255) DEFAULT NULL");

echo "Migration Successful: Google OAuth credentials columns added to users table.";
?>
