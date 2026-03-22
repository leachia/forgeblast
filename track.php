<?php
/**
 * 🕵️ BLASTFORGE ANALYTICS TRACKER v1.0
 * Handles: Email Open Pixel & Click Tracking Redirects
 */
require_once 'config.php';

$cid = intval($_GET['cid'] ?? 0);
$qid = intval($_GET['qid'] ?? 0);
$type = $_GET['type'] ?? 'open';

if ($cid > 0 && $qid > 0) {
    if ($type === 'open') {
        // Log Open Event
        $conn->query("UPDATE email_queue SET is_opened = 1, opened_at = NOW() WHERE id = $qid AND is_opened = 0");
        if ($conn->affected_rows > 0) {
            $conn->query("UPDATE campaigns SET opens_count = opens_count + 1 WHERE id = $cid");
        }
        
        // Serve 1x1 Transparent GIF
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    } 
    
    if ($type === 'click' && isset($_GET['url'])) {
        $url = $_GET['url'];
        // Log Click Event
        $conn->query("UPDATE email_queue SET is_clicked = 1 WHERE id = $qid");
        $conn->query("UPDATE campaigns SET clicks_count = clicks_count + 1 WHERE id = $cid");
        
        // Redirect to original URL
        header("Location: $url");
        exit;
    }
}

// Default fallback
header('Content-Type: text/plain');
echo "Silent Monitor v1.0";
?>
