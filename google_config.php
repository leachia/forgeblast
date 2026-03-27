<?php
// GOOGLE OAUTH CONFIGURATION (Loads from .env for security)
if (!defined('GOOGLE_CLIENT_ID')) {
    require_once 'db.php';
    if(session_status() === PHP_SESSION_NONE) session_start();
    
    $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
    $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';

    // Check if logged-in user has their own credentials
    if (isset($_SESSION['user_id'])) {
        $uId = $_SESSION['user_id'];
        $res = $conn->query("SELECT google_client_id, google_client_secret FROM users WHERE id = $uId");
        if ($res && $u = $res->fetch_assoc()) {
            if (!empty($u['google_client_id'])) {
                $clientId = $u['google_client_id'];
                $clientSecret = $u['google_client_secret'];
            }
        }
    }

    define('GOOGLE_CLIENT_ID', $clientId);
    define('GOOGLE_CLIENT_SECRET', $clientSecret);
    define('GOOGLE_REDIRECT_URL', 'http://localhost/emailblast/google_callback.php');
}

// Scopes required for Gmail sending
define('GOOGLE_SCOPES', 'https://www.googleapis.com/auth/gmail.send');
?>
