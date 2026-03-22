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

switch ($action) {

    // ── REGISTER ──────────────────────────────────────────────────────────────
    case 'register':
        $name     = Security::clean($input['name'] ?? '');
        $email    = strtolower(Security::clean($input['email'] ?? ''));
        $pass     = $input['password'] ?? '';
        $refCode  = Security::clean($input['referral_code'] ?? '');

        if (empty($name) || empty($email) || empty($pass))
            jsonResponse(['error' => 'All fields are required.'], 400);

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
                $role = ($row['role'] === 'super_admin') ? 'admin' : 'user';
            } else {
                jsonResponse(['error' => 'Invalid Branch Code. Contact your administrator.'], 400);
            }
        } else {
            jsonResponse(['error' => 'A Branch/Referral Code is required to register.'], 400);
        }

        $otp = sprintf("%06d", mt_rand(1, 999999));
        $isVerified = 0; // Forced verification for all roles
        
        $ins = $conn->prepare("INSERT INTO users (name, email, password_hash, role, otp_code, otp_expires_at, referred_by_admin_id, is_verified) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?, ?)");
        $ins->bind_param("sssssii", $name, $email, $hashed, $role, $otp, $refAdminId, $isVerified);

        if ($ins->execute()) {
            $newUserId = $conn->insert_id;
            // Auto-generate referral code
            $autoRefCode = strtoupper(substr(md5($newUserId . time()), 0, 8));
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
            $conn->query("UPDATE users SET is_verified = 1, is_online = 1, last_login = NOW(), otp_code = NULL, otp_expires_at = NULL WHERE id = $userId");
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['name'];
            logUserAction($conn, $userId, 'verify', 'Email verified successfully via OTP');
            jsonResponse(['message' => 'Verification successful!', 'status' => 'success']);
        }
        jsonResponse(['error' => 'Invalid or expired OTP code. Please request a new one.'], 400);
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
        $stmt  = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            if ($user['status'] === 'suspended')
                jsonResponse(['error' => 'Your account has been suspended. Contact the administrator.'], 403);
            if (!$user['is_verified'])
                jsonResponse(['error' => 'Please verify your email first.', 'needs_verification' => true, 'user_id' => $user['id']], 403);
            if (password_verify($pass, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['name']    = $user['name'];
                // Mark online & log login
                $uid = $user['id'];
                $conn->query("UPDATE users SET is_online = 1, last_login = NOW(), last_seen = NOW() WHERE id = $uid");
                logUserAction($conn, $uid, 'login', 'Login via email/password');
                jsonResponse(['message' => 'Welcome back!', 'status' => 'success']);
            }
        }
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
