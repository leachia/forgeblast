<?php
ob_start();
require_once 'db.php';
require_once 'google_api_helper.php';

// Include PHPMailer classes manually
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle preflight CORS if needed
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($method === 'OPTIONS') {
    exit(0); // Exit early for OPTIONS requests
}

// Support both JSON (Subscribers API) and FormData (Campaigns API with files)
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Hierarchical Visibility Logic
if ($userRole === 'user') {
    $isolationWhere = "WHERE s.user_id = $userId";
    $isolationWhereCamp = "WHERE c.user_id = $userId";
    $isolationWhereStats = "WHERE user_id = $userId";
    $isolationWhereUsers = "WHERE id = $userId";
} else if ($userRole === 'admin') {
    // Admins see their own data + data from users they referred
    $subQuery = "SELECT id FROM users WHERE referred_by_admin_id = $userId OR id = $userId";
    $isolationWhere = "WHERE s.user_id IN ($subQuery)";
    $isolationWhereCamp = "WHERE c.user_id IN ($subQuery)";
    $isolationWhereStats = "WHERE user_id IN ($subQuery)";
    $isolationWhereUsers = "WHERE referred_by_admin_id = $userId OR id = $userId";
} else {
    // Super Admin sees EVERYTHING
    $isolationWhere = "";
    $isolationWhereCamp = "";
    $isolationWhereStats = "";
    $isolationWhereUsers = "";
}

switch ($action) {
    
    // ---------------------------------------------------------------- //
    // Subscribers API
    // ---------------------------------------------------------------- //
    case 'getSubscribers':
        if ($method === 'GET') {
            $result = $conn->query("SELECT s.*, u.name as owner_name FROM subscribers s LEFT JOIN users u ON s.user_id = u.id $isolationWhere ORDER BY s.created_at DESC");
            $subscribers = [];
            while($row = $result->fetch_assoc()) {
                $subscribers[] = $row;
            }
            jsonResponse(['subscribers' => $subscribers]);
        }
        break;

    case 'addSubscriber':
        if ($method === 'POST') {
            $name = $conn->real_escape_string($input['name'] ?? '');
            $email = $conn->real_escape_string($input['email'] ?? '');
            
            if (empty($name) || empty($email)) {
                jsonResponse(['error' => 'Name and email are required'], 400);
            }
            
            $sql = "INSERT INTO subscribers (user_id, name, email) VALUES ($userId, '$name', '$email')";
            if ($conn->query($sql) === TRUE) {
                jsonResponse(['message' => 'Subscriber added successfully', 'id' => $conn->insert_id]);
            } else {
                jsonResponse(['error' => 'Error, maybe duplicate email'], 400);
            }
        }
        break;

    case 'deleteSubscriber':
        if ($method === 'DELETE') {
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $conn->query("DELETE FROM subscribers WHERE id = $id AND user_id = $userId");
                jsonResponse(['message' => 'Subscriber deleted']);
            }
            jsonResponse(['error' => 'Invalid ID'], 400);
        }
        break;

    // ---------------------------------------------------------------- //
    // Campaigns API
    // ---------------------------------------------------------------- //
    case 'getCampaigns':
        if ($method === 'GET') {
            $targetUserId = intval($_GET['target_user_id'] ?? 0);
            $whereClause = $isolationWhereCamp;
            if ($userRole === 'super_admin' && $targetUserId > 0) {
                $whereClause = "WHERE c.user_id = $targetUserId";
            }
            $result = $conn->query("SELECT c.*, u.name as sender_name, u.role as sender_role FROM campaigns c LEFT JOIN users u ON c.user_id = u.id $whereClause ORDER BY c.created_at DESC");
            $campaigns = [];
            while($row = $result->fetch_assoc()) {
                $campaigns[] = $row;
            }
            jsonResponse(['campaigns' => $campaigns]);
        }
        break;

    case 'sendCampaign':
        if ($method === 'POST') {
            $subject = $conn->real_escape_string($input['subject'] ?? '');
            $content = $conn->real_escape_string($input['content'] ?? '');
            
            if (empty($subject) || empty($content)) {
                jsonResponse(['error' => 'Subject and content are required'], 400);
            }
            
            // Save attachments permanently first
            $savedAttachments = [];
            if (isset($_FILES['attachments'])) {
                if (!is_dir('uploads/campaigns')) {
                    mkdir('uploads/campaigns', 0777, true);
                }
                $files = $_FILES['attachments'];
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                        $filename = 'uploads/campaigns/att_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                        if (move_uploaded_file($files['tmp_name'][$i], $filename)) {
                            $savedAttachments[] = [
                                'path' => $filename,
                                'original_name' => $files['name'][$i]
                            ];
                        }
                    }
                }
            }
            $attachmentsJson = $conn->real_escape_string(json_encode($savedAttachments));

            // Get Credentials: If User, use their Admin's credentials. If Admin/Super Admin, use their own.
            $credsId = $userId;
            if ($userRole === 'user') {
                $uData = $conn->query("SELECT referred_by_admin_id FROM users WHERE id = $userId")->fetch_assoc();
                if (!empty($uData['referred_by_admin_id'])) $credsId = $uData['referred_by_admin_id'];
            }

            $senderQuery = $conn->query("SELECT smtp_host, smtp_user, smtp_pass, smtp_port, google_refresh_token FROM users WHERE id = $credsId");
            $senderSmtp = $senderQuery->fetch_assoc();

            $isOAuth2 = !empty($senderSmtp['google_refresh_token']);
            $smtpHost = $senderSmtp['smtp_host'] ?? '';
            $smtpUser = $senderSmtp['smtp_user'] ?? '';
            $smtpPass = $senderSmtp['smtp_pass'] ?? '';
            $smtpPort = intval($senderSmtp['smtp_port'] ?? 465);

            // AUTO-FIX: Strip spaces from Google App Passwords (e.g. "abcd efgh..." -> "abcdefgh...")
            $smtpPass = str_replace(' ', '', $smtpPass);

            if (!$isOAuth2 && (empty($smtpHost) || empty($smtpUser) || empty($smtpPass))) {
                jsonResponse(['error' => 'Sender email not configured. Connect Gmail or check SMTP settings.'], 400);
            }

            // Super admin sends immediately; others wait for approval
            if ($userRole === 'super_admin') {
                $res = $conn->query("SELECT * FROM subscribers WHERE status='active' AND user_id = $userId");
                $sentCount = 0;
                $failedCount = 0;

                if ($res->num_rows > 0) {
                    if ($isOAuth2) {
                        // SEND VIA GMAIL API (OAuth2)
                        while ($sub = $res->fetch_assoc()) {
                            if (sendGmailApi($credsId, $sub['email'], $sub['name'], stripslashes($subject), stripslashes($content), $savedAttachments, $conn)) {
                                $sentCount++;
                            } else {
                                $failedCount++;
                            }
                        }
                    } else {
                        // SEND VIA SMTP (PHPMailer)
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = $smtpHost;
                            $mail->SMTPAuth   = true;
                            $mail->Username   = $smtpUser;
                            $mail->Password   = $smtpPass;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            $mail->Port       = $smtpPort;
                            $mail->setFrom($smtpUser, 'BlastForge System');
                            $mail->isHTML(true);
                            $mail->Subject = stripslashes($subject);
                            $mail->Body    = stripslashes($content);
                            foreach ($savedAttachments as $att) {
                                $mail->addAttachment($att['path'], $att['original_name']);
                            }
                            while ($sub = $res->fetch_assoc()) {
                                try {
                                    $mail->addAddress($sub['email'], $sub['name']);
                                    $mail->send();
                                    $mail->clearAllRecipients();
                                    $sentCount++;
                                } catch (Exception $e) {
                                    $failedCount++;
                                    $mail->clearAllRecipients();
                                }
                            }
                        } catch (Exception $e) {
                            jsonResponse(['error' => 'SMTP Configuration Error', 'details' => $mail->ErrorInfo], 500);
                        }
                    }
                }
                
                $sql = "INSERT INTO campaigns (user_id, subject, content, attachments, sent_count, status) VALUES ($userId, '$subject', '$content', '$attachmentsJson', $sentCount, 'sent')";
                if ($conn->query($sql) === TRUE) {
                    jsonResponse(['message' => "Campaign sent immediately. $sentCount delivered."]);
                } else {
                    jsonResponse(['error' => 'Email sent but failed logging to DB'], 500);
                }
            } else {
                // Pending status for non-super admins
                $sql = "INSERT INTO campaigns (user_id, subject, content, attachments, sent_count, status) VALUES ($userId, '$subject', '$content', '$attachmentsJson', 0, 'pending')";
                if ($conn->query($sql) === TRUE) {
                    jsonResponse(['message' => "Campaign submitted for approval! A Super Admin will review it."]);
                } else {
                    jsonResponse(['error' => 'Failed to save pending campaign'], 500);
                }
            }
        }
        break;

    case 'deleteCampaign':
        if ($method === 'DELETE') {
            $id = intval($_GET['id'] ?? 0);
            if ($id > 0) {
                $conn->query("DELETE FROM campaigns WHERE id = $id AND user_id = $userId");
                jsonResponse(['message' => 'Campaign record deleted']);
            }
            jsonResponse(['error' => 'Invalid ID'], 400);
        }
        break;

    case 'approveCampaign':
        if ($method === 'POST' && $userRole === 'super_admin') {
            $campaignId = intval($input['id'] ?? 0);
            $res = $conn->query("SELECT * FROM campaigns WHERE id = $campaignId AND status = 'pending'");
            if ($res->num_rows === 0) jsonResponse(['error' => 'Pending campaign not found'], 404);
            $campaign = $res->fetch_assoc();
            
            $ownerId = $campaign['user_id'];
            
            // Get Credentials: If Owner is User, use their Admin's credentials.
            $credsId = $ownerId;
            $ownerRoleCheck = $conn->query("SELECT role, referred_by_admin_id FROM users WHERE id = $ownerId")->fetch_assoc();
            if ($ownerRoleCheck['role'] === 'user' && !empty($ownerRoleCheck['referred_by_admin_id'])) {
                $credsId = $ownerRoleCheck['referred_by_admin_id'];
            }
            
            $ownerQuery = $conn->query("SELECT smtp_host, smtp_user, smtp_pass, smtp_port, google_refresh_token FROM users WHERE id = $credsId");
            $ownerSmtp = $ownerQuery->fetch_assoc();

            $isOAuth2 = !empty($ownerSmtp['google_refresh_token']);
            $smtpHost = $ownerSmtp['smtp_host'] ?? '';
            $smtpUser = $ownerSmtp['smtp_user'] ?? '';
            $smtpPass = $ownerSmtp['smtp_pass'] ?? '';
            $smtpPort = intval($ownerSmtp['smtp_port'] ?? 465);

            // AUTO-FIX: Strip spaces from Google App Passwords
            $smtpPass = str_replace(' ', '', $smtpPass);

            if (!$isOAuth2 && (empty($smtpHost) || empty($smtpUser) || empty($smtpPass))) {
                jsonResponse(['error' => 'Owner email not configured. Action aborted.'], 400);
            }

            $subject = stripslashes($campaign['subject']);
            $content = stripslashes($campaign['content']);
            $attachments = json_decode($campaign['attachments'], true) ?? [];

            $subRes = $conn->query("SELECT * FROM subscribers WHERE status='active' AND user_id = $ownerId");
            $sentCount = 0;
            $failedCount = 0;

            if ($subRes->num_rows > 0) {
                if ($isOAuth2) {
                    // SEND VIA GMAIL API (OAuth2)
                    while ($sub = $subRes->fetch_assoc()) {
                        if (sendGmailApi($credsId, $sub['email'], $sub['name'], $subject, $content, $attachments, $conn)) {
                            $sentCount++;
                        } else {
                            $failedCount++;
                        }
                    }
                } else {
                    // SEND VIA SMTP (PHPMailer)
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = $smtpHost;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $smtpUser;
                        $mail->Password   = $smtpPass;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = $smtpPort;
                        $mail->setFrom($smtpUser, 'BlastForge System');
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body    = $content;

                        foreach ($attachments as $att) {
                            if (isset($att['path']) && file_exists($att['path'])) {
                                $mail->addAttachment($att['path'], $att['original_name']);
                            }
                        }

                        while ($sub = $subRes->fetch_assoc()) {
                            try {
                                $mail->addAddress($sub['email'], $sub['name']);
                                $mail->send();
                                $mail->clearAllRecipients();
                                $sentCount++;
                            } catch (Exception $e) {
                                $failedCount++;
                                $mail->clearAllRecipients();
                            }
                        }
                    } catch (Exception $e) {
                        jsonResponse(['error' => 'SMTP Dispatch Failed on Approval', 'details' => $mail->ErrorInfo], 500);
                    }
                }
            }
            $conn->query("UPDATE campaigns SET status = 'sent', sent_count = $sentCount WHERE id = $campaignId");
            jsonResponse(['message' => "Campaign approved and sent to $sentCount subscribers."]);
        }
        break;

    case 'rejectCampaign':
        if ($method === 'POST' && $userRole === 'super_admin') {
            $campaignId = intval($input['id'] ?? 0);
            $conn->query("UPDATE campaigns SET status = 'rejected' WHERE id = $campaignId AND status = 'pending'");
            jsonResponse(['message' => 'Campaign rejected']);
        }
        break;

    case 'updateProfile':
        if ($method === 'POST') {
            $name = $conn->real_escape_string($_POST['name'] ?? '');
            $bio = $conn->real_escape_string($_POST['bio'] ?? '');
            $smtpHost = $conn->real_escape_string($_POST['smtp_host'] ?? '');
            $smtpUser = $conn->real_escape_string($_POST['smtp_user'] ?? '');
            $smtpPass = $conn->real_escape_string($_POST['smtp_pass'] ?? '');
            $smtpPort = intval($_POST['smtp_port'] ?? 0);
            
            // Comprehensive Fields
            $firstName = $conn->real_escape_string($_POST['firstName'] ?? '');
            $lastName = $conn->real_escape_string($_POST['lastName'] ?? '');
            $name = trim($firstName . ' ' . $lastName);
            $age = intval($_POST['age'] ?? 0);
            $address = $conn->real_escape_string($_POST['address'] ?? '');
            $phone = $conn->real_escape_string($_POST['phone'] ?? '');
            $gender = $conn->real_escape_string($_POST['gender'] ?? '');
            $location = $conn->real_escape_string($_POST['location'] ?? '');
            $birthday = $conn->real_escape_string($_POST['birthday'] ?? '');
            $facebook = $conn->real_escape_string($_POST['facebook'] ?? '');
            $instagram = $conn->real_escape_string($_POST['instagram'] ?? '');
            $gmail = $conn->real_escape_string($_POST['gmail'] ?? '');
            $email_notifications = intval($_POST['email_notifications'] ?? 1);
            $dark_mode = intval($_POST['dark_mode'] ?? 0);
            $id_info = $conn->real_escape_string($_POST['id_info'] ?? '');
            
            $avatarUrl = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                if (!is_dir('uploads')) mkdir('uploads');
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'uploads/avatar_' . $userId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filename)) {
                    $avatarUrl = $filename;
                }
            }

            $updateParts = [
                "firstName = '$firstName'",
                "lastName = '$lastName'",
                "name = '$name'", 
                "bio = '$bio'",
                "smtp_host = " . ($smtpHost ? "'$smtpHost'" : "NULL"),
                "smtp_user = " . ($smtpUser ? "'$smtpUser'" : "NULL"),
                "smtp_pass = " . ($smtpPass ? "'$smtpPass'" : "NULL"),
                "smtp_port = " . ($smtpPort ? $smtpPort : "NULL"),
                "age = " . ($age ? $age : "NULL"),
                "address = " . ($address ? "'$address'" : "NULL"),
                "phone = " . ($phone ? "'$phone'" : "NULL"),
                "gender = " . ($gender ? "'$gender'" : "NULL"),
                "location = " . ($location ? "'$location'" : "NULL"),
                "birthday = " . ($birthday ? "'$birthday'" : "NULL"),
                "facebook = " . ($facebook ? "'$facebook'" : "NULL"),
                "instagram = " . ($instagram ? "'$instagram'" : "NULL"),
                "gmail = " . ($gmail ? "'$gmail'" : "NULL"),
                "email_notifications = $email_notifications",
                "dark_mode = $dark_mode",
                "id_info = " . ($id_info ? "'$id_info'" : "NULL"),
                "last_activity = NOW()"
            ];
            if ($avatarUrl) {
                $updateParts[] = "avatar = '$avatarUrl'";
                $updateParts[] = "profile_picture_uploaded = 1";
            }
            if ($birthday) $updateParts[] = "birthday_added = 1";
            if ($facebook || $instagram) $updateParts[] = "social_links_added = 1";
            
            try {
                $sql = "UPDATE users SET " . implode(', ', $updateParts) . " WHERE id = $userId";
                if (!$conn->query($sql)) throw new Exception($conn->error);
                
                $_SESSION['name'] = stripslashes($name);
                if (ob_get_length()) ob_clean();
                jsonResponse(['message' => 'Profile updated successfully', 'avatar_url' => $avatarUrl]);
            } catch (Exception $e) {
                if (ob_get_length()) ob_clean();
                jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
        }
        break;

    case 'getStats':
        if ($method === 'GET') {
            $subscribersCount = $conn->query("SELECT COUNT(*) FROM subscribers $isolationWhereStats")->fetch_row()[0];
            $campaignsCount = $conn->query("SELECT COUNT(*) FROM campaigns $isolationWhereStats")->fetch_row()[0];
            $emailsSent = $conn->query("SELECT SUM(sent_count) FROM campaigns $isolationWhereStats")->fetch_row()[0] ?? 0;
            
            jsonResponse([
                'total_subscribers' => $subscribersCount,
                'total_campaigns' => $campaignsCount,
                'total_emails_sent' => $emailsSent
            ]);
        }
        break;

    // ---------------------------------------------------------------- //
    // Admin API
    // ---------------------------------------------------------------- //
    case 'getUsers':
        if ($method === 'GET' && in_array($userRole, ['admin', 'super_admin'])) {
            $whereClause = $isolationWhereUsers ? "WHERE $isolationWhereUsers" : "";
            $sql = "SELECT id, name, email, avatar, role, is_verified, bio, referral_code, age, address, phone, gender, id_info, smtp_host, smtp_user, smtp_pass, smtp_port, 
                           firstName, lastName, location, birthday, facebook, instagram, gmail, status, last_login, last_activity FROM users $whereClause ORDER BY created_at ASC";
            $result = $conn->query($sql);
            $users = [];
            while($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            jsonResponse(['users' => $users]);
        }
        break;

    case 'updateRole':
        if ($method === 'POST' && $userRole === 'super_admin') {
            $targetId = intval($input['target_id'] ?? 0);
            $newRole = $conn->real_escape_string($input['role'] ?? 'user');
            
            // Prevent removing self as admin mistakenly
            if ($targetId == $userId) {
                jsonResponse(['error' => 'Cannot change your own role from here.'], 403);
            }

            if (in_array($newRole, ['admin', 'user']) && $targetId > 0) {
                $conn->query("UPDATE users SET role = '$newRole' WHERE id = $targetId");
                jsonResponse(['message' => 'Role updated']);
            }
            jsonResponse(['error' => 'Invalid request'], 400);
        }
        break;

    case 'adminUpdateUser':
        if ($method === 'POST' && $userRole === 'super_admin') {
            $targetId = intval($input['id'] ?? 0);
            $name = $conn->real_escape_string($input['name'] ?? '');
            $email = $conn->real_escape_string($input['email'] ?? '');
            $bio = $conn->real_escape_string($input['bio'] ?? '');
            $isVerified = intval($input['is_verified'] ?? 0);
            $status = $conn->real_escape_string($input['status'] ?? 'active');
            $role = $conn->real_escape_string($input['role'] ?? 'user');
            if ($role === 'super_admin') $role = 'admin'; // Safeguard

            $firstName = $conn->real_escape_string($input['firstName'] ?? '');
            $lastName = $conn->real_escape_string($input['lastName'] ?? '');
            $name = trim($firstName . ' ' . $lastName);
            $age = intval($input['age'] ?? 0);
            $address = $conn->real_escape_string($input['address'] ?? '');
            $phone = $conn->real_escape_string($input['phone'] ?? '');
            $gender = $conn->real_escape_string($input['gender'] ?? '');
            $location = $conn->real_escape_string($input['location'] ?? '');
            $birthday = $conn->real_escape_string($input['birthday'] ?? '');
            $facebook = $conn->real_escape_string($input['facebook'] ?? '');
            $instagram = $conn->real_escape_string($input['instagram'] ?? '');
            $gmail = $conn->real_escape_string($input['gmail'] ?? '');
            $id_info = $conn->real_escape_string($input['id_info'] ?? '');
            $smtpHost = $conn->real_escape_string($input['smtp_host'] ?? '');
            $smtpUser = $conn->real_escape_string($input['smtp_user'] ?? '');
            $smtpPass = $conn->real_escape_string($input['smtp_pass'] ?? '');
            $smtpPort = intval($input['smtp_port'] ?? 0);

            if ($targetId > 0 && !empty($firstName) && !empty($email)) {
                $sql = "UPDATE users SET 
                        firstName='$firstName', lastName='$lastName', name='$name',
                        email='$email', bio='$bio', is_verified=$isVerified, status='$status',
                        role='$role',
                        age=" . ($age ? $age : "NULL") . ",
                        address=" . ($address ? "'$address'" : "NULL") . ",
                        phone=" . ($phone ? "'$phone'" : "NULL") . ",
                        gender=" . ($gender ? "'$gender'" : "NULL") . ",
                        location=" . ($location ? "'$location'" : "NULL") . ",
                        birthday=" . ($birthday ? "'$birthday'" : "NULL") . ",
                        facebook=" . ($facebook ? "'$facebook'" : "NULL") . ",
                        instagram=" . ($instagram ? "'$instagram'" : "NULL") . ",
                        gmail=" . ($gmail ? "'$gmail'" : "NULL") . ",
                        id_info=" . ($id_info ? "'$id_info'" : "NULL") . ",
                        smtp_host=" . ($smtpHost ? "'$smtpHost'" : "NULL") . ",
                        smtp_user=" . ($smtpUser ? "'$smtpUser'" : "NULL") . ",
                        smtp_pass=" . ($smtpPass ? "'$smtpPass'" : "NULL") . ",
                        smtp_port=" . ($smtpPort ? $smtpPort : "NULL") . "
                        WHERE id=$targetId";
                if ($conn->query($sql)) {
                    if (ob_get_length()) ob_clean();
                    jsonResponse(['message' => 'User profile updated successfully']);
                } else {
                    if (ob_get_length()) ob_clean();
                    jsonResponse(['error' => 'Failed to update user: ' . $conn->error], 500);
                }
            }
            if (ob_get_length()) ob_clean();
            jsonResponse(['error' => 'Invalid data provided'], 400);
        }
        break;

    case 'deleteUser':
        if ($method === 'DELETE' && $userRole === 'super_admin') {
            $targetId = intval($_GET['id'] ?? 0);
            if ($targetId == $userId) {
                jsonResponse(['error' => 'Cannot delete your own account'], 403);
            }
            if ($targetId > 0) {
                $conn->query("DELETE FROM users WHERE id = $targetId");
                jsonResponse(['message' => 'User deleted securely']);
            }
            jsonResponse(['error' => 'Invalid user ID'], 400);
        }
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
?>
