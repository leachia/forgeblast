<?php
ob_start();
session_start();
require_once 'db.php';
require_once 'security.php';
require_once 'mailer_functions.php';

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
$input = $jsonData ?? $_POST;
$action = $_GET['action'] ?? '';

function jsonResponse($data, $statusCode = 200) {
    if (ob_get_length()) ob_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function handleRegistrationAttempt($conn, $email, $password, $otp, $refCode, $refId, $failed = true) {
    $stmt = $conn->prepare("SELECT id, attempts_count, is_banned FROM registration_attempts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $attempts = $row['attempts_count'] + ($failed ? 1 : 0);
        $banned = ($attempts >= 3) ? 1 : $row['is_banned'];
        $upd = $conn->prepare("UPDATE registration_attempts SET password_attempt = ?, otp_attempt = ?, referral_code = ?, referrer_id = ?, attempts_count = ?, is_banned = ? WHERE email = ?");
        $upd->bind_param("sssiiss", $password, $otp, $refCode, $refId, $attempts, $banned, $email);
        $upd->execute();
        return $banned;
    } else {
        $attempts = $failed ? 1 : 0;
        $banned = ($attempts >= 3) ? 1 : 0;
        $ins = $conn->prepare("INSERT INTO registration_attempts (email, password_attempt, otp_attempt, referral_code, referrer_id, attempts_count, is_banned) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("ssssiii", $email, $password, $otp, $refCode, $refId, $attempts, $banned);
        $ins->execute();
        return $banned;
    }
}

switch ($action) {

    // ── REGISTER ──────────────────────────────────────────────────────────────
    case 'register':
        $name     = Security::clean($input['name'] ?? '');
        $email    = strtolower(Security::clean($input['email'] ?? ''));
        $pass     = $input['password'] ?? '';
        $refCode  = Security::clean($input['referral_code'] ?? '');

        if (empty($name) || empty($email) || empty($pass))
            jsonResponse(['error' => 'All fields are required.'], 400);

        // Check for Ban
        $chkBan = $conn->prepare("SELECT is_banned FROM registration_attempts WHERE email = ?");
        $chkBan->bind_param("s", $email);
        $chkBan->execute();
        $banRes = $chkBan->get_result();
        if ($banRow = $banRes->fetch_assoc()) {
            if ($banRow['is_banned']) jsonResponse(['error' => 'Your Gmail has been banned due to 3 failed attempts. Please contact the administrator.'], 403);
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0)
            jsonResponse(['error' => 'Email already registered. Please sign in.'], 400);

        $hashed  = password_hash($pass, PASSWORD_DEFAULT);
        $otp     = sprintf("%06d", mt_rand(100000, 999999));
        $role    = 'user';
        $refAdminId = null;

        $countRes    = $conn->query("SELECT COUNT(*) FROM users");
        $isFirstUser = ($countRes->fetch_row()[0] == 0);

        if ($isFirstUser) {
            $role = 'super_admin';
        } elseif (!empty($refCode)) {
            $refStmt = $conn->prepare("SELECT id, role FROM users WHERE referral_code = ?");
            $refStmt->bind_param("s", $refCode);
            $refStmt->execute();
            $res = $refStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $refAdminId = $row['id'];
                if ($row['role'] === 'super_admin') {
                    $role = 'admin';
                } elseif ($row['role'] === 'admin') {
                    $chk = $conn->prepare("SELECT COUNT(*) FROM users WHERE referred_by_admin_id = ? AND role = 'staff'");
                    $chk->bind_param("i", $refAdminId);
                    $chk->execute();
                    $staffCount = $chk->get_result()->fetch_row()[0];
                    if ($staffCount >= 100) { // Elite Expansion: Increased limit for advanced scale
                        jsonResponse(['error' => 'This Admin Branch has reached its maximum staff limit.'], 400);
                    }
                    $role = 'staff';
                } elseif ($row['role'] === 'staff') {
                    $role = 'user';
                } else {
                    handleRegistrationAttempt($conn, $email, $pass, '', $refCode, 0, true);
                    jsonResponse(['error' => 'Invalid Referral Code. Standard users cannot refer others.'], 400);
                }
            } else {
                handleRegistrationAttempt($conn, $email, $pass, '', $refCode, 0, true);
                jsonResponse(['error' => 'Invalid Branch/Staff Code. Please check your credentials.'], 400);
            }
        } else {
            jsonResponse(['error' => 'An Elite Referral Code is required for registration.'], 400);
        }
        // Log the starting attempt (failed=0 yet, we are trying to send OTP)
        handleRegistrationAttempt($conn, $email, $pass, $otp, $refCode, $refAdminId, false);
        $isVerified = 0; // Forced verification for all roles
        
        $ins = $conn->prepare("INSERT INTO users (name, email, password_hash, role, otp_code, otp_expires_at, referred_by_admin_id, is_verified) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?, ?)");
        $ins->bind_param("sssssii", $name, $email, $hashed, $role, $otp, $refAdminId, $isVerified);

        if ($ins->execute()) {
            $newUserId = $conn->insert_id;
            // Auto-generate prefix-based referral code
            $prefix = ($role === 'super_admin') ? 'ADM-' : (($role === 'admin') ? 'BRN-' : (($role === 'staff') ? 'STF-' : 'USR-'));
            $autoRefCode = $prefix . strtoupper(substr(md5($newUserId . time()), 0, 6));
            $conn->query("UPDATE users SET referral_code = '$autoRefCode' WHERE id = $newUserId AND referral_code IS NULL");
            
            // 📧 Send Authentication OTP
            sendOTP($email, $name, $otp);

            // Log the registration
            logUserAction($conn, $newUserId, 'register', "Role: $role (Status: Pending Verification)");

            jsonResponse(['message' => 'A verification code has been sent to your email.', 'user_id' => $newUserId, 'status' => 'success']);
        } else {
            jsonResponse(['error' => 'Database Error: ' . $conn->error], 500);
        }
        break;

    // ── OTP VERIFY ────────────────────────────────────────────────────────────
    case 'verify':
        $userId = intval($input['user_id'] ?? 0);
        $code   = Security::clean($input['otp_code'] ?? '');
        $stmt   = $conn->prepare("SELECT * FROM users WHERE id = ? AND otp_code = ? AND otp_expires_at > NOW()");
        $stmt->bind_param("is", $userId, $code);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            // Only the very first user (Super Admin) is auto-approved.
            // Everyone else, including new Admins and Staff, must wait for approval.
            $isApproved = ($user['role'] === 'super_admin') ? 1 : 0;
            $conn->query("UPDATE users SET is_verified = 1, is_approved = $isApproved, is_online = 1, last_login = NOW(), otp_code = NULL, otp_expires_at = NULL WHERE id = $userId");
            
            // Registration successful! Remove attempt record.
            $conn->query("DELETE FROM registration_attempts WHERE email = (SELECT email FROM users WHERE id = $userId)");
            logUserAction($conn, $userId, 'verify', 'Email verified successfully via OTP. Approved status: ' . ($isApproved ? 'YES' : 'NO (WAITING REVIEW)'));

            if ($isApproved) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['name']    = $user['name'];
                jsonResponse(['message' => 'Verification successful! Redirecting...', 'status' => 'success', 'approved' => true]);
            } else {
                jsonResponse(['message' => 'Email verified! Your Admin must now approve your account before you can sign in. Please wait for confirmation.', 'status' => 'success', 'approved' => false]);
            }
        }
        
        // Failed OTP means we increment attempts
        $uRes = $conn->query("SELECT email, referred_by_admin_id, referral_code FROM users WHERE id = $userId");
        if ($uRow = $uRes->fetch_assoc()) {
            $isBanned = handleRegistrationAttempt($conn, $uRow['email'], '*****', $code, '', $uRow['referred_by_admin_id'], true);
            if ($isBanned) {
                $conn->query("UPDATE users SET status = 'suspended' WHERE id = $userId"); // Also suspend just in case
                jsonResponse(['error' => 'Invalid OTP. 3 Failed attempts reached. This Gmail is now banned.'], 403);
            }
        }
        
        jsonResponse(['error' => 'Invalid or expired OTP code. Please request a new one.'], 400);
        break;

    // ── 2FA VERIFY ────────────────────────────────────────────────────────────
    case 'verify_2fa':
        $userId = intval($input['user_id'] ?? 0);
        $code   = Security::clean($input['otp_code'] ?? '');
        $stmt   = $conn->prepare("SELECT * FROM users WHERE id = ? AND otp_code = ? AND otp_expires_at > NOW()");
        $stmt->bind_param("is", $userId, $code);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['name'];
            $conn->query("UPDATE users SET is_online = 1, last_login = NOW(), otp_code = NULL, otp_expires_at = NULL WHERE id = $userId");
            logUserAction($conn, $userId, 'login_2fa', 'Login finalized after 2FA verification');
            jsonResponse(['message' => 'Identity verified! Redirecting...', 'status' => 'success']);
        }
        jsonResponse(['error' => 'Invalid or expired 2FA code.'], 400);
        break;

    // ── RESEND OTP ────────────────────────────────────────────────────────────
    case 'resend_otp':
        $userId = intval($input['user_id'] ?? 0);
        $stmt = $conn->prepare("SELECT name, email, is_verified FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!$user || $user['is_verified']) {
            jsonResponse(['error' => 'Account already verified or not found.'], 400);
        }

        $newOtp = sprintf("%06d", mt_rand(1, 999999));
        $update = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?");
        $update->bind_param("si", $newOtp, $userId);
        $update->execute();
        
        sendOTP($user['email'], $user['name'], $newOtp);
        logUserAction($conn, $userId, 'resend_otp', 'New OTP code requested and sent');

        jsonResponse(['message' => 'A new code has been sent to your email.', 'status' => 'success']);
        break;

    // ── LOGIN ─────────────────────────────────────────────────────────────────
    case 'login':
        $email = strtolower(Security::clean($input['email'] ?? ''));
        $pass  = $input['password'] ?? '';
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // 🛡️ RATE LIMIT CHECK
        $stmt = $conn->prepare("SELECT attempt_count, last_attempt FROM rate_limiting WHERE ip_address = ? AND action = 'login'");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $rate = $stmt->get_result()->fetch_assoc();
        if ($rate && $rate['attempt_count'] >= 5 && strtotime($rate['last_attempt']) > strtotime("-15 minutes")) {
            jsonResponse(['error' => 'Too many failed login attempts. Please wait 15 minutes.'], 429);
        }

        $stmt  = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            if ($user['status'] === 'suspended')
                jsonResponse(['error' => 'Your account has been suspended. Contact the administrator.'], 403);
            if (!$user['is_verified'])
                jsonResponse(['error' => 'Please verify your email first.', 'needs_verification' => true, 'user_id' => $user['id']], 403);
            if (!$user['is_approved'])
                jsonResponse(['error' => 'Account Pending Approval: Your Admin must activate your account before you can sign in.', 'status' => 'pending'], 403);
            
            if (password_verify($pass, $user['password_hash'])) {
                // Check for 2FA
                if ($user['two_factor_enabled']) {
                    $otp = sprintf("%06d", mt_rand(100000, 999999));
                    $conn->query("UPDATE users SET otp_code = '$otp', otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = " . $user['id']);
                    sendOTP($user['email'], $user['name'], $otp);
                    jsonResponse(['message' => '2FA required. A code has been sent to your email.', 'status' => '2fa_required', 'user_id' => $user['id']]);
                }

                // Success: Reset Rate Limit
                $conn->query("DELETE FROM rate_limiting WHERE ip_address = '$ip' AND action = 'login'");
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['name']    = $user['name'];
                $uid = $user['id'];
                $conn->query("UPDATE users SET is_online = 1, last_login = NOW(), last_seen = NOW() WHERE id = $uid");
                logUserAction($conn, $uid, 'login', 'Login via email/password');
                jsonResponse(['message' => 'Welcome back!', 'status' => 'success']);
            }
        }
        
        // Fail: Increment Rate Limit
        $conn->query("INSERT INTO rate_limiting (ip_address, action, attempt_count) VALUES ('$ip', 'login', 1) 
                      ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, last_attempt = NOW()");
        
        jsonResponse(['error' => 'Incorrect email or password.'], 401);
        break;

    // ── LOGOUT ────────────────────────────────────────────────────────────────
    case 'logout':
        if (isset($_SESSION['user_id'])) {
            $uid = intval($_SESSION['user_id']);
            $conn->query("UPDATE users SET is_online = 0, last_seen = NOW() WHERE id = $uid");
            logUserAction($conn, $uid, 'logout');
        }
        session_destroy();
        header('Location: login.php');
        exit;

    // ── HEARTBEAT (Keep-alive online status) ─────────────────────────────────
    case 'heartbeat':
        if (isset($_SESSION['user_id'])) {
            $uid = intval($_SESSION['user_id']);
            $conn->query("UPDATE users SET is_online = 1, last_seen = NOW(), last_activity = NOW() WHERE id = $uid");
            jsonResponse(['status' => 'ok']);
        }
        jsonResponse(['error' => 'Not authenticated'], 401);
        break;
}

jsonResponse(['error' => 'Invalid action.'], 400);
