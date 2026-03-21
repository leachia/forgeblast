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

    public static function log($conn, $user_id, $action, $details = "") {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $action, $details, $ip);
        $stmt->execute();
    }

    public static function clean($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}
