<?php
/**
 * 🚀 BLASTFORGE ENTERPRISE WORKER v2.1
 * Features: AES Decryption, SMTP Failover, A/B Testing, Throttling
 */

if (php_sapi_name() !== 'cli') die("CLI Only.");

require_once 'config.php';
require_once 'security.php';
require_once 'google_api_helper.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "[".date('Y-m-d H:i:s')."] ⚙️ BlastForge Enterprise Worker v2.1 (A/B Ready) Started...\n";

function processQueue($smtp, $conn, $campaign, $owner_id, $cred_holder_id) {
    $cid = $campaign['id'];
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['user'];
    $mail->Password = $smtp['pass'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp['port'];
    $mail->setFrom($smtp['user'], 'BlastForge Engine');
    $mail->Timeout = 15;

    $queue = $conn->query("SELECT * FROM email_queue WHERE campaign_id = $cid AND status = 'pending' LIMIT 20");
    if ($queue->num_rows === 0) return "DONE";

    while ($item = $queue->fetch_assoc()) {
        $qid = $item['id'];
        $email = $item['email'];
        
        try {
            // 🧬 A/B TESTING LOGIC
            $subject = $campaign['subject'];
            if (!empty($campaign['subject_b']) && rand(1, 100) <= $campaign['subject_b_weight']) {
                $subject = $campaign['subject_b'];
            }

            // 🕵️ ANALYTICS TRACKING (Invisible Pixel & Link Wrapper)
            $qid = $item['id'];
            $cid = $campaign['id'];
            $host_url = "http://localhost/emailblast/track.php"; // Change this if you deploy online
            
            $tracking_pixel = "<br/><img src='$host_url?type=open&cid=$cid&qid=$qid' width='1' height='1' style='display:none;' />";
            
            // 🛡️ Safe Link Wrapping (Regex to find and wrap all <a> tags)
            $tracked_content = preg_replace_callback('/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/', function($m) use ($host_url, $cid, $qid) {
                $original_url = $m[2];
                if (strpos($original_url, 'http') === 0) {
                    $tracking_url = $host_url . "?type=click&cid=$cid&qid=$qid&url=" . urlencode($original_url);
                    return str_replace($original_url, $tracking_url, $m[0]);
                }
                return $m[0];
            }, $campaign['content']);

            $tracked_content .= $tracking_pixel;

            // 🛡️ OAUTH2 CHECK (Individual Admin Gmail API)
            $u = $conn->query("SELECT google_refresh_token FROM users WHERE id = $cred_holder_id")->fetch_assoc();
            $success = false;

            if (!empty($u['google_refresh_token'])) {
                // Use the ADMIN's Gmail API for this USER's campaign
                $success = sendGmailApi($cred_holder_id, $email, $item['name'], $subject, $tracked_content, [], $conn);
            } else {
                // Fallback to PHPMailer SMTP
                $mail->clearAddresses();
                $mail->addAddress($email, $item['name']);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $tracked_content;
                $success = $mail->send();
            }

            if ($success) {
                $conn->query("UPDATE email_queue SET status = 'sent', processed_at = NOW() WHERE id = $qid");
                $conn->query("UPDATE campaigns SET sent_success = sent_success + 1 WHERE id = $cid");
                
                // Credit Quota
                $quota_holder = $owner_id;
                $u = $conn->query("SELECT role, referred_by_admin_id FROM users WHERE id = $owner_id")->fetch_assoc();
                if ($u['role'] === 'user' && $u['referred_by_admin_id']) $quota_holder = $u['referred_by_admin_id'];
                $conn->query("UPDATE users SET sent_count_this_month = sent_count_this_month + 1 WHERE id = $quota_holder");
                
                echo "  [SENT] $email (".($subject === $campaign['subject'] ? 'A' : 'B').")\n";
                usleep(500000); // 0.5s throttle
            }
        } catch (Exception $e) {
            echo "  [FAIL] $email: " . $mail->ErrorInfo . "\n";
            $err = $conn->real_escape_string($mail->ErrorInfo);
            $conn->query("UPDATE email_queue SET status = 'failed', error_message = '$err' WHERE id = $qid");
            $conn->query("UPDATE campaigns SET sent_failed = sent_failed + 1 WHERE id = $cid");
            return "FAILOVER";
        }
    }
    return "CONTINUE";
}

while (true) {
    @file_put_contents('worker_heartbeat.txt', time());
    $camp = $conn->query("SELECT * FROM campaigns WHERE status IN ('queued', 'sending') LIMIT 1")->fetch_assoc();
    if (!$camp) { sleep(10); continue; }

    $cid = $camp['id'];
    $owner_id = $camp['user_id'];
    $conn->query("UPDATE campaigns SET status = 'sending' WHERE id = $cid");

    // 🕵️ ROUTING LOGIC: If the owner is a regular USER, route through their ADMIN's credentials
    $lookup_user = $conn->query("SELECT * FROM users WHERE id = $owner_id")->fetch_assoc();
    $cred_holder_id = $owner_id;

    if ($lookup_user['role'] === 'user' && !empty($lookup_user['referred_by_admin_id'])) {
        $cred_holder_id = $lookup_user['referred_by_admin_id'];
        echo "  [Routing] User #$owner_id campaign through Admin #$cred_holder_id credentials.\n";
    }

    $u = $conn->query("SELECT * FROM users WHERE id = $cred_holder_id")->fetch_assoc();
    $p_pass = $u['smtp_pass_encrypted'] ? Security::decrypt($u['smtp_pass_encrypted']) : $u['smtp_pass'];
    $b_pass = $u['backup_smtp_pass_encrypted'] ? Security::decrypt($u['backup_smtp_pass_encrypted']) : null;

    $p_smtp = ['host' => $u['smtp_host'], 'user' => $u['smtp_user'], 'pass' => $p_pass, 'port' => $u['smtp_port']];
    $b_smtp = $u['backup_smtp_host'] ? ['host' => $u['backup_smtp_host'], 'user' => $u['backup_smtp_user'], 'pass' => $b_pass, 'port' => $u['backup_smtp_port']] : null;

    echo "[".date('H:i:s')."] Processing Campaign #$cid...\n";
    @file_put_contents('worker_heartbeat.txt', time());
    
    $status = processQueue($p_smtp, $conn, $camp, $owner_id, $cred_holder_id);
    if ($status === "FAILOVER" && $b_smtp) {
        echo "  ⚠️ Failover Engaged for Campaign #$cid using Backup Credentials\n";
        $status = processQueue($b_smtp, $conn, $camp, $owner_id, $cred_holder_id);
    }
    if ($status === "DONE") {
        $conn->query("UPDATE campaigns SET status = 'completed' WHERE id = $cid");
        echo "  ✅ Campaign #$cid Completed.\n";
    }
}
