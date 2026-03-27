<?php
/**
 * 🕵️ BLASTFORGE ANALYTICS TRACKER v2.0 (INTELLIGENT)
 * Handles: Email Open Pixel, Click Tracking, Lead Scoring, Engagement Heatmap
 */
require_once 'config.php';

$cid = intval($_GET['cid'] ?? 0);
$qid = intval($_GET['qid'] ?? 0);
$type = $_GET['type'] ?? 'open';
$hour = date('H'); // 0-23 for Heatmap

if ($cid > 0 && $qid > 0) {
    // 🧬 Identify the Subscriber
    $sub = $conn->query("SELECT s.id, s.email FROM subscribers s JOIN email_queue q ON s.email = q.email WHERE q.id = $qid")->fetch_assoc();
    $sid = $sub['id'] ?? 0;

    if ($type === 'open') {
        // 🚀 SMART UPDATE: Open stats + Engagement Hour + Lead Score
        $conn->query("UPDATE email_queue SET is_opened = 1, opened_at = NOW(), engagement_hour = $hour WHERE id = $qid AND is_opened = 0");
        
        if ($conn->affected_rows > 0) {
            $conn->query("UPDATE campaigns SET opens_count = opens_count + 1 WHERE id = $cid");
            
            // 📈 LEAD SCORING: +2 points for Email Open
            if ($sid) {
                $conn->query("UPDATE subscribers SET lead_score = lead_score + 2, last_engagement = NOW() WHERE id = $sid");
            }
        }
        
        // Serve 1x1 Transparent GIF
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    } 
    
    if ($type === 'click' && isset($_GET['url'])) {
        $url = $_GET['url'];
        // 🚀 SMART UPDATE: Click stats + Lead Score
        $conn->query("UPDATE email_queue SET is_clicked = 1, engagement_hour = $hour WHERE id = $qid AND is_clicked = 0");
        
        if ($conn->affected_rows > 0) {
            $conn->query("UPDATE campaigns SET clicks_count = clicks_count + 1 WHERE id = $cid");
            
            // 📈 LEAD SCORING: +5 points for Click-through
            if ($sid) {
                $conn->query("UPDATE subscribers SET lead_score = lead_score + 5, last_engagement = NOW() WHERE id = $sid");
            }
        }
        
        // Redirect to original URL
        header("Location: $url");
        exit;
    }
}

// Default fallback
header('Content-Type: text/plain');
echo "Engine v2.0 Active";
?>
