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

    // 🛡️ ZERO TRUST RE-VERIFICATION ENGINE
    public static function verifyZeroTrust() {
        if (!isset($_SESSION['user_id'])) return true; // Not logged in yet
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'];
        if (!isset($_SESSION['secure_fingerprint'])) {
            $_SESSION['secure_fingerprint'] = hash('sha256', $ip . $ua);
            return true;
        }
        return hash_equals($_SESSION['secure_fingerprint'], hash('sha256', $ip . $ua));
    }

    // 🛡️ ELITE ENCRYPTION KEY (Define in your .env or server config)
    private static $ENCRYPTION_KEY = null;

    private static function get_key() {
        if (self::$ENCRYPTION_KEY === null) {
            self::$ENCRYPTION_KEY = $_ENV['APP_ENCRYPTION_KEY'] ?? "BLASTFORGE-ENT-SUPER-SECRET-KEY-123456";
        }
        return self::$ENCRYPTION_KEY;
    }

    public static function encrypt($data) {
        if (empty($data)) return null;
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', self::get_key(), 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($data) {
        if (empty($data)) return null;
        $decoded = base64_decode($data);
        $iv_len = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decoded, 0, $iv_len);
        $encrypted = substr($decoded, $iv_len);
        return openssl_decrypt($encrypted, 'aes-256-cbc', self::get_key(), 0, $iv);
    }

    public static function log($conn, $user_id, $action, $details = "", $old = null, $new = null) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $oldJSON = $old ? json_encode($old) : NULL;
        $newJSON = $new ? json_encode($new) : NULL;
        
        // ⛓️ CHAINLESS HASHING ENGINE (SIMULATED BLOCKCHAIN AUDIT)
        $prev = $conn->query("SELECT log_hash FROM activity_logs ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $prevHash = $prev['log_hash'] ?? 'GENESIS-BLOCK';
        $currentHash = hash('sha256', $prevHash . $user_id . $action . $details . $ip . time());

        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, old_data, new_data, log_hash, prev_log_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $action, $details, $ip, $oldJSON, $newJSON, $currentHash, $prevHash);
        $stmt->execute();
    }

    public static function clean($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}
