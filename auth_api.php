<?php
require_once 'db.php';
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? '';

// System Email Settings for OTP
// System Email Settings for OTP
$smtpHost = 'smtp.gmail.com';
$smtpUser = 'YOUR_SYSTEM_EMAIL_HERE'; 
$smtpPass = 'YOUR_APP_PASSWORD_HERE';
$smtpPort = 465;

function sendOTPEmail($email, $name, $code) {
    global $smtpHost, $smtpUser, $smtpPass, $smtpPort;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $smtpPort;
        $mail->setFrom($smtpUser, 'BlastForge Security');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Your BlastForge Verification Code';
        $mail->Body    = "<h3>Hello $name,</h3><p>Your 6-digit verification code is: <strong>$code</strong></p><p>Please enter this code on the verification screen to activate your account.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

switch ($action) {
    case 'register':
        $name = $conn->real_escape_string($input['name'] ?? '');
        $email = $conn->real_escape_string($input['email'] ?? '');
        $pass = $input['password'] ?? '';
        $refCode = $conn->real_escape_string($input['referral_code'] ?? '');
        
        if (empty($name) || empty($email) || empty($pass)) {
            jsonResponse(['error' => 'All fields are required'], 400);
        }
        
        // Check if email exists
        $res = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($res->num_rows > 0) {
            jsonResponse(['error' => 'Email already registered. Please sign in.'], 400);
        }
        
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $otp = sprintf("%06d", mt_rand(1, 999999));
        
        // Hierarchy Logic
        $usersCountRes = $conn->query("SELECT COUNT(*) FROM users");
        $usersCount = $usersCountRes->fetch_row()[0];
        
        if ($usersCount === 0) {
            $role = 'super_admin';
            $refAdminId = "NULL";
        } else {
            // Find Referrer
            $referrerRes = $conn->query("SELECT id, role FROM users WHERE referral_code = '$refCode'");
            if ($referrerRes->num_rows === 0) {
                jsonResponse(['error' => 'Invalid Branch/Referral Code. Please contact your administrator.'], 400);
            }
            $referrer = $referrerRes->fetch_assoc();
            $refAdminId = $referrer['id'];

            if ($referrer['role'] === 'super_admin') {
                $role = 'admin'; // Registered by Super Admin as a new Branch Admin
            } else if ($referrer['role'] === 'admin') {
                $role = 'user'; // Registered by Branch Admin as a Member
            } else {
                jsonResponse(['error' => 'This referral code cannot be used for registration.'], 400);
            }
        }

        $sql = "INSERT INTO users (name, email, password_hash, role, otp_code, referred_by_admin_id) VALUES ('$name', '$email', '$hashed', '$role', '$otp', $refAdminId)";
        
        if ($conn->query($sql) === TRUE) {
            $userId = $conn->insert_id;
            if (sendOTPEmail($email, $name, $otp)) {
                jsonResponse(['message' => 'OTP sent to email', 'user_id' => $userId]);
            } else {
                jsonResponse(['error' => 'Account created but failed to send OTP email.'], 500);
            }
        } else {
            jsonResponse(['error' => 'Registration failed'], 500);
        }
        break;

    case 'verify':
        $userId = intval($input['user_id'] ?? 0);
        $code = $conn->real_escape_string($input['otp_code'] ?? '');
        
        $res = $conn->query("SELECT * FROM users WHERE id=$userId AND otp_code='$code' AND is_verified=0");
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $conn->query("UPDATE users SET is_verified=1, otp_code=NULL WHERE id=$userId");
            
            // Auto-Subscribe to Admin's list (User ID 1 is always the platform owner)
            $conn->query("INSERT IGNORE INTO subscribers (user_id, name, email) VALUES (1, '{$user['name']}', '{$user['email']}')");

            // Auto Login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            jsonResponse(['message' => 'Account verified and logged in!']);
        } else {
            jsonResponse(['error' => 'Invalid OTP Code'], 400);
        }
        break;

    case 'login':
        $email = $conn->real_escape_string($input['email'] ?? '');
        $pass = $input['password'] ?? '';
        
        $res = $conn->query("SELECT * FROM users WHERE email='$email'");
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if ($user['is_verified'] == 0) {
                jsonResponse(['error' => 'Please verify your email first. Contact support.', 'needs_verification' => true], 403);
            }
            if (password_verify($pass, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                jsonResponse(['message' => 'Logged in successfully']);
            } else {
                jsonResponse(['error' => 'Incorrect password'], 401);
            }
        } else {
            jsonResponse(['error' => 'Email not found'], 404);
        }
        break;

    case 'logout':
        session_destroy();
        header('Location: login.php');
        exit;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
?>
