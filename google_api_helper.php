<?php
require_once 'google_config.php';
require_once 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Refreshes the Google Access Token if expired.
 */
function refreshGoogleToken($userId, $conn) {
    $res = $conn->query("SELECT google_access_token, google_refresh_token, google_token_expiry FROM users WHERE id = $userId");
    $u = $res->fetch_assoc();

    if (empty($u['google_refresh_token'])) return null;

    // If token expires in < 60 seconds, refresh it
    if ($u['google_token_expiry'] < time() + 60) {
        $token_url = "https://oauth2.googleapis.com/token";
        $post_data = [
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => $u['google_refresh_token'],
            'grant_type' => 'refresh_token'
        ];

        $ch = curl_init($token_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        curl_close($ch);

        if (isset($data['access_token'])) {
            $newToken = $conn->real_escape_string($data['access_token']);
            $newExpiry = time() + intval($data['expires_in']);
            $conn->query("UPDATE users SET google_access_token = '$newToken', google_token_expiry = $newExpiry WHERE id = $userId");
            return $newToken;
        }
    }
    return $u['google_access_token'];
}

/**
 * Sends an email via Gmail API using OAuth2 token.
 */
function sendGmailApi($userId, $toEmail, $toName, $subject, $content, $attachments = [], $conn) {
    $token = refreshGoogleToken($userId, $conn);
    if (!$token) return false;

    // Build the MIME message using PHPMailer (it's already included in the project)
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $content;

        foreach ($attachments as $att) {
            if (isset($att['path']) && file_exists($att['path'])) {
                $mail->addAttachment($att['path'], $att['original_name'] ?? basename($att['path']));
            }
        }

        // We need the raw RFC 2822 message. PHPMailer's preSend() generates it.
        $mail->preSend();
        $mime = $mail->getSentMIMEMessage();

        // Encode to Base64URL
        $raw = strtr(base64_encode($mime), '+/', '-_');
        $raw = rtrim($raw, '=');

        $url = "https://gmail.googleapis.com/gmail/v1/users/me/messages/send";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $raw]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200);
    } catch (Exception $e) {
        return false;
    }
}
?>
