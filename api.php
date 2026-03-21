<?php
require_once 'config.php';
require_once 'security.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized Access"]));
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// CSRF check for write actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $token = $headers['X-CSRF-TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRF($token)) {
        http_response_code(403);
        die(json_encode(["error" => "Firewall: Invalid CSRF Token"]));
    }
}

switch ($action) {
    case 'getStats':
        if ($_SESSION['role'] === 'super_admin') {
            // GLOBAL ANALYTICS for Super Admin
            $subsStat = $conn->query("SELECT COUNT(*) FROM subscribers")->fetch_row()[0];
            $campsStat = $conn->query("SELECT COUNT(*) FROM campaigns")->fetch_row()[0];
            $deliveredStat = $conn->query("SELECT SUM(sent_count) FROM campaigns")->fetch_row()[0] ?? 0;
            $usersStat = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
        } else {
            // PERSONAL ANALYTICS for Branch Admins & Users
            $stmt = $conn->prepare("SELECT COUNT(*) FROM subscribers WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); 
            $subsStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT COUNT(*) FROM campaigns WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); 
            $campsStat = $stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare("SELECT SUM(sent_count) FROM campaigns WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute(); 
            $deliveredStat = $stmt->get_result()->fetch_row()[0] ?? 0;
            $usersStat = 0;
        }

        echo json_encode([
            "total_subs" => $subsStat,
            "total_campaigns" => $campsStat,
            "total_delivered" => $deliveredStat,
            "total_users" => $usersStat,
            "chart_labels" => ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
            "chart_data" => [12, 19, 3, 15, 22, 30, 45]
        ]);
        break;

    case 'getUsers':
        if ($_SESSION['role'] !== 'super_admin') {
            http_response_code(403); exit;
        }
        $res = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
        echo json_encode(["users" => $res->fetch_all(MYSQLI_ASSOC)]);
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
            echo json_encode(["status" => "success", "message" => "Member Sync Complete"]);
        }
        break;

    case 'getSubscribers':
        if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin') {
            $stmt = $conn->prepare("SELECT s.*, u.name as owner FROM subscribers s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC");
        } else {
            $stmt = $conn->prepare("SELECT * FROM subscribers WHERE user_id = ? ORDER BY created_at DESC");
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
            echo json_encode(["message" => "Success"]);
        }
        break;

    case 'getLogs':
        $target_id = intval($_GET['target_id'] ?? $user_id);
        if ($_SESSION['role'] !== 'super_admin' && $target_id !== $user_id) {
            http_response_code(403); exit;
        }
        $stmt = $conn->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        echo json_encode(["logs" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'updateProfile':
        $name = Security::clean($_POST['name'] ?? '');
        $email = Security::clean($_POST['email'] ?? '');
        $host = Security::clean($_POST['smtp_host'] ?? '');
        $user = Security::clean($_POST['smtp_user'] ?? '');
        $pass = $_POST['smtp_pass'] ?? '';
        $port = intval($_POST['smtp_port'] ?? 587);

        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, smtp_host = ?, smtp_user = ?, smtp_pass = ?, smtp_port = ? WHERE id = ?");
        $stmt->bind_param("sssssii", $name, $email, $host, $user, $pass, $port, $user_id);
        if ($stmt->execute()) {
            $_SESSION['name'] = $name;
            Security::log($conn, $user_id, "UPDATE_PROFILE", "User updated account and SMTP info.");
            echo json_encode(["message" => "Sync Success!"]);
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
    case 'getCampaigns':
        $target_user_id = intval($_GET['target_user_id'] ?? $user_id);
        if ($_SESSION['role'] !== 'super_admin' && $target_user_id !== $user_id) {
            http_response_code(403); exit;
        }
        $stmt = $conn->prepare("SELECT * FROM campaigns WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        echo json_encode(["campaigns" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'sendBlast':
        $subject = Security::clean($_POST['subject'] ?? '');
        $template = $_POST['template'] ?? '';
        $content = $_POST['content'] ?? '';
        
        // 📋 World-Class Template Rendering
        $htmlContent = $content; // Use raw content if no template matched
        if ($template === 'modern') {
            $htmlContent = "<div style='font-family:sans-serif; background:#f9fafb; padding:40px; border-radius:30px;'><div style='max-width:600px; margin:auto; background:#fff; border-radius:24px; padding:60px; border:1px solid #eee; box-shadow:0 10px 30px rgba(0,0,0,0.05);'><h1 style='color:#111; font-weight:800; font-size:32px; letter-spacing:-1px; margin-bottom:30px;'>$subject</h1><div style='color:#444; line-height:1.8; font-size:18px;'>$content</div></div></div>";
        } else if ($template === 'midnight') {
            $htmlContent = "<div style='font-family:sans-serif; background:#05060b; padding:40px; color:#fff;'><div style='max-width:600px; margin:auto; background:#0f121d; border-radius:24px; padding:60px; border:1px solid #1e293b;'><h1 style='color:#8b5cf6; font-weight:800; font-size:32px; letter-spacing:-1px; margin-bottom:30px;'>$subject</h1><div style='color:#94a3b8; line-height:1.8; font-size:18px;'>$content</div></div></div>";
        }

        // 🛡️ Fetch SMTP Creds
        $stmt = $conn->prepare("SELECT smtp_host, smtp_user, smtp_pass, smtp_port FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $creds = $stmt->get_result()->fetch_assoc();

        if (empty($creds['smtp_host'])) {
            die(json_encode(["error" => "SMTP not configured. Sync your Profile."]));
        }

        // 🎯 Targeting
        $stmt = $conn->prepare("SELECT email, name FROM subscribers WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $subs = $stmt->get_result();
        $sent_count = 0;

        // PHPMailer Integration
        require 'vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $creds['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $creds['smtp_user'];
            $mail->Password = $creds['smtp_pass'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $creds['smtp_port'];
            $mail->setFrom($creds['smtp_user'], $_SESSION['name']);

            while($row = $subs->fetch_assoc()) {
                $mail->clearAddresses();
                $mail->addAddress($row['email'], $row['name']);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $htmlContent;
                $mail->send();
                $sent_count++;
            }

            // Record Campaign
            $stmt = $conn->prepare("INSERT INTO campaigns (user_id, name, sent_count) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $user_id, $subject, $sent_count);
            $stmt->execute();

            Security::log($conn, $user_id, "BLAST_SUCCESS", "Distributed $sent_count payloads.");
            echo json_encode(["status" => "success", "sent" => $sent_count]);
        } catch (Exception $e) {
            echo json_encode(["error" => "Uplink Failed: " . $mail->ErrorInfo]);
        }
        break;
}
