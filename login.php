<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlastForge - Login</title>
    <link rel="stylesheet" href="styles.css">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <style>
        body { justify-content: center; align-items: center; }
        .auth-container {
            width: 100%; max-width: 400px;
            background: var(--panel-bg); border: 1px solid var(--panel-border);
            backdrop-filter: var(--glass-blur); padding: 3rem 2rem;
            border-radius: 1.5rem; box-shadow: 0 25px 50px rgba(0,0,0,0.4);
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-header ion-icon { font-size: 3rem; color: var(--accent-primary); }
        .auth-header h2 { font-size: 1.8rem; margin-top: 0.5rem; }
        .switch-mode { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted); cursor: pointer; }
        .switch-mode span { color: var(--accent-secondary); font-weight: 500; }
        .switch-mode span:hover { text-decoration: underline; }
        .panel-view { display: none; }
        .panel-view.active { display: block; }
    </style>
</head>
<body>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="auth-container">
        
        <!-- LOGIN PANEL -->
        <div id="login-panel" class="panel-view active">
            <div class="auth-header">
                <ion-icon name="finger-print-outline"></ion-icon>
                <h2>Welcome Back</h2>
            </div>
            <form id="login-form">
                <div class="form-group"><label>Email</label><input type="email" id="login-email" class="form-control" required></div>
                <div class="form-group"><label>Password</label><input type="password" id="login-password" class="form-control" required></div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem;">Sign In</button>
            </form>
            <div class="switch-mode" onclick="showPanel('register-panel')">New here? <span>Create an account</span></div>
        </div>

        <!-- REGISTER PANEL -->
        <div id="register-panel" class="panel-view">
            <div class="auth-header">
                <ion-icon name="person-add-outline"></ion-icon>
                <h2>Join BlastForge</h2>
            </div>
            <form id="register-form">
                <div class="form-group"><label>Full Name</label><input type="text" id="reg-name" class="form-control" required></div>
                <div class="form-group">
                    <label>Referral Code</label>
                    <input type="text" id="reg-referral" class="form-control" placeholder="Provided by your Admin" required>
                </div>
                <div class="form-group"><label>Email</label><input type="email" id="reg-email" class="form-control" required></div>
                <div class="form-group"><label>Password</label><input type="password" id="reg-password" class="form-control" minlength="6" required></div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem;">Register</button>
            </form>
            <div class="switch-mode" onclick="showPanel('login-panel')">Already have an account? <span>Sign In</span></div>
        </div>

        <!-- OTP VERIFY PANEL -->
        <div id="otp-panel" class="panel-view">
            <div class="auth-header">
                <ion-icon name="shield-checkmark-outline"></ion-icon>
                <h2>Verify Email</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">We sent a 6-digit code to your email.</p>
            </div>
            <form id="otp-form">
                <div class="form-group"><input type="text" id="otp-code" class="form-control" placeholder="123456" maxlength="6" style="text-align:center; font-size:1.5rem; letter-spacing: 0.5rem;" required></div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem;">Verify & Login</button>
            </form>
        </div>

    </div>

    <!-- Toast Notifications -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        let pendingUserId = null;

        function showPanel(id) {
            document.querySelectorAll('.panel-view').forEach(p => p.classList.remove('active'));
            document.getElementById(id).classList.add('active');
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? 'checkmark-circle' : 'alert-circle';
            toast.innerHTML = `<ion-icon name="${icon}" style="font-size: 1.5rem; color: var(--${type})"></ion-icon><div>${message}</div>`;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 3000);
        }

        // Handle Login
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const prev = btn.innerHTML; btn.innerHTML = 'Wait...'; btn.disabled = true;

            const res = await fetch('auth_api.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: document.getElementById('login-email').value,
                    password: document.getElementById('login-password').value
                })
            });
            const data = await res.json();
            btn.innerHTML = prev; btn.disabled = false;

            if (res.ok) {
                window.location.href = 'index.php';
            } else {
                showToast(data.error, 'error');
            }
        });

        // Handle Register
        document.getElementById('register-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const prev = btn.innerHTML; btn.innerHTML = 'Sending OTP...'; btn.disabled = true;

            const res = await fetch('auth_api.php?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: document.getElementById('reg-name').value,
                    email: document.getElementById('reg-email').value,
                    password: document.getElementById('reg-password').value,
                    referral_code: document.getElementById('reg-referral').value
                })
            });
            const data = await res.json();
            btn.innerHTML = prev; btn.disabled = false;

            if (res.ok) {
                pendingUserId = data.user_id;
                showPanel('otp-panel');
                showToast('OTP code sent to email!', 'success');
            } else {
                showToast(data.error, 'error');
            }
        });

        // Handle OTP Verify
        document.getElementById('otp-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const prev = btn.innerHTML; btn.innerHTML = 'Verifying...'; btn.disabled = true;

            const res = await fetch('auth_api.php?action=verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: pendingUserId,
                    otp_code: document.getElementById('otp-code').value
                })
            });
            const data = await res.json();
            btn.innerHTML = prev; btn.disabled = false;

            if (res.ok) {
                showToast('Account verified!', 'success');
                setTimeout(() => window.location.href = 'index.php', 1000);
            } else {
                showToast(data.error, 'error');
            }
        });
    </script>
</body>
</html>
