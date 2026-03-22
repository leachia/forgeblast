<?php
// GOOGLE OAUTH CONFIGURATION (Loads from .env for security)
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
    define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
    define('GOOGLE_REDIRECT_URL', 'http://localhost/emailblast/google_callback.php');
}

// Scopes required for Gmail sending
define('GOOGLE_SCOPES', 'https://www.googleapis.com/auth/gmail.send');
?>
