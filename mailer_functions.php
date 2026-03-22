<?php
// BlastForge Central Mailing Engine
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

function sendOTP($toEmail, $userName, $otp) {
    if (empty(SYSTEM_EMAIL) || empty(SYSTEM_PASS)) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = SYSTEM_EMAIL;
        $mail->Password   = SYSTEM_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(SYSTEM_EMAIL, 'BlastForge Security');
        $mail->addAddress($toEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = "Verification Code: [ $otp ]";
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
                <h2 style='color: #2563eb;'>Verify Your Identity</h2>
                <p>Hello <b>$userName</b>,</p>
                <p>Your 6-digit verification code is below. This code will expire in 10 minutes.</p>
                <div style='background: #f1f5f9; padding: 20px; text-align: center; border-radius: 8px; font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: 5px; margin: 20px 0;'>
                    $otp
                </div>
                <p style='color: #64748b; font-size: 14px;'>If you did not request this code, please ignore this email.</p>
                <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 11px; color: #94a3b8; text-align: center;'>BlastForge Enterprise Mailing System</p>
            </div>";

        $mail->send();
        return true;
    } catch (Exception $e) { return false; }
}

function sendWelcomeEmail($toEmail, $userName) {
    if (empty(SYSTEM_EMAIL) || empty(SYSTEM_PASS)) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = SYSTEM_EMAIL;
        $mail->Password   = SYSTEM_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(SYSTEM_EMAIL, 'BlastForge Onboarding');
        $mail->addAddress($toEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = "Welcome to BlastForge Excellence";
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
                <h2 style='color: #10b981;'>Welcome Aboard, $userName!</h2>
                <p>Your account has been successfully verified. You now have full access to your campaign dashboard.</p>
                <div style='background: #ecfdf5; padding: 15px; border-radius: 8px; color: #065f46; margin: 20px 0;'>
                    <b>Your Journey Starts Now:</b><br/>
                    • Access your Dashboard<br/>
                    • Prepare your first Subscriber list<br/>
                    • Launch your Elite Email Campaign
                </div>
                <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 11px; color: #94a3b8; text-align: center;'>BlastForge Enterprise Customer Success</p>
            </div>";

        $mail->send();
        return true;
    } catch (Exception $e) { return false; }
}

function sendPasswordChangeHandshake($toEmail, $userName, $otp) {
    if (empty(SYSTEM_EMAIL) || empty(SYSTEM_PASS)) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = SYSTEM_EMAIL;
        $mail->Password   = SYSTEM_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(SYSTEM_EMAIL, 'BlastForge Identity');
        $mail->addAddress($toEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = "CRITICAL: Password Change Authorization Code [ $otp ]";
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; padding: 25px; border: 2px solid #ef4444; border-radius: 12px;'>
                <h2 style='color: #ef4444;'>Security Alert</h2>
                <p>Hello <b>$userName</b>,</p>
                <p>An administrator has initiated a password change for your BlastForge account. To authorize this change, please provide the following code to your administrator:</p>
                <p style='color: #ef4444; font-size: 13px; font-weight: bold;'>⚠️ This code will expire in 10 minutes for security purposes.</p>
                <div style='background: #fee2e2; padding: 20px; text-align: center; border-radius: 8px; font-size: 32px; font-weight: 900; color: #b91c1c; letter-spacing: 10px; margin: 25px 0;'>
                    $otp
                </div>
                <p style='color: #6b7280; font-size: 13px;'>If you did not authorize this change, please contact your Super Admin immediately to secure your account.</p>
                <hr style='border: 0; border-top: 1px solid #fee2e2; margin: 25px 0;'>
                <p style='font-size: 11px; color: #999; text-align: center;'>BlastForge Enterprise Identity Security System</p>
            </div>";

        $mail->send();
        return true;
    } catch (Exception $e) { return false; }
}
?>
