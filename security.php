<?php
/**
 * BlastForge Firewall Engine v2.0
 */
class Security {
    public static function generateCSRF() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRF($token) {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
    }

    // 🛡️ ELITE ENCRYPTION KEY (Define in your .env or server config)
    private static $ENCRYPTION_KEY = "BLASTFORGE-ENT-SUPER-SECRET-KEY-123456";

    public static function encrypt($data) {
        if (empty($data)) return null;
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', self::$ENCRYPTION_KEY, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($data) {
        if (empty($data)) return null;
        $decoded = base64_decode($data);
        $iv_len = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decoded, 0, $iv_len);
        $encrypted = substr($decoded, $iv_len);
        return openssl_decrypt($encrypted, 'aes-256-cbc', self::$ENCRYPTION_KEY, 0, $iv);
    }

    public static function log($conn, $user_id, $action, $details = "", $old = null, $new = null) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $oldJSON = $old ? json_encode($old) : NULL;
        $newJSON = $new ? json_encode($new) : NULL;
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $action, $details, $ip, $oldJSON, $newJSON);
        $stmt->execute();
    }

    public static function clean($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}
