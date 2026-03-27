<?php
require_once 'config.php';
require_once 'security.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized Access"]));
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// CSRF check for write actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = '';
    foreach (['X-CSRF-TOKEN', 'X-Csrf-Token', 'x-csrf-token', 'HTTP_X_CSRF_TOKEN'] as $h) {
        if (isset($headers[$h])) { $token = $headers[$h]; break; }
        if (isset($_SERVER[$h])) { $token = $_SERVER[$h]; break; }
    }
    if (empty($token) && isset($_POST['csrf_token'])) $token = $_POST['csrf_token'];
    
    if (!Security::verifyCSRF($token)) {
        http_response_code(403);
        die(json_encode(["error" => "Firewall: Invalid CSRF Token"]));
    }
}

switch ($action) {
    case 'getStats':
        $quotaUsed = 0;
        if ($_SESSION['role'] === 'super_admin') {
            $subsStat = $conn->query("SELECT COUNT(*) FROM subscribers")->fetch_row()[0];
            $campsStat = $conn->query("SELECT COUNT(*) FROM campaigns")->fetch_row()[0];
            $deliveredStat = $conn->query("SELECT SUM(sent_success) FROM campaigns")->fetch_row()[0] ?? 0;
            $usersStat = $conn->query("SELECT COUNT(*) FROM users WHERE is_approved = 1 AND status = 'active'")->fetch_row()[0];
        } else if ($_SESSION['role'] === 'admin') {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM subscribers WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?))");
            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
            $stmt->execute(); $subsStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT COUNT(*) FROM campaigns WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?))");
            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
            $stmt->execute(); $campsStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT SUM(sent_success) FROM campaigns WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?))");
            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
            $stmt->execute(); $deliveredStat = $stmt->get_result()->fetch_row()[0] ?? 0;
            
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE (referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?)) AND is_approved = 1 AND status = 'active'");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute(); $usersStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT sent_count_this_month FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); $quotaUsed = $stmt->get_result()->fetch_row()[0] ?? 0;
        } else if ($_SESSION['role'] === 'staff') {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM subscribers WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?)");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute(); $subsStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT COUNT(*) FROM campaigns WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?)");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute(); $campsStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT SUM(sent_success) FROM campaigns WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?)");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute(); $deliveredStat = $stmt->get_result()->fetch_row()[0] ?? 0;
            
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE referred_by_admin_id = ? AND is_approved = 1 AND status = 'active'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); $usersStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT sent_count_this_month FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); $quotaUsed = $stmt->get_result()->fetch_row()[0] ?? 0;
        } else {
            // role === 'user' (Target Sourcer)
            $stmt = $conn->prepare("SELECT COUNT(*) FROM subscribers WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); $subsStat = $stmt->get_result()->fetch_row()[0];
            $campsStat = 0; $deliveredStat = 0; $usersStat = 0; $quotaUsed = 0;
        }

        // 📅 Real Performance Chart (Last 7 Days)
        $chart_labels = [];
        $chart_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('D', strtotime($date));
            $chart_labels[] = $label;
            
            if ($_SESSION['role'] === 'super_admin') {
                $q = "SELECT SUM(sent_success) FROM campaigns WHERE DATE(created_at) = '$date'";
            } else if ($_SESSION['role'] === 'admin') {
                $q = "SELECT SUM(sent_success) FROM campaigns WHERE (user_id = $user_id OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = $user_id OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = $user_id))) AND DATE(created_at) = '$date'";
            } else if ($_SESSION['role'] === 'staff') {
                $q = "SELECT SUM(sent_success) FROM campaigns WHERE (user_id = $user_id OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = $user_id)) AND DATE(created_at) = '$date'";
            } else {
                // 🚀 ADVANCED: Sourcing Velocity (Leads captured per day)
                $q = "SELECT COUNT(*) FROM subscribers WHERE user_id = $user_id AND DATE(created_at) = '$date'";
            }
            $val = $conn->query($q)->fetch_row()[0] ?? 0;
            $chart_data[] = $val;
        }

        // Add Open & Click Stats
        if ($_SESSION['role'] === 'super_admin') {
            $oCount = $conn->query("SELECT SUM(opens_count) FROM campaigns")->fetch_row()[0] ?? 0;
            $clCount = $conn->query("SELECT SUM(clicks_count) FROM campaigns")->fetch_row()[0] ?? 0;
        } else if ($_SESSION['role'] === 'admin') {
            $stmt = $conn->prepare("SELECT SUM(opens_count) FROM campaigns WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?))");
            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
            $stmt->execute();
            $oCount = $stmt->get_result()->fetch_row()[0] ?? 0;
            
            $stmt = $conn->prepare("SELECT SUM(clicks_count) FROM campaigns WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?))");
            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
            $stmt->execute();
            $clCount = $stmt->get_result()->fetch_row()[0] ?? 0;
        } else if ($_SESSION['role'] === 'staff') {
            $stmt = $conn->prepare("SELECT SUM(opens_count) FROM campaigns WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?)");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $oCount = $stmt->get_result()->fetch_row()[0] ?? 0;
            
            $stmt = $conn->prepare("SELECT SUM(clicks_count) FROM campaigns WHERE user_id = ? OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?)");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $clCount = $stmt->get_result()->fetch_row()[0] ?? 0;
        } else {
            $stmt = $conn->prepare("SELECT SUM(opens_count) FROM campaigns WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $oCount = $stmt->get_result()->fetch_row()[0] ?? 0;
            
            $stmt = $conn->prepare("SELECT SUM(clicks_count) FROM campaigns WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $clCount = $stmt->get_result()->fetch_row()[0] ?? 0;
        }

        // 🕵️ WORKER CHECK
        $hbFile = 'worker_heartbeat.txt';
        $workerOnline = false;
        $lastHBTime = null;
        if (file_exists($hbFile)) {
            $lastHB = intval(file_get_contents($hbFile));
            $lastHBTime = date('H:i:s', $lastHB);
            if (time() - $lastHB < 300) $workerOnline = true; // 5 minute window
        }

        // 📋 QUEUE CHECK
        $pendingQueue = $conn->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetch_row()[0] ?? 0;
        
        $totalMsg = 0; $unreadMsg = 0;
        if ($_SESSION['role'] === 'user') {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM email_queue q JOIN subscribers s ON q.email = s.email WHERE s.user_id = ? AND q.is_opened = 1");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); $totalMsg = $stmt->get_result()->fetch_row()[0] ?? 0; // Total Opened
            
            $stmt = $conn->prepare("SELECT COUNT(*) FROM email_queue q JOIN subscribers s ON q.email = s.email WHERE s.user_id = ? AND q.status = 'sent' AND q.is_opened = 0");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); $unreadMsg = $stmt->get_result()->fetch_row()[0] ?? 0; // Total Unopened
        }

        jsonResponse([
            "total_subs" => $subsStat,
            "total_campaigns" => $campsStat,
            "total_delivered" => $deliveredStat,
            "total_opens" => $oCount,
            "total_clicks" => $clCount,
            "total_users" => $usersStat,
            "quota_used" => $quotaUsed,
            "worker_online" => $workerOnline,
            "last_heartbeat" => $lastHBTime,
            "pending_queue" => $pendingQueue,
            "chart_labels" => $chart_labels,
            "chart_data" => $chart_data,
            "total_messages" => $totalMsg,
            "unread_messages" => $unreadMsg
        ]);
        break;

    case 'getHeatmap':
        $heatmap = [];
        for ($h = 0; $h < 24; $h++) {
            if ($_SESSION['role'] === 'super_admin') {
                $q = "SELECT COUNT(*) FROM email_queue WHERE engagement_hour = $h";
            } else {
                $q = "SELECT COUNT(*) FROM email_queue q JOIN campaigns c ON q.campaign_id = c.id WHERE q.engagement_hour = $h AND c.user_id = $user_id";
            }
            $heatmap[$h] = $conn->query($q)->fetch_row()[0] ?? 0;
        }
        jsonResponse(["heatmap" => $heatmap]);
        break;

    case 'getLeaderboard':
        $query = "SELECT u.name, SUM(c.sent_success) as total_sent, SUM(c.opens_count) as total_opens, 
                  (SUM(c.opens_count) / SUM(c.sent_success) * 100) as open_rate 
                  FROM users u JOIN campaigns c ON u.id = c.user_id 
                  WHERE c.sent_success > 0 ";
        
        if ($_SESSION['role'] !== 'super_admin') {
            $query .= " AND (u.referred_by_admin_id = $user_id OR u.id = $user_id) ";
        }
        $query .= " GROUP BY u.id ORDER BY open_rate DESC LIMIT 10";
        $res = $conn->query($query);
        jsonResponse(["leaderboard" => $res->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'getLeadInsights':
        $q = "SELECT name, email, lead_score, last_engagement, 
              (lead_score * 0.7 + IF(last_engagement > DATE_SUB(NOW(), INTERVAL 7 DAY), 30, 0)) as conv_prob 
              FROM subscribers WHERE user_id = $user_id ORDER BY conv_prob DESC LIMIT 20";
        $res = $conn->query($q);
        jsonResponse(["insights" => $res->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'getSystemAlerts':
        if ($_SESSION['role'] !== 'super_admin') jsonResponse(['error' => 'Super Admin Only'], 403);
        $res = $conn->query("SELECT * FROM system_alerts WHERE is_resolved = 0 ORDER BY created_at DESC LIMIT 5");
        $alerts = [];
        while($row = $res->fetch_assoc()) $alerts[] = $row;
        jsonResponse($alerts);
        break;

    case 'getPlatformHealth':
        if ($_SESSION['role'] !== 'super_admin') { http_response_code(403); exit; }
        $health = [
            "active_workers" => $conn->query("SELECT COUNT(DISTINCT worker_instance_id) FROM email_queue WHERE worker_instance_id IS NOT NULL")->fetch_row()[0],
            "global_sent_today" => $conn->query("SELECT SUM(sent_success) FROM campaigns WHERE DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0,
            "smtp_health" => $conn->query("SELECT COUNT(*) FROM users WHERE smtp_host IS NOT NULL AND status = 'active'")->fetch_row()[0]
        ];
        echo json_encode($health);
        break;

    case 'getGlobalSettings':
        if ($_SESSION['role'] !== 'super_admin') { http_response_code(403); exit; }
        $res = $conn->query("SELECT * FROM global_settings");
        echo json_encode(["settings" => $res->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'getCampaigns':
        if ($_SESSION['role'] === 'super_admin') {
            $res = $conn->query("SELECT c.*, u.name as owner_name FROM campaigns c JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC");
        } else if ($_SESSION['role'] === 'admin') {
            $stmt = $conn->prepare("SELECT c.*, IF(c.user_id = ?, 'Me', u.name) as owner_name FROM campaigns c JOIN users u ON c.user_id = u.id WHERE c.user_id = ? OR c.user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?)) ORDER BY c.created_at DESC");
            $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $stmt = $conn->prepare("SELECT c.*, 'Me' as owner_name FROM campaigns c WHERE c.user_id = ? ORDER BY c.created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
        }
        echo json_encode(["campaigns" => $res->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'getUsers':
        if ($_SESSION['role'] === 'super_admin') {
            $query = "SELECT u.*, 
                IF(u.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE), 1, 0) as is_online,
                (SELECT COUNT(*) FROM campaigns c WHERE c.user_id = u.id) as total_campaigns,
                (SELECT SUM(sent_success) FROM campaigns c WHERE c.user_id = u.id) as total_emails
                FROM users u WHERE u.is_approved = 1 AND u.status = 'active'
                ORDER BY u.role DESC, u.created_at DESC";
            $res = $conn->query($query);
        } else if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff') {
            $stmt = $conn->prepare("SELECT u.*, 
                IF(u.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE), 1, 0) as is_online,
                (SELECT COUNT(*) FROM campaigns c WHERE c.user_id = u.id) as total_campaigns,
                (SELECT SUM(sent_success) FROM campaigns c WHERE c.user_id = u.id) as total_emails
                FROM users u WHERE (u.referred_by_admin_id = ? OR u.referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?) OR u.id = ?)
                AND u.is_approved = 1 AND u.status = 'active'
                ORDER BY u.role DESC, u.created_at DESC");
            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            http_response_code(403); exit;
        }
        echo json_encode(["users" => $res->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'addUser':
        if ($_SESSION['role'] === 'user') {
            http_response_code(403); exit;
        }
        $name = Security::clean($_POST['name'] ?? '');
        $email = strtolower(Security::clean($_POST['email'] ?? ''));
        $pass = $_POST['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($pass)) {
            die(json_encode(["error" => "All fields (Name, Email, Password) are required."]));
        }
        
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        if ($_SESSION['role'] === 'super_admin') {
            $role = 'admin';
        } else if ($_SESSION['role'] === 'admin') {
            $role = 'staff';
            $chk = $conn->prepare("SELECT COUNT(*) FROM users WHERE referred_by_admin_id = ? AND role = 'staff'");
            $chk->bind_param("i", $user_id);
            $chk->execute();
            if ($chk->get_result()->fetch_row()[0] >= 5) die(json_encode(["error" => "Admin limit: Maximum of 5 staff members allowed."]));
        } else if ($_SESSION['role'] === 'staff') {
            $role = 'user';
        } else {
            http_response_code(403); exit;
        }
        
        // Check for duplicates
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) die(json_encode(["error" => "Email already listed."]));

        $otp = sprintf("%06d", mt_rand(100000, 999999));
        
        $ins = $conn->prepare("INSERT INTO users (name, email, password_hash, role, otp_code, otp_expires_at, referred_by_admin_id, is_verified) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?, 0)");
        $ins->bind_param("sssssi", $name, $email, $hashed, $role, $otp, $user_id);
        
        if ($ins->execute()) {
            require_once 'mailer_functions.php';
            sendOTP($email, $name, $otp);
            Security::log($conn, $user_id, "ADMIN_ONBOARD", "Successfully enrolled new team member: $email (Pending Verification)");
            echo json_encode(["status" => "success", "message" => "User added successfully. A verification code has been sent to their email."]);
        }
        break;

    case 'getSubscribers':
        if ($_SESSION['role'] === 'super_admin') {
            $stmt = $conn->prepare("SELECT s.*, u.name as owner FROM subscribers s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC");
        } else if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff') {
            $stmt = $conn->prepare("SELECT s.*, IF(s.user_id = ?, 'Me', u.name) as owner FROM subscribers s JOIN users u ON s.user_id = u.id WHERE s.user_id = ? OR s.user_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?) ORDER BY s.created_at DESC");
            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
        } else {
            $stmt = $conn->prepare("SELECT s.*, 'Me' as owner FROM subscribers s WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        echo json_encode(["subscribers" => $res->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'addSubscriber':
        $name = Security::clean($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        $stmt = $conn->prepare("INSERT INTO subscribers (user_id, name, email) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $name, $email);
        if ($stmt->execute()) {
            Security::log($conn, $user_id, "ADD_SUBSCRIBER", "Added contact: $email");
            jsonResponse(["status" => "success", "message" => "Subscriber successfully added"]);
        } else {
            jsonResponse(["error" => "Failed to add subscriber. Email might already exist."], 400);
        }
        break;

    case 'deleteSubscriber':
        $sub_id = intval($_GET['id'] ?? 0);
        if ($_SESSION['role'] === 'super_admin') {
            $stmt = $conn->prepare("DELETE FROM subscribers WHERE id = ?");
            $stmt->bind_param("i", $sub_id);
        } else {
            // Admin and Users can only delete their own subscribers
            $stmt = $conn->prepare("DELETE FROM subscribers WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $sub_id, $user_id);
        }
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            jsonResponse(["status" => "success", "message" => "Subscriber successfully removed"]);
        } else {
            jsonResponse(["error" => "Failed to remove subscriber or unauthorized."], 403);
        }
        break;

    case 'getLogs':
        $target_id = intval($_GET['target_id'] ?? $user_id);
        if ($_SESSION['role'] !== 'super_admin' && $target_id !== $user_id) {
            $chk = $conn->prepare("SELECT id FROM users WHERE id = ? AND (referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?))");
            $chk->bind_param("iii", $target_id, $user_id, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                http_response_code(403); exit;
            }
        }
        $stmt = $conn->prepare("SELECT id, user_id, action, details, ip_address, created_at, old_data, new_data FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        jsonResponse(["logs" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'updateRole':
        header('Content-Type: application/json');
        if ($_SESSION['role'] !== 'super_admin') {
             http_response_code(403); die(json_encode(["error" => "Forbidden"]));
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) die(json_encode(["error" => "Invalid JSON payload"]));
        
        $tid = intval($data['target_id'] ?? 0);
        $nr = Security::clean($data['role'] ?? '');
        
        if ($tid <= 0 || empty($nr)) die(json_encode(["error" => "Missing required data"]));

        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $nr, $tid);
        if ($stmt->execute()) {
            Security::log($conn, $user_id, "ROLE_CHANGE", "Updated User ID $tid to role: $nr");
            echo json_encode(["status" => "success"]);
        } else {
             echo json_encode(["error" => "Failed to update role."]);
        }
        break;

    case 'deleteUser':
        header('Content-Type: application/json');
        $tid = intval($_GET['id'] ?? 0);
        if ($tid <= 0) die(json_encode(["error" => "Invalid User ID"]));
        if ($tid === $user_id) die(json_encode(["error" => "Cannot delete yourself"]));

        // 1. Fetch data before deletion
        $stmt = $conn->prepare("SELECT name, email, role, referred_by_admin_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $tid);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();

        if (!$u) die(json_encode(["error" => "User not found."]));

        // 2. Security Check (Admins only delete their OWN users)
        if ($_SESSION['role'] !== 'super_admin' && $u['referred_by_admin_id'] != $user_id) {
            die(json_encode(["error" => "Unauthorized access."]));
        }

        // 3. Archive to deleted_users
        $ins = $conn->prepare("INSERT INTO deleted_users (original_id, name, email, role, deleted_by_admin_id, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $reason = "Permanent Deletion from Dashboard";
        $ins->bind_param("isssis", $tid, $u['name'], $u['email'], $u['role'], $user_id, $reason);
        $ins->execute();

        // 4. Delete the user
        $conn->query("DELETE FROM users WHERE id = $tid");

        // 5. Log for Super Admin traceability
        Security::log($conn, $user_id, "ADMIN_REMOVE_USER", "Deleted Account: {$u['name']} ({$u['email']})");

        echo json_encode(["status" => "success", "message" => "Account archived and removed."]);
        break;

    case 'adminChangePassword':
        $input = json_decode(file_get_contents('php://input'), true);
        $tid = intval($input['target_id'] ?? 0);
        if ($_SESSION['role'] !== 'super_admin') {
            $chk = $conn->prepare("SELECT id FROM users WHERE id = ? AND (referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?))");
            $chk->bind_param("iii", $tid, $user_id, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) jsonResponse(['error' => 'Super Admin Access Only'], 403);
        }
        
        $newPass = $input['new_password'] ?? '';
        
        if (empty($newPass) || strlen($newPass) < 6) jsonResponse(['error' => 'New password must be at least 6 characters.'], 400);

        // Fetch User
        $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $tid);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        if (!$u) jsonResponse(['error' => 'Account not found.'], 404);

        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        
        // Store as PENDING password change
        $expiry = date('Y-m-d H:i:s', time() + 600); // 10 mins
        $conn->query("UPDATE users SET otp_code = '$otp', otp_expires_at = '$expiry', temp_password_hash = '$hashed' WHERE id = $tid");

        // Send Handshake Email to the USER
        require_once 'mailer_functions.php';
        sendPasswordChangeHandshake($u['email'], $u['name'], $otp);
        
        Security::log($conn, $user_id, "ADMIN_PASSWORD_HANDSHAKE", "Super Admin initiated password change for: {$u['email']}");

        jsonResponse(['status' => 'success', 'message' => "Verification code has been sent to {$u['email']}. Please ask them for the code to finalize.", 'target_id' => $tid]);
        break;

    case 'finalizeAdminChangePassword':
        $input = json_decode(file_get_contents('php://input'), true);
        $tid = intval($input['target_id'] ?? 0);
        if ($_SESSION['role'] !== 'super_admin') {
            $chk = $conn->prepare("SELECT id FROM users WHERE id = ? AND (referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?))");
            $chk->bind_param("iii", $tid, $user_id, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) jsonResponse(['error' => 'Super Admin Access Only'], 403);
        }
        
        $tid = intval($input['target_id'] ?? 0);
        $code = Security::clean($input['otp_code'] ?? '');
        
        $stmt = $conn->prepare("SELECT temp_password_hash FROM users WHERE id = ? AND otp_code = ? AND otp_expires_at > NOW()");
        $stmt->bind_param("is", $tid, $code);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($u = $res->fetch_assoc()) {
            $newHashed = $u['temp_password_hash'];
            $conn->query("UPDATE users SET password_hash = '$newHashed', otp_code = NULL, otp_expires_at = NULL, temp_password_hash = NULL WHERE id = $tid");
            Security::log($conn, $user_id, "ADMIN_PASSWORD_FINALIZED", "Password change finalized for User ID $tid");
            jsonResponse(['status' => 'success', 'message' => 'Credentials updated successfully.']);
        }
        jsonResponse(['error' => 'Invalid or expired confirmation code.'], 400);
        break;

    case 'adminUpdateUser':
        $tid = intval($_POST['id'] ?? 0);
        if ($_SESSION['role'] !== 'super_admin') {
            $chk = $conn->prepare("SELECT id FROM users WHERE id = ? AND (referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?))");
            $chk->bind_param("iii", $tid, $user_id, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                echo json_encode(['error' => 'Not Authorized']);
                exit;
            }
        }
        $old_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $old_stmt->bind_param("i", $tid);
        $old_stmt->execute();
        $old_data = $old_stmt->get_result()->fetch_assoc();

        $fn = Security::clean($_POST['firstName'] ?? '');
        $ln = Security::clean($_POST['lastName'] ?? '');
        $nm = Security::clean($_POST['name'] ?? "$fn $ln");
        $em = strtolower(Security::clean($_POST['email'] ?? ''));
        $ph = Security::clean($_POST['phone'] ?? '');
        $ag = intval($_POST['age'] ?? 0);
        $gn = Security::clean($_POST['gender'] ?? '');
        $bd = Security::clean($_POST['birthday'] ?? '');
        $lc = Security::clean($_POST['location'] ?? '');
        $ad = Security::clean($_POST['address'] ?? '');
        $ii = Security::clean($_POST['id_info'] ?? '');
        $bi = Security::clean($_POST['bio'] ?? '');
        $gm = Security::clean($_POST['gmail'] ?? '');
        $fb = Security::clean($_POST['facebook'] ?? '');
        $ig = Security::clean($_POST['instagram'] ?? '');
        $rl = Security::clean($_POST['role'] ?? '');
        $st = Security::clean($_POST['status'] ?? 'active');
        $iv = intval($_POST['is_verified'] ?? 0);

        $gci = Security::clean($_POST['google_client_id'] ?? '');
        $gcs = Security::clean($_POST['google_client_secret'] ?? '');
        
        $sh = Security::clean($_POST['smtp_host'] ?? '');
        $sp = intval($_POST['smtp_port'] ?? 587);
        $su = Security::clean($_POST['smtp_user'] ?? '');
        $sa = $_POST['smtp_pass'] ?? '';

        $bsh = Security::clean($_POST['backup_smtp_host'] ?? '');
        $bsu = Security::clean($_POST['backup_smtp_user'] ?? '');
        $bsa = $_POST['backup_smtp_pass'] ?? '';
        $bsp = intval($_POST['backup_smtp_port'] ?? 587);

        $sql = "UPDATE users SET firstName=?, lastName=?, name=?, email=?, phone=?, age=?, gender=?, birthday=?, location=?, address=?, id_info=?, bio=?, gmail=?, facebook=?, instagram=?, role=?, status=?, is_verified=?, google_client_id=?, google_client_secret=?, smtp_host=?, smtp_port=?, smtp_user=?, backup_smtp_host=?, backup_smtp_user=?, backup_smtp_port=?";
        $params = [$fn, $ln, $nm, $em, $ph, $ag, $gn, $bd, $lc, $ad, $ii, $bi, $gm, $fb, $ig, $rl, $st, $iv, $gci, $gcs, $sh, $sp, $su, $bsh, $bsu, $bsp];
        $types = "sssssisssssssssssisssisiis";

        if (!empty($sa)) { $sql .= ", smtp_pass_encrypted=?"; $params[] = Security::encrypt($sa); $types .= "s"; }
        if (!empty($bsa)) { $sql .= ", backup_smtp_pass_encrypted=?"; $params[] = Security::encrypt($bsa); $types .= "s"; }

        $sql .= " WHERE id=?"; $params[] = $tid; $types .= "i";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $new_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $new_stmt->bind_param("i", $tid);
            $new_stmt->execute();
            $new_data = $new_stmt->get_result()->fetch_assoc();
            Security::log($conn, $user_id, "ADMIN_UPDATE_USER", "Updated extensive profile for User ID: $tid", $old_data, $new_data);
            echo json_encode(["status" => "success", "message" => "Admin update complete."]);
        } else {
            echo json_encode(["error" => "Failed to update user record: " . $conn->error]);
        }
        break;

    case 'updateProfile':
        $old_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $old_stmt->bind_param("i", $user_id);
        $old_stmt->execute();
        $old_data = $old_stmt->get_result()->fetch_assoc();

        $old_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
        
        $name = Security::clean($_POST['name'] ?? '');
        $firstName = Security::clean($_POST['firstName'] ?? '');
        $lastName = Security::clean($_POST['lastName'] ?? '');
        $email = strtolower(Security::clean($_POST['email'] ?? ($old_data['email'] ?? '')));

        if (empty($email)) {
             die(json_encode(["status" => "error", "message" => "Email address cannot be empty."]));
        }

        // 🚀 Auto-sync Full Name for Members
        if (empty($name) && (!empty($firstName) || !empty($lastName))) {
            $name = trim($firstName . ' ' . $lastName);
        }

        $fields = [
            'name' => $name,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'age' => intval($_POST['age'] ?? 0),
            'gender' => Security::clean($_POST['gender'] ?? ''),
            'birthday' => Security::clean($_POST['birthday'] ?? null),
            'phone' => Security::clean($_POST['phone'] ?? ''),
            'location' => Security::clean($_POST['location'] ?? ''),
            'address' => Security::clean($_POST['address'] ?? ''),
            'id_info' => Security::clean($_POST['id_info'] ?? ''),
            'bio' => Security::clean($_POST['bio'] ?? ''),
            'gmail' => Security::clean($_POST['gmail'] ?? ''),
            'facebook' => Security::clean($_POST['facebook'] ?? ''),
            'instagram' => Security::clean($_POST['instagram'] ?? ''),
            'google_client_id' => Security::clean($_POST['google_client_id'] ?? ''),
            'google_client_secret' => Security::clean($_POST['google_client_secret'] ?? ''),
            'smtp_host' => Security::clean($_POST['smtp_host'] ?? ''),
            'smtp_user' => Security::clean($_POST['smtp_user'] ?? ''),
            'smtp_port' => intval($_POST['smtp_port'] ?? 587),
            'backup_smtp_host' => Security::clean($_POST['backup_smtp_host'] ?? ''),
            'backup_smtp_user' => Security::clean($_POST['backup_smtp_user'] ?? ''),
            'backup_smtp_port' => intval($_POST['backup_smtp_port'] ?? 587),
            'email_notifications' => intval($_POST['email_notifications'] ?? 0),
            'dark_mode' => intval($_POST['dark_mode'] ?? 0),
            'two_factor_enabled' => intval($_POST['two_factor_enabled'] ?? 0)
        ];

        // 🖼️ Avatar Handling
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatarName = "avatar_" . $user_id . "_" . time() . "." . $ext;
            $avatarPath = "uploads/avatars/" . $avatarName;
            if (!is_dir('uploads/avatars')) mkdir('uploads/avatars', 0777, true);
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarPath)) {
                $fields['avatar'] = $avatarPath;
            }
        }

        // 🔒 Password Encryption
        $pass = $_POST['smtp_pass'] ?? '';
        $b_pass = $_POST['backup_smtp_pass'] ?? '';

        $sql_parts = [];
        $params = [];
        $types = "";

        foreach ($fields as $col => $val) {
            $sql_parts[] = "$col = ?";
            $params[] = ($val === "") ? null : $val;
            $types .= (is_int($val) ? "i" : "s");
        }

        if (!empty($pass)) { 
            $sql_parts[] = "smtp_pass_encrypted = ?"; 
            $params[] = Security::encrypt($pass); $types .= "s";
        }
        if (!empty($b_pass)) { 
            $sql_parts[] = "backup_smtp_pass_encrypted = ?"; 
            $params[] = Security::encrypt($b_pass); $types .= "s";
        }

        $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        $params[] = $user_id;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
             $_SESSION['name'] = $name;
             $new_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
             $new_stmt->bind_param("i", $user_id);
             $new_stmt->execute();
             $new_data = $new_stmt->get_result()->fetch_assoc();
             Security::log($conn, $user_id, "UPDATE_PROFILE", "User updated unified profile metadata.", $old_data, $new_data);
             echo json_encode(["status" => "success", "message" => "Profile updated successfully!"]);
        } else {
             echo json_encode(["status" => "error", "message" => "Execution failed: " . $conn->error]);
        }
        break;

    case 'getDeletedUsers':
        if ($_SESSION['role'] !== 'super_admin') jsonResponse(['error' => 'Super Admin Access Required'], 403);
        $res = $conn->query("SELECT d.*, a.name as admin_name FROM deleted_users d 
                            LEFT JOIN users a ON d.deleted_by_admin_id = a.id 
                            ORDER BY d.deleted_at DESC");
        echo json_encode(["deleted_users" => $res->fetch_all(MYSQLI_ASSOC)]);
         case 'importSubscribers':
        if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
             die(json_encode(["error" => "No file uploaded."]));
        }
        $filename = $_FILES['csv']['name'];
        $file = $_FILES['csv']['tmp_name'];
        $handle = fopen($file, "r");
        $contacts = [];
        fgetcsv($handle); // skip header
        while (($data = fgetcsv($handle)) !== FALSE) {
            $email = strtolower(Security::clean($data[0] ?? ''));
            $c_name = Security::clean($data[1] ?? '');
            if (!empty($email)) $contacts[] = ['email' => $email, 'name' => $c_name];
        }
        fclose($handle);

        if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin') {
            // Direct Import for Leaders
            $count = 0;
            foreach ($contacts as $c) {
                $stmt = $conn->prepare("INSERT IGNORE INTO subscribers (user_id, email, name) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user_id, $c['email'], $c['name']);
                $stmt->execute();
                if ($stmt->affected_rows > 0) $count++;
            }
            Security::log($conn, $user_id, "BULK_IMPORT", "Directly imported $count subscribers.");
            echo json_encode(["status" => "success", "message" => "Successfully imported $count subscribers."]);
        } else {
            // Staff/User: Request for Review
            $ref = $conn->prepare("SELECT referred_by_admin_id FROM users WHERE id = ?");
            $ref->bind_param("i", $user_id);
            $ref->execute();
            $admin_id = $ref->get_result()->fetch_row()[0] ?? 0;
            
            if (!$admin_id) die(json_encode(["error" => "No admin found to review your request."]));
            
            $jsonData = json_encode($contacts);
            $stmt = $conn->prepare("INSERT INTO pending_imports (user_id, admin_id, filename, csv_data) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $user_id, $admin_id, $filename, $jsonData);
            if ($stmt->execute()) {
                Security::log($conn, $user_id, "IMPORT_REQUEST", "Submitted import request for review: $filename (" . count($contacts) . " emails)");
                echo json_encode(["status" => "success", "message" => "Import request sent! Waiting for your admin to review and approve."]);
            }
        }
        break;

    case 'getPendingImports':
        if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'staff') { http_response_code(403); exit; }
        if ($_SESSION['role'] === 'super_admin') {
            $res = $conn->query("SELECT p.*, u.name as requester_name FROM pending_imports p JOIN users u ON p.user_id = u.id WHERE p.status = 'pending' ORDER BY p.created_at DESC");
        } else {
            $stmt = $conn->prepare("SELECT p.*, u.name as requester_name FROM pending_imports p JOIN users u ON p.user_id = u.id WHERE p.admin_id = ? AND p.status = 'pending' ORDER BY p.created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
        }
        echo json_encode(["pending_imports" => $res->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'processPendingImport':
        if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'staff') { http_response_code(403); exit; }
        $id = intval($_GET['id'] ?? 0);
        $decision = $_GET['decision'] ?? ''; // 'approve' or 'reject'
        
        $stmt = $conn->prepare("SELECT * FROM pending_imports WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $import = $stmt->get_result()->fetch_assoc();
        
        if (!$import) die(json_encode(["error" => "Request not found."]));

        if ($decision === 'approve') {
            $contacts = json_decode($import['csv_data'], true);
            $requester_id = $import['user_id'];
            $count = 0;
            foreach ($contacts as $c) {
                $ins = $conn->prepare("INSERT IGNORE INTO subscribers (user_id, email, name) VALUES (?, ?, ?)");
                $ins->bind_param("iss", $requester_id, $c['email'], $c['name']);
                $ins->execute();
                if ($ins->affected_rows > 0) $count++;
            }
            $conn->query("UPDATE pending_imports SET status = 'approved' WHERE id = $id");
            Security::log($conn, $user_id, "IMPORT_APPROVED", "Admin approved import ID $id ($count contacts added to User $requester_id)");
            echo json_encode(["status" => "success", "message" => "Import approved! $count contacts are now live."]);
        } else {
            $conn->query("UPDATE pending_imports SET status = 'rejected' WHERE id = $id");
            Security::log($conn, $user_id, "IMPORT_REJECTED", "Admin rejected import request ID $id");
            echo json_encode(["status" => "success", "message" => "Request rejected."]);
        }
        break;

    case 'getPendingRegistrations':
        if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'staff') { http_response_code(403); exit; }
        
        $sqlVerified = "";
        $sqlUnverified = "";
        
        if ($_SESSION['role'] === 'super_admin') {
            $res = $conn->query("SELECT id, name, email, role, created_at, is_verified FROM users WHERE is_approved = 0 ORDER BY created_at DESC");
        } else if ($_SESSION['role'] === 'admin') {
            $stmt = $conn->prepare("SELECT id, name, email, role, created_at, is_verified FROM users WHERE (referred_by_admin_id = ? OR referred_by_admin_id IN (SELECT id FROM users WHERE referred_by_admin_id = ?)) AND is_approved = 0 ORDER BY created_at DESC");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $stmt = $conn->prepare("SELECT id, name, email, role, created_at, is_verified FROM users WHERE referred_by_admin_id = ? AND is_approved = 0 ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
        }
        
        $pending = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(["pending_users" => $pending]);
        break;

    case 'processUserApproval':
        if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'staff') { http_response_code(403); exit; }
        $tid = intval($_GET['id'] ?? 0);
        $decision = $_GET['decision'] ?? '';
        if ($decision === 'approve') {
            $conn->query("UPDATE users SET is_approved = 1 WHERE id = $tid");
            Security::log($conn, $user_id, "USER_APPROVED", "Admin approved registration for user ID $tid");
            echo json_encode(["status" => "success", "message" => "User account activated successfully."]);
        } else {
            $conn->query("DELETE FROM users WHERE id = $tid");
            Security::log($conn, $user_id, "USER_REJECTED", "Admin rejected and deleted registration for user ID $tid");
            echo json_encode(["status" => "success", "message" => "Registration request rejected & deleted."]);
        }
        break;

    case 'getTemplates':
        // Core elite templates
        $templates = [
            [
                "id" => "modern",
                "name" => "Modern Visual System",
                "content" => "<div style='font-family:sans-serif; background:#f9fafb; padding:40px;'>
                    <div style='max-width:600px; margin:auto; background:#fff; border-radius:12px; padding:40px; border:1px solid #e1e1e1;'>
                        <h1 style='color:#111; font-weight:800; font-size:24px; margin-bottom:20px;'>{SUBJECT}</h1>
                        <p style='color:#666; line-height:1.6; font-size:16px;'>{CONTENT}</p>
                    </div>
                </div>"
            ],
            [
                "id" => "midnight",
                "name" => "Midnight Recon",
                "content" => "<div style='font-family:sans-serif; background:#0a0a0a; padding:40px; color:#fff;'>
                    <div style='max-width:600px; margin:auto; background:#111; border-radius:16px; padding:40px; border:1px solid #333;'>
                        <h2 style='color:#8b5cf6; font-weight:800; font-size:22px;'>{SUBJECT}</h2>
                        <div style='color:#ccc; line-height:1.8; font-size:15px; margin-top:30px;'>{CONTENT}</div>
                    </div>
                </div>"
            ]
        ];
        echo json_encode(["templates" => $templates]);
        break;
        
    case 'getUnopenedInvites':
        if ($_SESSION['role'] !== 'user') { http_response_code(403); exit; }
        // 🚀 HIERARCHICAL INTELLIGENCE: Show unopened emails from self AND direct referrer (Admin/Staff)
        $stmt = $conn->prepare("SELECT q.id, q.email, q.processed_at as created_at, 'UNOPENED' as subject 
                                FROM email_queue q 
                                JOIN subscribers s ON q.email = s.email 
                                WHERE (s.user_id = ? OR s.user_id = (SELECT referred_by_admin_id FROM users WHERE id = ?)) 
                                AND q.status = 'sent' AND q.is_opened = 0 
                                ORDER BY q.processed_at DESC LIMIT 100");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        echo json_encode(["messages" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'getPersonalActivity':
        if ($_SESSION['role'] !== 'user') { http_response_code(403); exit; }
        $stmt = $conn->prepare("SELECT created_at, action, details as notes, ip_address FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo json_encode(["logs" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'getUserLogs':
        $target_user_id = intval($_GET['target_user_id'] ?? $user_id);
        if ($_SESSION['role'] !== 'super_admin' && $target_user_id !== $user_id) {
            http_response_code(403); exit;
        }
        $stmt = $conn->prepare("SELECT action, ip_address, created_at, details as notes FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        echo json_encode(["logs" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'getRegistrationAttempts':
        if ($_SESSION['role'] === 'user') { http_response_code(403); exit; }
        if ($_SESSION['role'] === 'super_admin') {
            $stmt = $conn->prepare("SELECT r.*, u.name as referrer_name FROM registration_attempts r LEFT JOIN users u ON r.referrer_id = u.id ORDER BY r.last_attempt DESC");
            $stmt->execute();
            echo json_encode(["attempts" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        } else {
            $stmt = $conn->prepare("SELECT r.*, '' as referrer_name FROM registration_attempts r WHERE r.referrer_id = ? ORDER BY r.last_attempt DESC");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            echo json_encode(["attempts" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }
        break;

    case 'clearRegistrationAttempt':
        if ($_SESSION['role'] === 'user') { http_response_code(403); exit; }
        $id = intval($_GET['id'] ?? 0);
        
        $stmt = $conn->prepare("SELECT email FROM registration_attempts WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $email = $stmt->get_result()->fetch_row()[0] ?? '';

        if ($_SESSION['role'] === 'super_admin') {
            $stmt = $conn->prepare("DELETE FROM registration_attempts WHERE id = ?");
            $stmt->bind_param("i", $id);
        } else {
            $stmt = $conn->prepare("DELETE FROM registration_attempts WHERE id = ? AND referrer_id = ?");
            $stmt->bind_param("ii", $id, $_SESSION['user_id']);
        }
        
        if ($stmt->execute()) {
             if (!empty($email)) {
                 // Also remove the unverified user record so they can register again
                 $conn->query("DELETE FROM users WHERE email = '$email' AND is_verified = 0");
             }
             Security::log($conn, $_SESSION['user_id'], "CLEAR_ATTEMPT", "Admin cleared registration attempt and unverified record for: $email");
             echo json_encode(["status" => "success", "message" => "Attempt record cleared and email released."]);
        }
        break;

    case 'getWorkerStatus':
        $file = 'worker_heartbeat.txt';
        $status = 'offline';
        if (file_exists($file)) {
            $last = file_get_contents($file);
            if (time() - intval($last) < 60) $status = 'online';
        }
        $pending = $conn->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetch_row()[0];
        echo json_encode(["status" => $status, "pending" => $pending]);
        break;

    case 'sendBlast':
        header('Content-Type: application/json');
        $subject = Security::clean($_POST['subject'] ?? '');
        $subject_b = Security::clean($_POST['subject_b'] ?? '');
        $weight_a = intval($_POST['weight_a'] ?? 100);
        $weight_b = intval($_POST['weight_b'] ?? 0);
        $template = $_POST['template'] ?? '';
        $content = $_POST['content'] ?? '';
        
        if (empty($subject) || empty($content)) {
            jsonResponse(["error" => "Subject A and Content are required."], 400);
        }

        // 🛡️ ELITE PRE-SEND VALIDATION (SPAM & QUALITY)
        $spamWords = ['viagra', 'win money', 'free prize', 'guaranteed success', 'urgent action required', 'cash gift'];
        $foundSpam = [];
        foreach ($spamWords as $word) {
            if (stripos($content, $word) !== false || stripos($subject, $word) !== false) $foundSpam[] = $word;
        }
        if (!empty($foundSpam)) {
             jsonResponse(["error" => "Campaign blocked by AI Spam Filter. Restricted keywords detected: " . implode(', ', $foundSpam)], 403);
        }

        // Auto-check for unsubscribe link
        if (stripos($content, 'unsubscribe') === false) {
             $content .= '<br><br><p style="font-size:12px; color:#777;">To stop receiving these, <a href="{UNSUBSCRIBE_URL}">unsubscribe here</a>.</p>';
        }

        // 🛡️ Monthly Quota Management (Hierarchical)
        $currentMonth = date('Y-m');
        $quota_owner_id = $user_id;
        if ($_SESSION['role'] === 'user') {
            $ref = $conn->prepare("SELECT referred_by_admin_id FROM users WHERE id = ?");
            $ref->bind_param("i", $user_id);
            $ref->execute();
            $quota_owner_id = $ref->get_result()->fetch_row()[0] ?? $user_id;
        }

        $check = $conn->prepare("SELECT sent_count_this_month, monthly_limit, last_reset_month FROM users WHERE id = ?");
        $check->bind_param("i", $quota_owner_id);
        $check->execute();
        $userQuota = $check->get_result()->fetch_assoc();

        if ($userQuota['last_reset_month'] !== $currentMonth) {
            $conn->query("UPDATE users SET sent_count_this_month = 0, last_reset_month = '$currentMonth', monthly_limit = 250000 WHERE id = $quota_owner_id");
            $userQuota['sent_count_this_month'] = 0; $userQuota['monthly_limit'] = 250000;
        }

        // 🎯 HIERARCHICAL TARGETING 
        // --------------------------------------------------
        if ($_SESSION['role'] === 'super_admin') { 
            // 🚀 SUPER ADMIN: Reach everyone in the entire organization
            $sql = "SELECT email, name FROM subscribers 
                    UNION 
                    SELECT email, name FROM users 
                    WHERE is_approved = 1 AND status = 'active'";
            $subs = $conn->query($sql);
        } else if ($_SESSION['role'] === 'admin') {
            // 🏢 REGIONAL ADMIN: Reach own branch + all staff + all branch leads
            $sql = "SELECT email, name FROM subscribers 
                    WHERE user_id = $user_id 
                    OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = $user_id)
                    UNION
                    SELECT email, name FROM users 
                    WHERE id = $user_id 
                    OR referred_by_admin_id = $user_id
                    AND is_approved = 1 AND status = 'active'";
            $subs = $conn->query($sql);
        } else if ($_SESSION['role'] === 'staff') {
            // 🧑‍💼 BRANCH STAFF: Reach own leads + all users below them
            $sql = "SELECT email, name FROM subscribers 
                    WHERE user_id = $user_id 
                    OR user_id IN (SELECT id FROM users WHERE referred_by_admin_id = $user_id)
                    UNION
                    SELECT email, name FROM users 
                    WHERE id = $user_id 
                    OR referred_by_admin_id = $user_id
                    AND is_approved = 1 AND status = 'active'";
            $subs = $conn->query($sql);
        } else { 
            // 👤 STANDARD USER (Target Sourcer)
            $stmt = $conn->prepare("SELECT email, name FROM subscribers WHERE user_id = ?"); 
            $stmt->bind_param("i", $user_id); 
            $stmt->execute(); 
            $subs = $stmt->get_result(); 
        }

        $targetCount = $subs->num_rows;
        $remainingQuota = $userQuota['monthly_limit'] - $userQuota['sent_count_this_month'];

        if ($targetCount > $remainingQuota) {
            die(json_encode(["error" => "Exceeded monthly limit. Remaining: $remainingQuota emails. Requested: $targetCount."]));
        }

        // 📋 TRANSACTIONAL QUEUING
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO campaigns (user_id, subject, subject_b, subject_b_weight, content, template, total_targets, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'queued')");
            $stmt->bind_param("isssisi", $user_id, $subject, $subject_b, $weight_b, $content, $template, $targetCount);
            $stmt->execute();
            $campaign_id = $conn->insert_id;

            $qStmt = $conn->prepare("INSERT INTO email_queue (campaign_id, email, name, status) VALUES (?, ?, ?, 'pending')");
            while ($row = $subs->fetch_assoc()) {
                $qStmt->bind_param("iss", $campaign_id, $row['email'], $row['name']);
                $qStmt->execute();
            }

            $conn->commit();
            Security::log($conn, $user_id, "BLAST_QUEUED", "Scheduled $targetCount emails for campaign ID: $campaign_id. A/B enabled: " . (!empty($subject_b) ? 'Yes' : 'No'));
            echo json_encode(["status" => "success", "message" => "Blast Forge Engine: $targetCount targets queued for delivery.", "cid" => $campaign_id]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["error" => "Queuing failure: " . $e->getMessage()]);
        }
        break;
}
