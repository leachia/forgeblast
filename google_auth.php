<?php
require_once 'config.php';
require_once 'google_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URL,
    'response_type' => 'code',
    'scope' => GOOGLE_SCOPES,
    'access_type' => 'offline',
    'prompt' => 'consent' // Forces refresh_token generation
]);

header("Location: $auth_url");
exit;
?>
