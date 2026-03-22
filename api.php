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
            $usersStat = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM subscribers WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); 
            $subsStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT COUNT(*) FROM campaigns WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); 
            $campsStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT SUM(sent_success) FROM campaigns WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); 
            $deliveredStat = $stmt->get_result()->fetch_row()[0] ?? 0;
            $usersStat = 0;

            $stmt = $conn->prepare("SELECT sent_count_this_month FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $quotaUsed = $stmt->get_result()->fetch_row()[0] ?? 0;
        }

        // 📅 Real Performance Chart (Last 7 Days)
        $chart_labels = [];
        $chart_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('D', strtotime($date));
            $chart_labels[] = $label;
            
            $q = ($_SESSION['role'] === 'super_admin') 
                ? "SELECT SUM(sent_success) FROM campaigns WHERE DATE(created_at) = '$date'"
                : "SELECT SUM(sent_success) FROM campaigns WHERE user_id = $user_id AND DATE(created_at) = '$date'";
            $val = $conn->query($q)->fetch_row()[0] ?? 0;
            $chart_data[] = $val;
        }

        // Add Open & Click Stats
        if ($_SESSION['role'] === 'super_admin') {
            $oCount = $conn->query("SELECT SUM(opens_count) FROM campaigns")->fetch_row()[0] ?? 0;
            $clCount = $conn->query("SELECT SUM(clicks_count) FROM campaigns")->fetch_row()[0] ?? 0;
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

        echo json_encode([
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
            "chart_data" => $chart_data
        ]);
        break;

    case 'getCampaigns':
        if ($_SESSION['role'] === 'super_admin') {
            $res = $conn->query("SELECT * FROM campaigns ORDER BY created_at DESC");
        } else {
            $stmt = $conn->prepare("SELECT * FROM campaigns WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
        }
        echo json_encode(["campaigns" => $res->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'getUsers':
        if ($_SESSION['role'] === 'super_admin') {
            $res = $conn->query("SELECT id, name, email, role, created_at, last_activity, 
                IF(last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE), 1, 0) as is_online 
                FROM users ORDER BY last_activity DESC, created_at DESC");
            echo json_encode(["users" => $res->fetch_all(MYSQLI_ASSOC)]);
        } else if ($_SESSION['role'] === 'admin') {
            $stmt = $conn->prepare("SELECT id, name, email, role, created_at, last_activity, 
                IF(last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE), 1, 0) as is_online 
                FROM users WHERE referred_by_admin_id = ? ORDER BY last_activity DESC, created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            echo json_encode(["users" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        } else {
            http_response_code(403); exit;
        }
        break;

    case 'addUser':
        if ($_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'admin') {
            http_response_code(403); exit;
        }
        $name = Security::clean($_POST['name'] ?? '');
        $email = strtolower(Security::clean($_POST['email'] ?? ''));
        $pass = $_POST['password'] ?? '';
        
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $role = ($_SESSION['role'] === 'super_admin') ? 'admin' : 'user';
        
        // Check for duplicates
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) die(json_encode(["error" => "Email already listed."]));

        $ins = $conn->prepare("INSERT INTO users (name, email, password_hash, role, referred_by_admin_id, is_verified) VALUES (?, ?, ?, ?, ?, 1)");
        $ins->bind_param("ssssi", $name, $email, $hashed, $role, $user_id);
        
        if ($ins->execute()) {
            Security::log($conn, $user_id, "ADMIN_ONBOARD", "Successfully enrolled new team member: $email");
            echo json_encode(["status" => "success", "message" => "User added successfully."]);
        }
        break;

    case 'getSubscribers':
        if ($_SESSION['role'] === 'super_admin') {
            $stmt = $conn->prepare("SELECT s.*, u.name as owner FROM subscribers s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC");
        } else {
            $stmt = $conn->prepare("SELECT s.*, '' as owner FROM subscribers s WHERE user_id = ? ORDER BY created_at DESC");
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
            echo json_encode(["status" => "success", "message" => "Subscriber successfully added"]);
        } else {
            echo json_encode(["error" => "Failed to add subscriber. Email might already exist."]);
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
            echo json_encode(["status" => "success", "message" => "Subscriber successfully removed"]);
        } else {
            echo json_encode(["error" => "Failed to remove subscriber or unauthorized."]);
        }
        break;

    case 'getLogs':
        $target_id = intval($_GET['target_id'] ?? $user_id);
        if ($_SESSION['role'] !== 'super_admin' && $target_id !== $user_id) {
            http_response_code(403); exit;
        }
        $stmt = $conn->prepare("SELECT id, user_id, action, details, ip_address, created_at, old_data, new_data FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        echo json_encode(["logs" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
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
        if ($_SESSION['role'] !== 'super_admin') jsonResponse(['error' => 'Super Admin Access Only'], 403);
        
        $tid = intval($input['target_id'] ?? 0);
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
        if ($_SESSION['role'] !== 'super_admin') jsonResponse(['error' => 'Super Admin Access Only'], 403);
        
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
        if ($_SESSION['role'] !== 'super_admin') {
            http_response_code(403); exit;
        }
        $tid = intval($_POST['id'] ?? 0);
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
        $rl = Security::clean($_POST['role'] ?? '');
        $st = Security::clean($_POST['status'] ?? 'active');
        $iv = intval($_POST['is_verified'] ?? 0);
        
        $sh = Security::clean($_POST['smtp_host'] ?? '');
        $sp = intval($_POST['smtp_port'] ?? 587);
        $su = Security::clean($_POST['smtp_user'] ?? '');
        $sa = $_POST['smtp_pass'] ?? '';

        $bsh = Security::clean($_POST['backup_smtp_host'] ?? '');
        $bsu = Security::clean($_POST['backup_smtp_user'] ?? '');
        $bsa = $_POST['backup_smtp_pass'] ?? '';
        $bsp = intval($_POST['backup_smtp_port'] ?? 587);

        $sql = "UPDATE users SET firstName=?, lastName=?, name=?, email=?, phone=?, age=?, gender=?, birthday=?, location=?, address=?, id_info=?, role=?, status=?, is_verified=?, smtp_host=?, smtp_port=?, smtp_user=?, backup_smtp_host=?, backup_smtp_user=?, backup_smtp_port=?";
        $params = [$fn, $ln, $nm, $em, $ph, $ag, $gn, $bd, $lc, $ad, $ii, $rl, $st, $iv, $sh, $sp, $su, $bsh, $bsu, $bsp];
        $types = "sssssisssssssisisiis";

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
            Security::log($conn, $user_id, "ADMIN_UPDATE_USER", "Updated profile for User ID: $tid", $old_data, $new_data);
            echo json_encode(["status" => "success", "message" => "User updated successfully."]);
        } else {
            echo json_encode(["error" => "Failed to update user."]);
        }
        break;

    case 'updateProfile':
        $old_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $old_stmt->bind_param("i", $user_id);
        $old_stmt->execute();
        $old_data = $old_stmt->get_result()->fetch_assoc();

        $name = Security::clean($_POST['name'] ?? '');
        $email = Security::clean($_POST['email'] ?? '');
        $host = Security::clean($_POST['smtp_host'] ?? '');
        $user = Security::clean($_POST['smtp_user'] ?? '');
        $pass = $_POST['smtp_pass'] ?? '';
        $port = intval($_POST['smtp_port'] ?? 587);

        $b_host = Security::clean($_POST['backup_smtp_host'] ?? '');
        $b_user = Security::clean($_POST['backup_smtp_user'] ?? '');
        $b_pass = $_POST['backup_smtp_pass'] ?? '';
        $b_port = intval($_POST['backup_smtp_port'] ?? 587);

        $sql = "UPDATE users SET name=?, email=?, smtp_host=?, smtp_user=?, smtp_port=?, backup_smtp_host=?, backup_smtp_user=?, backup_smtp_port=?";
        $params = [$name, $email, $host, $user, $port, $b_host, $b_user, $b_port];
        $types = "ssssisss";

        if (!empty($pass)) { $sql .= ", smtp_pass_encrypted=?"; $params[] = Security::encrypt($pass); $types .= "s"; }
        if (!empty($b_pass)) { $sql .= ", backup_smtp_pass_encrypted=?"; $params[] = Security::encrypt($b_pass); $types .= "s"; }

        $sql .= " WHERE id=?"; $params[] = $user_id; $types .= "i";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
             $_SESSION['name'] = $name;
             $new_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
             $new_stmt->bind_param("i", $user_id);
             $new_stmt->execute();
             $new_data = $new_stmt->get_result()->fetch_assoc();
             Security::log($conn, $user_id, "UPDATE_PROFILE", "User updated account and SMTP info.", $old_data, $new_data);
             echo json_encode(["status" => "success", "message" => "Profile updated successfully."]);
        }
        break;

    case 'getDeletedUsers':
        if ($_SESSION['role'] !== 'super_admin') jsonResponse(['error' => 'Super Admin Access Required'], 403);
        $res = $conn->query("SELECT d.*, a.name as admin_name FROM deleted_users d 
                            LEFT JOIN users a ON d.deleted_by_admin_id = a.id 
                            ORDER BY d.deleted_at DESC");
        echo json_encode(["deleted_users" => $res->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'importSubscribers':
        if ($_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'admin') {
            http_response_code(403); exit;
        }
        if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
             die(json_encode(["error" => "No file uploaded."]));
        }
        $file = $_FILES['csv']['tmp_name'];
        $handle = fopen($file, "r");
        $count = 0;
        fgetcsv($handle); // skip header
        while (($data = fgetcsv($handle)) !== FALSE) {
            $email = strtolower(Security::clean($data[0] ?? ''));
            $c_name = Security::clean($data[1] ?? '');
            if (empty($email)) continue;
            $stmt = $conn->prepare("INSERT IGNORE INTO subscribers (user_id, email, name) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $email, $c_name);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $count++;
        }
        fclose($handle);
        Security::log($conn, $user_id, "BULK_IMPORT", "Imported $count subscribers via CSV");
        echo json_encode(["status" => "success", "message" => "Successfully imported $count subscribers."]);
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

    case 'sendBlast':
        header('Content-Type: application/json');
        $subject = Security::clean($_POST['subject'] ?? '');
        $subject_b = Security::clean($_POST['subject_b'] ?? '');
        $weight_a = intval($_POST['weight_a'] ?? 100);
        $weight_b = intval($_POST['weight_b'] ?? 0);
        $template = $_POST['template'] ?? '';
        $content = $_POST['content'] ?? '';
        
        if (empty($subject) || empty($content)) {
            die(json_encode(["error" => "Subject A and Content are required."]));
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

        // 🎯 Targeting
        if ($_SESSION['role'] === 'super_admin') { $subs = $conn->query("SELECT email, name FROM subscribers"); }
        else { $stmt = $conn->prepare("SELECT email, name FROM subscribers WHERE user_id = ?"); $stmt->bind_param("i", $user_id); $stmt->execute(); $subs = $stmt->get_result(); }

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
