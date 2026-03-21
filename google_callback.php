<?php
require_once 'db.php';
require_once 'google_config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_GET['code'])) {
    die("No authorization code provided.");
}

$userID = $_SESSION['user_id'] ?? 0;
if ($userID == 0) die("User session not found.");

$code = $_GET['code'];
$token_url = "https://oauth2.googleapis.com/token";

$post_data = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URL,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

if (isset($data['access_token'])) {
    $accessToken = $conn->real_escape_string($data['access_token']);
    $refreshToken = $conn->real_escape_string($data['refresh_token'] ?? ''); // This only comes first time
    $expiry = time() + intval($data['expires_in']);

    $updateSql = "UPDATE users SET google_access_token = '$accessToken', google_token_expiry = $expiry";
    if (!empty($refreshToken)) {
        $updateSql .= ", google_refresh_token = '$refreshToken'";
    }
    $updateSql .= " WHERE id = $userID";
    $conn->query($updateSql);

    header("Location: profile.php?oauth=success");
} else {
    echo "OAuth Error: " . ($data['error_description'] ?? 'Failed to retrieve access token.');
    var_dump($data);
}
?>
