<?php
/**
 * 🚀 BLASTFORGE ENTERPRISE WORKER v3.0 (ULTRA-ENGINE)
 * Features: Adaptive Throttling, Priority-based Scheduling, Parallel Processing, Smart Retry
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

$worker_id = "WORKER_" . getmypid();
echo "[".date('Y-m-d H:i:s')."] ⚙️ BlastForge Enterprise Worker v3.0 ($worker_id) Started...\n";

function processQueue($smtp, $conn, $campaign, $owner_id, $cred_holder_id, $worker_id) {
    $cid = $campaign['id'];
    $speed = $campaign['adaptive_speed'] ?? 100000;
    
    // 🚀 CLAIM TASKS: Atomic update to prevent race conditions in parallel mode
    $conn->query("UPDATE email_queue SET worker_instance_id = '$worker_id' 
                  WHERE campaign_id = $cid AND status = 'pending' AND worker_instance_id IS NULL 
                  LIMIT 10");
    
    $queue = $conn->query("SELECT * FROM email_queue WHERE campaign_id = $cid AND worker_instance_id = '$worker_id' AND status = 'pending'");
    if ($queue->num_rows === 0) return "DONE";

    $success_this_batch = 0;
    while ($item = $queue->fetch_assoc()) {
        $qid = $item['id'];
        $email = $item['email'];
        
        try {
            $subject = $campaign['subject']; // A/B Logic can be added here
            
            // 🕵️ ANALYTICS TRACKING
            $host_url = (isset($_ENV['APP_URL']) ? $_ENV['APP_URL'] : 'http://localhost/emailblast') . '/track.php';
            $tracking_pixel = "<br/><img src='$host_url?type=open&cid=$cid&qid=$qid' width='1' height='1' style='display:none;' />";
            
            $tracked_content = preg_replace_callback('/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/', function($m) use ($host_url, $cid, $qid) {
                $original_url = $m[2];
                if (strpos($original_url, 'http') === 0) {
                    return str_replace($original_url, $host_url . "?type=click&cid=$cid&qid=$qid&url=" . urlencode($original_url), $m[0]);
                }
                return $m[0];
            }, $campaign['content']);

            $tracked_content .= $tracking_pixel;

            // 🛡️ OAUTH2 vs SMTP ROUTING
            $u = $conn->query("SELECT google_refresh_token FROM users WHERE id = $cred_holder_id")->fetch_assoc();
            $success = false;

            if (!empty($u['google_refresh_token'])) {
                $success = sendGmailApi($cred_holder_id, $email, $item['name'], $subject, $tracked_content, [], $conn);
            } else {
                // 🏊‍♂️ ELITE SMTP POOL & FAILOVER ENGINE
                $pool = $conn->query("SELECT * FROM smtp_pools WHERE user_id = $owner_id AND is_active = 1 ORDER BY success_count DESC LIMIT 3");
                $active_smtp = $smtp; // Default to primary
                
                if ($item['retry_count'] > 0 && $p_row = $pool->fetch_assoc()) {
                    // If retrying, try a fresh SMTP from the pool
                    $active_smtp = [
                        'host' => $p_row['host'],
                        'port' => $p_row['port'],
                        'user' => $p_row['username'],
                        'pass' => Security::decrypt($p_row['password_encrypted']),
                        'pool_id' => $p_row['id']
                    ];
                }

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $active_smtp['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $active_smtp['user'];
                $mail->Password = $active_smtp['pass'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $active_smtp['port'];
                $mail->setFrom($active_smtp['user'], 'BlastForge Engine');
                $mail->addAddress($email, $item['name']);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $tracked_content;
                
                if ($mail->send()) {
                    $success = true;
                    if (isset($active_smtp['pool_id'])) {
                        $conn->query("UPDATE smtp_pools SET success_count = success_count + 1 WHERE id = " . $active_smtp['pool_id']);
                    }
                }
            }

            if ($success) {
                $conn->query("UPDATE email_queue SET status = 'sent', processed_at = NOW(), worker_instance_id = NULL WHERE id = $qid");
                $conn->query("UPDATE campaigns SET sent_success = sent_success + 1 WHERE id = $cid");
                $success_this_batch++;
                
                // 🚀 ADAPTIVE THROTTLING: Speed up
                $speed = max(10000, $speed * 0.95);
            }
        } catch (Exception $e) {
            $err = $conn->real_escape_string($e->getMessage());
            // 🔄 SMART RETRY: Exponential Backoff (Simplified)
            $retry_delay = pow(2, $item['retry_count'] + 1);
            $next_retry = date('Y-m-d H:i:s', time() + ($retry_delay * 60));
            
            if ($item['retry_count'] < 3) {
                $conn->query("UPDATE email_queue SET status = 'pending', retry_count = retry_count + 1, next_retry_at = '$next_retry', worker_instance_id = NULL, error_message = '$err' WHERE id = $qid");
                echo "  [RETRYING] $email in $retry_delay mins\n";
            } else {
                $conn->query("UPDATE email_queue SET status = 'failed', error_message = '$err', worker_instance_id = NULL WHERE id = $qid");
                $conn->query("UPDATE campaigns SET sent_failed = sent_failed + 1 WHERE id = $cid");
            }
            
            // 🐢 ADAPTIVE THROTTLING: Slow down
            $speed = min(1000000, $speed * 1.5);
            $conn->query("UPDATE campaigns SET adaptive_speed = $speed WHERE id = $cid");
            if (isset($active_smtp['pool_id'])) {
                $conn->query("UPDATE smtp_pools SET fail_count = fail_count + 1 WHERE id = " . $active_smtp['pool_id']);
                // Auto-disable if too many fails
                $conn->query("UPDATE smtp_pools SET is_active = 0 WHERE id = " . $active_smtp['pool_id'] . " AND fail_count > 10");
            }
            return "FAILOVER";
        }
            if ($item['retry_count'] >= 3) {
                 // 🕵️ ANOMALY DETECTION: If a campaign is failing too much, log a critical alert
                 $stats = $conn->query("SELECT sent_success, sent_failed FROM campaigns WHERE id = $cid")->fetch_assoc();
                 if ($stats['sent_failed'] > 50 && ($stats['sent_failed'] / ($stats['sent_success'] + 1)) > 0.5) {
                      $msg = "Campaign ID $cid is experiencing 50%+ failure rate. Investigating SMTP health.";
                      $conn->query("INSERT INTO system_alerts (type, source, message) VALUES ('critical', 'anomaly_detector', '$msg')");
                 }
            }

            usleep($speed); 
        }
    return "CONTINUE";
}

while (true) {
    @file_put_contents('worker_heartbeat.txt', time());
    
    // 🕵️ WATCHDOG: Release jobs assigned to workers that have gone silent
    $conn->query("UPDATE email_queue SET worker_instance_id = NULL, status = 'pending' 
                  WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    
    // 🚀 PRIORITY-BASED SCHEDULING
    $camp = $conn->query("SELECT * FROM campaigns WHERE status IN ('queued', 'sending') ORDER BY priority DESC, created_at ASC LIMIT 1")->fetch_assoc();
    
    if (!$camp) {
        // Handle retries if no active campaigns
        $retry_item = $conn->query("SELECT * FROM email_queue WHERE status = 'pending' AND next_retry_at <= NOW() LIMIT 1")->fetch_assoc();
        if ($retry_item) {
            $cid = $retry_item['campaign_id'];
            $camp = $conn->query("SELECT * FROM campaigns WHERE id = $cid")->fetch_assoc();
        } else {
            sleep(5); continue;
        }
    }

    $cid = $camp['id'];
    $owner_id = $camp['user_id'];
    $conn->query("UPDATE campaigns SET status = 'sending' WHERE id = $cid");

    // ROUTING & CREDENTIALS
    $lookup_user = $conn->query("SELECT * FROM users WHERE id = $owner_id")->fetch_assoc();
    $cred_holder_id = $owner_id;
    if ($lookup_user['role'] === 'staff' && !empty($lookup_user['referred_by_admin_id'])) {
        $cred_holder_id = $lookup_user['referred_by_admin_id'];
    }

    $u = $conn->query("SELECT * FROM users WHERE id = $cred_holder_id")->fetch_assoc();
    $p_pass = $u['smtp_pass_encrypted'] ? Security::decrypt($u['smtp_pass_encrypted']) : $u['smtp_pass'];
    $b_pass = $u['backup_smtp_pass_encrypted'] ? Security::decrypt($u['backup_smtp_pass_encrypted']) : null;

    $p_smtp = ['host' => $u['smtp_host'], 'user' => $u['smtp_user'], 'pass' => $p_pass, 'port' => $u['smtp_port']];
    $b_smtp = $u['backup_smtp_host'] ? ['host' => $u['backup_smtp_host'], 'user' => $u['backup_smtp_user'], 'pass' => $b_pass, 'port' => $u['backup_smtp_port']] : null;

    echo "[".date('H:i:s')."] Processing Campaign #$cid (Speed: ".round($camp['adaptive_speed']/1000)."ms)...\n";
    
    $status = processQueue($p_smtp, $conn, $camp, $owner_id, $cred_holder_id, $worker_id);
    if ($status === "FAILOVER" && $b_smtp) {
        echo "  ⚠️ Failover to Backup SMTP...\n";
        $status = processQueue($b_smtp, $conn, $camp, $owner_id, $cred_holder_id, $worker_id);
    }
    
    if ($status === "DONE") {
        $check = $conn->query("SELECT COUNT(*) FROM email_queue WHERE campaign_id = $cid AND status = 'pending'")->fetch_row()[0];
        if ($check == 0) {
            $conn->query("UPDATE campaigns SET status = 'completed' WHERE id = $cid");
            echo "  ✅ Campaign #$cid Completed.\n";
        }
    }
}
