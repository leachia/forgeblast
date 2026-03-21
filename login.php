<?php
require_once 'config.php';
session_destroy();
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlastForge | Secure Login</title>
    <link rel="stylesheet" href="styles.css">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .auth-wrap { width:100%; max-width:460px; }
        .auth-card { background:var(--card-bg); border:1px solid var(--border); border-radius:1.5rem; padding:3rem 2.5rem; backdrop-filter:blur(20px); animation:slideUpFade 0.6s forwards; }
        .brand { text-align:center; margin-bottom:2.5rem; }
        .brand-logo { font-size:2.2rem; font-weight:900; letter-spacing:-2px; }
        .brand-logo span { color:var(--primary); }
        .brand-sub { color:var(--text-dim); font-size:0.7rem; font-weight:700; letter-spacing:3px; margin-top:0.25rem; }
        .tab-switcher { display:grid; grid-template-columns:1fr 1fr; background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:0.75rem; padding:4px; gap:4px; margin-bottom:2rem; }
        .tab-btn-auth { background:transparent; border:none; color:var(--text-dim); padding:0.7rem; border-radius:0.55rem; cursor:pointer; font-weight:700; font-size:0.75rem; letter-spacing:1px; transition:all 0.25s; }
        .tab-btn-auth.active { background:var(--primary); color:#fff; box-shadow:0 4px 15px var(--primary-glow); }
        .tab-form { display:none; }
        .tab-form.active { display:block; animation:slideUpFade 0.4s forwards; }
        .form-field { margin-bottom:1.25rem; }
        .form-label { display:block; font-size:0.68rem; font-weight:700; color:var(--text-dim); letter-spacing:1.5px; margin-bottom:0.5rem; text-transform:uppercase; }
        .form-input { width:100%; background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:0.65rem; padding:0.85rem 1rem; color:#fff; font-size:0.9rem; font-family:inherit; transition:border-color 0.2s; box-sizing:border-box; }
        .form-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-glow); }
        .form-input::placeholder { color:rgba(255,255,255,0.2); }
        .submit-btn { width:100%; background:var(--primary); border:none; border-radius:0.75rem; color:#fff; font-weight:800; font-size:0.85rem; letter-spacing:1px; padding:1rem; cursor:pointer; transition:all 0.25s; margin-top:0.5rem; box-shadow:0 8px 25px var(--primary-glow); }
        .submit-btn:hover { transform:translateY(-2px); box-shadow:0 12px 35px var(--primary-glow); }
        .submit-btn:disabled { opacity:0.6; transform:none; cursor:not-allowed; }
        .error-msg { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#f87171; padding:0.75rem 1rem; border-radius:0.65rem; font-size:0.82rem; margin-top:1rem; display:none; }
        .error-msg.show { display:block; animation:slideUpFade 0.3s forwards; }
        .success-msg { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:#34d399; padding:0.75rem 1rem; border-radius:0.65rem; font-size:0.82rem; margin-top:1rem; display:none; }
        .success-msg.show { display:block; }
        .divider { text-align:center; color:var(--text-dim); font-size:0.7rem; font-weight:700; letter-spacing:2px; margin:1.5rem 0; position:relative; }
        .divider::before,.divider::after { content:''; position:absolute; top:50%; width:calc(50% - 40px); height:1px; background:var(--border); }
        .divider::before { left:0; } .divider::after { right:0; }
        /* OTP Modal */
        .otp-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(20px); z-index:9999; display:none; align-items:center; justify-content:center; }
        .otp-overlay.open { display:flex; }
        .otp-card { background:var(--card-bg); border:1px solid var(--border); border-radius:1.5rem; padding:3rem; text-align:center; width:380px; animation:slideUpFade 0.4s forwards; }
        .otp-input { width:100%; text-align:center; font-size:2.5rem; font-weight:900; letter-spacing:12px; background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:0.75rem; padding:1rem; color:#fff; font-family:inherit; box-sizing:border-box; }
        @keyframes slideUpFade { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body class="mesh-bg">
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="brand">
                <div class="brand-logo"><span>Blast</span>Forge</div>
                <div class="brand-sub">Secure Access Terminal</div>
            </div>

            <div class="tab-switcher">
                <button class="tab-btn-auth active" onclick="switchTab('login')" id="btn-login">Sign In</button>
                <button class="tab-btn-auth" onclick="switchTab('register')" id="btn-register">Register</button>
            </div>

            <!-- ── LOGIN FORM ─────────────────────────────────────── -->
            <div id="login-form" class="tab-form active">
                <div class="form-field">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="login-email" class="form-input" placeholder="you@example.com" required>
                </div>
                <div class="form-field">
                    <label class="form-label">Password</label>
                    <input type="password" id="login-password" class="form-input" placeholder="••••••••" required>
                </div>
                <div class="error-msg" id="login-error"></div>
                <button class="submit-btn" id="login-btn" onclick="doLogin()">
                    <ion-icon name="log-in-outline" style="vertical-align:middle; margin-right:6px;"></ion-icon>
                    Sign In
                </button>
            </div>

            <!-- ── REGISTER FORM ──────────────────────────────────── -->
            <div id="register-form" class="tab-form">
                <div class="form-field">
                    <label class="form-label">Full Name</label>
                    <input type="text" id="reg-name" class="form-input" placeholder="Juan dela Cruz" required>
                </div>
                <div class="form-field">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="reg-email" class="form-input" placeholder="you@example.com" required>
                </div>
                <div class="form-field">
                    <label class="form-label">Password</label>
                    <input type="password" id="reg-password" class="form-input" placeholder="Min. 6 characters" required>
                </div>
                <div class="form-field">
                    <label class="form-label">
                        <ion-icon name="key-outline" style="vertical-align:middle;"></ion-icon>
                        Branch / Referral Code
                    </label>
                    <input type="text" id="reg-code" class="form-input" placeholder="Provided by your Admin" required style="letter-spacing:2px; text-transform:uppercase;">
                    <small style="color:var(--text-dim); font-size:0.72rem; margin-top:0.4rem; display:block;">Ask your administrator for your branch code.</small>
                </div>
                <div class="error-msg" id="reg-error"></div>
                <div class="success-msg" id="reg-success"></div>
                <button class="submit-btn" id="reg-btn" onclick="doRegister()">
                    <ion-icon name="person-add-outline" style="vertical-align:middle; margin-right:6px;"></ion-icon>
                    Create Account
                </button>
            </div>
        </div>
    </div>

    <!-- ── OTP VERIFICATION MODAL ─────────────────────────────────── -->
    <div class="otp-overlay" id="otp-overlay">
        <div class="otp-card">
            <ion-icon name="shield-checkmark-outline" style="font-size:3.5rem; color:var(--primary); margin-bottom:1rem;"></ion-icon>
            <h2 style="margin-bottom:0.5rem;">Verify Your Email</h2>
            <p style="color:var(--text-dim); font-size:0.85rem; margin-bottom:2rem;">Enter the 6-digit code sent to your email address.</p>
            <input type="text" id="otp-code" class="otp-input" placeholder="000000" maxlength="6">
            <div class="error-msg" id="otp-error" style="margin-bottom:0;"></div>
            <button class="submit-btn" style="margin-top:1.5rem;" onclick="verifyOTP()">
                <ion-icon name="checkmark-circle-outline" style="vertical-align:middle; margin-right:6px;"></ion-icon>
                Verify & Login
            </button>
            <input type="hidden" id="pending-user-id">
        </div>
    </div>

    <script>
        function switchTab(type) {
            document.querySelectorAll('.tab-form').forEach(f => f.classList.remove('active'));
            document.querySelectorAll('.tab-btn-auth').forEach(b => b.classList.remove('active'));
            document.getElementById(`${type}-form`).classList.add('active');
            document.getElementById(`btn-${type}`).classList.add('active');
            ['login-error','reg-error','reg-success','otp-error'].forEach(id => {
                const el = document.getElementById(id);
                if(el) el.classList.remove('show');
            });
        }

        function showError(id, msg) {
            const el = document.getElementById(id);
            el.textContent = msg;
            el.classList.add('show');
        }

        async function doLogin() {
            const btn = document.getElementById('login-btn');
            const email = document.getElementById('login-email').value.trim();
            const password = document.getElementById('login-password').value;
            document.getElementById('login-error').classList.remove('show');

            if (!email || !password) return showError('login-error', 'Please fill in all fields.');
            btn.disabled = true; btn.textContent = 'Signing in...';

            try {
                const res = await fetch('auth_api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                const data = await res.json();
                if (res.ok) {
                    btn.textContent = 'Welcome! Redirecting...';
                    window.location.href = 'index.php';
                } else {
                    showError('login-error', data.error || 'Login failed.');
                    btn.disabled = false;
                    btn.innerHTML = '<ion-icon name="log-in-outline" style="vertical-align:middle; margin-right:6px;"></ion-icon> Sign In';
                }
            } catch(e) {
                showError('login-error', 'Connection error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<ion-icon name="log-in-outline" style="vertical-align:middle; margin-right:6px;"></ion-icon> Sign In';
            }
        }

        async function doRegister() {
            const btn = document.getElementById('reg-btn');
            const name = document.getElementById('reg-name').value.trim();
            const email = document.getElementById('reg-email').value.trim();
            const password = document.getElementById('reg-password').value;
            const referral_code = document.getElementById('reg-code').value.trim().toUpperCase();
            document.getElementById('reg-error').classList.remove('show');
            document.getElementById('reg-success').classList.remove('show');

            if (!name || !email || !password || !referral_code)
                return showError('reg-error', 'All fields including the branch code are required.');
            if (password.length < 6)
                return showError('reg-error', 'Password must be at least 6 characters.');

            btn.disabled = true; btn.textContent = 'Creating Account...';

            try {
                const res = await fetch('auth_api.php?action=register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, email, password, referral_code })
                });
                const data = await res.json();
                if (res.ok && data.user_id) {
                    document.getElementById('pending-user-id').value = data.user_id;
                    document.getElementById('otp-overlay').classList.add('open');
                } else {
                    showError('reg-error', data.error || 'Registration failed.');
                }
            } catch(e) {
                showError('reg-error', 'Connection error. Please try again.');
            }
            btn.disabled = false;
            btn.innerHTML = '<ion-icon name="person-add-outline" style="vertical-align:middle; margin-right:6px;"></ion-icon> Create Account';
        }

        async function verifyOTP() {
            const userId = document.getElementById('pending-user-id').value;
            const otp_code = document.getElementById('otp-code').value.trim();
            document.getElementById('otp-error').classList.remove('show');

            if (otp_code.length !== 6) return showError('otp-error', 'Enter the 6-digit code.');

            try {
                const res = await fetch('auth_api.php?action=verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, otp_code })
                });
                const data = await res.json();
                if (res.ok) {
                    window.location.href = 'index.php';
                } else {
                    showError('otp-error', data.error || 'Invalid code. Try again.');
                }
            } catch(e) {
                showError('otp-error', 'Connection error.');
            }
        }

        // Allow Enter key on login
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                if (document.getElementById('login-form').classList.contains('active')) doLogin();
                else if (document.getElementById('register-form').classList.contains('active')) doRegister();
            }
        });
    </script>
</body>
</html>
