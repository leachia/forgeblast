<?php
ob_start();
require_once 'config.php';
require_once 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Fetch logged-in user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Auto-generate referral code for Super Admin if missing
if ($user['role'] === 'super_admin' && empty($user['referral_code'])) {
    $newCode = 'ADM-' . strtoupper(substr(md5(uniqid()), 0, 6));
    $conn->query("UPDATE users SET referral_code = '$newCode' WHERE id = $userId");
    $user['referral_code'] = $newCode;
}

// Fetch all users for Super Admin
$allUsers = [];
if ($user['role'] === 'super_admin') {
    $allUsersRes = $conn->query("
        SELECT u.*,
               (SELECT COUNT(*) FROM campaigns c WHERE c.user_id = u.id) AS campaign_count,
               IFNULL((SELECT SUM(sent_count) FROM campaigns c WHERE c.user_id = u.id), 0) AS emails_sent
        FROM users u ORDER BY u.role DESC, u.name ASC
    ");
    while ($row = $allUsersRes->fetch_assoc()) $allUsers[] = $row;
}

// Fetch activity logs for Super Admin
$activityLogs = [];
if ($user['role'] === 'super_admin') {
    $logRes = $conn->query("
        SELECT ul.*, u.name, u.email, u.avatar, u.role
        FROM user_logs ul
        JOIN users u ON u.id = ul.user_id
        ORDER BY ul.created_at DESC
        LIMIT 100
    ");
    while ($row = $logRes->fetch_assoc()) $activityLogs[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlastForge | My Profile</title>
    <link rel="stylesheet" href="styles.css">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <style>
        .profile-page { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .profile-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2.5rem; }
        .profile-grid { display:grid; grid-template-columns:300px 1fr; gap:2rem; }
        @media(max-width:900px) { .profile-grid { grid-template-columns:1fr; } }

        /* Sidebar */
        .sidebar-card { background:var(--card-bg); border:1px solid var(--border); border-radius:1.25rem; padding:2rem; text-align:center; }
        .avatar-wrapper { position:relative; width:120px; height:120px; margin:0 auto 1.5rem; }
        .avatar-wrapper img { width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid var(--primary); }
        .avatar-edit-btn { position:absolute; bottom:0; right:0; width:34px; height:34px; background:var(--primary); border:none; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.9rem; }
        .online-badge { display:inline-flex; align-items:center; gap:0.4rem; background:rgba(52,211,153,0.1); color:#34d399; border:1px solid rgba(52,211,153,0.2); border-radius:20px; padding:0.3rem 0.8rem; font-size:0.72rem; font-weight:700; margin-top:0.5rem; }
        .online-dot { width:8px; height:8px; border-radius:50%; background:#34d399; animation:pulse 2s infinite; }
        .role-badge { display:inline-block; background:rgba(139,92,246,0.1); color:var(--primary-bright); border:1px solid rgba(139,92,246,0.2); border-radius:20px; padding:0.35rem 1rem; font-size:0.72rem; font-weight:800; letter-spacing:1.5px; margin-top:0.5rem; }
        .referral-box { background:rgba(0,0,0,0.2); border:1px dashed var(--primary); border-radius:0.75rem; padding:0.75rem; margin-top:1.25rem; }
        .referral-box .code { font-size:1.3rem; font-weight:900; color:var(--primary); letter-spacing:3px; font-family:monospace; }
        .referral-box small { display:block; color:var(--text-dim); font-size:0.68rem; margin-top:0.3rem; }

        /* Main content tabs */
        .content-card { background:var(--card-bg); border:1px solid var(--border); border-radius:1.25rem; overflow:hidden; }
        .content-tabs { display:flex; gap:0; border-bottom:1px solid var(--border); overflow-x:auto; }
        .ctab { background:transparent; border:none; color:var(--text-dim); padding:1rem 1.5rem; cursor:pointer; font-weight:700; font-size:0.78rem; letter-spacing:1px; white-space:nowrap; border-bottom:2px solid transparent; transition:all 0.2s; }
        .ctab.active { color:var(--primary-bright); border-bottom-color:var(--primary); background:rgba(139,92,246,0.05); }
        .ctab-panel { display:none; padding:2rem; }
        .ctab-panel.active { display:block; }

        /* Forms */
        .form-section-title { font-size:0.7rem; font-weight:800; color:var(--text-dim); letter-spacing:2px; text-transform:uppercase; border-bottom:1px solid var(--border); padding-bottom:0.75rem; margin-bottom:1.5rem; }
        .fields-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.5rem; }
        @media(max-width:600px) { .fields-grid { grid-template-columns:1fr; } }
        .field-label { display:block; font-size:0.7rem; font-weight:700; color:var(--text-dim); letter-spacing:1px; text-transform:uppercase; margin-bottom:0.4rem; }
        .field-input { width:100%; background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:0.6rem; padding:0.75rem 1rem; color:#fff; font-size:0.85rem; font-family:inherit; box-sizing:border-box; transition:border-color 0.2s; }
        .field-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-glow); }
        .field-input:disabled { opacity:0.5; cursor:not-allowed; }
        select.field-input option { background:#1a1a2e; }

        /* User Cards for Super Admin */
        .users-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:1.25rem; }
        .user-card { background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:1rem; padding:1.5rem; text-align:center; transition:all 0.25s; position:relative; cursor:pointer; }
        .user-card:hover { border-color:var(--primary); transform:translateY(-2px); box-shadow:0 10px 30px rgba(0,0,0,0.3); }
        .user-card-avatar { width:70px; height:70px; border-radius:50%; object-fit:cover; border:2px solid var(--primary); margin-bottom:0.75rem; }
        .user-card-name { font-weight:700; font-size:0.95rem; margin-bottom:0.25rem; }
        .user-card-email { color:var(--text-dim); font-size:0.72rem; word-break:break-all; margin-bottom:0.5rem; }
        .user-card-stats { display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-top:1rem; background:rgba(0,0,0,0.2); border-radius:0.5rem; padding:0.5rem; }
        .stat-box { text-align:center; }
        .stat-num { font-size:1.1rem; font-weight:800; color:var(--primary-bright); }
        .stat-lbl { font-size:0.6rem; color:var(--text-dim); text-transform:uppercase; }
        .card-actions { display:flex; gap:0.4rem; position:absolute; top:0.75rem; right:0.75rem; }
        .card-action-btn { width:28px; height:28px; border:none; border-radius:0.4rem; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.8rem; transition:all 0.2s; }
        .online-indicator { position:absolute; top:0.75rem; left:0.75rem; width:10px; height:10px; border-radius:50%; background:#888; border:2px solid var(--card-bg); }
        .online-indicator.online { background:#34d399; animation:pulse 2s infinite; }

        /* Activity Log Table */
        .log-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
        .log-table th { color:var(--text-dim); font-size:0.68rem; letter-spacing:1px; text-transform:uppercase; padding:0.75rem 1rem; text-align:left; border-bottom:1px solid var(--border); }
        .log-table td { padding:0.75rem 1rem; border-bottom:1px solid rgba(255,255,255,0.04); }
        .log-table tr:last-child td { border-bottom:none; }
        .log-action { display:inline-flex; align-items:center; gap:0.4rem; padding:0.25rem 0.7rem; border-radius:20px; font-size:0.7rem; font-weight:700; }
        .log-action.login { background:rgba(52,211,153,0.1); color:#34d399; }
        .log-action.logout { background:rgba(239,68,68,0.1); color:#f87171; }
        .log-action.register { background:rgba(96,165,250,0.1); color:#60a5fa; }
        .log-action.campaign_sent { background:rgba(250,204,21,0.1); color:#fbbf24; }
        .log-action.profile_update { background:rgba(167,139,250,0.1); color:#a78bfa; }

        /* Master Code Banner */
        .master-code-banner { background:linear-gradient(135deg, rgba(66,133,244,0.08), rgba(139,92,246,0.08)); border:1px solid rgba(139,92,246,0.2); border-radius:1rem; padding:1.5rem; display:flex; align-items:center; justify-content:space-between; margin-bottom:2rem; flex-wrap:wrap; gap:1rem; }

        /* Edit Modal */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(20px); z-index:9000; display:none; align-items:center; justify-content:center; overflow-y:auto; padding:2rem; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:var(--card-bg); border:1px solid var(--border); border-radius:1.25rem; padding:2rem; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; animation:slideUpFade 0.3s forwards; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; }
        .modal-close { background:transparent; border:none; color:var(--text-dim); cursor:pointer; font-size:1.5rem; }

        /* Google OAuth in Profile */
        .oauth-btn { display:flex; align-items:center; gap:0.75rem; background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:0.75rem; padding:1rem 1.25rem; cursor:pointer; width:100%; color:#fff; font-family:inherit; font-size:0.85rem; font-weight:600; transition:all 0.2s; text-decoration:none; }
        .oauth-btn:hover { background:rgba(255,255,255,0.08); border-color:var(--primary); }
        .oauth-connected { display:flex; align-items:center; gap:0.75rem; background:rgba(52,211,153,0.08); border:1px solid rgba(52,211,153,0.2); border-radius:0.75rem; padding:1rem 1.25rem; color:#34d399; font-weight:600; font-size:0.85rem; }

        @keyframes slideUpFade { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
    </style>
</head>
<body>
<div class="mesh-bg"></div>
<div class="profile-page">

    <!-- ── Header ──────────────────────────────────────────────────────── -->
    <div class="profile-header">
        <button onclick="window.location.href='index.php'" style="background:transparent; border:none; color:var(--text-dim); cursor:pointer; display:flex; align-items:center; gap:0.5rem; font-family:inherit; font-weight:700; font-size:0.85rem; letter-spacing:1px;">
            <ion-icon name="chevron-back-outline"></ion-icon> Dashboard
        </button>
        <h1 style="font-size:1.5rem; font-weight:800;">My <span style="color:var(--primary);">Profile</span></h1>
        <a href="auth_api.php?action=logout" style="background:transparent; border:1px solid var(--border); color:var(--text-dim); padding:0.5rem 1rem; border-radius:0.5rem; text-decoration:none; font-size:0.8rem; font-weight:600;">
            <ion-icon name="log-out-outline" style="vertical-align:middle;"></ion-icon> Logout
        </a>
    </div>

    <?php if ($user['role'] === 'super_admin'): ?>
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!--                    SUPER ADMIN DASHBOARD                          -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->

    <!-- Master Code Banner -->
    <div class="master-code-banner">
        <div>
            <div style="font-weight:800; font-size:1rem; margin-bottom:0.25rem;"><ion-icon name="key-outline" style="color:var(--primary); vertical-align:middle;"></ion-icon> Master Referral Code</div>
            <div style="color:var(--text-dim); font-size:0.8rem;">Share this code with your new users so they can register.</div>
        </div>
        <div style="text-align:center;">
            <div class="referral-box" style="min-width:180px; margin:0;">
                <div class="code" id="master-code"><?php echo htmlspecialchars($user['referral_code']); ?></div>
                <small>Click to copy</small>
            </div>
        </div>
    </div>

    <!-- Content Tabs for Super Admin -->
    <div class="content-card">
        <div class="content-tabs">
            <button class="ctab active" onclick="switchCTab(this,'ctab-users')"><ion-icon name="people-outline" style="vertical-align:middle;"></ion-icon> All Users</button>
            <button class="ctab" onclick="switchCTab(this,'ctab-myprofile')"><ion-icon name="person-outline" style="vertical-align:middle;"></ion-icon> My Profile</button>
        </div>

        <!-- ── TAB: All Users ──────────────────────────────────────────── -->
        <div class="ctab-panel active" id="ctab-users">
            <div id="users-grid" class="users-grid">
                <?php foreach ($allUsers as $u): ?>
                <?php 
                    // Hide regular users who were referred by OTHER admins
                    if ($u['id'] !== $userId && $u['role'] === 'user' && !empty($u['referred_by_admin_id']) && $u['referred_by_admin_id'] != $userId) {
                        continue; 
                    }
                ?>
                <div class="user-card" onclick='viewUserDetails(<?php echo json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)'>
                    <!-- Online Indicator -->
                    <div class="online-indicator <?php echo $u['is_online'] ? 'online' : ''; ?>" title="<?php echo $u['is_online'] ? 'Online Now' : 'Offline'; ?>"></div>

                    <!-- Action Buttons (only for non-self) -->
                    <?php if ($u['id'] !== $userId): ?>
                    <div class="card-actions">
                        <button class="card-action-btn" style="background:var(--primary); color:#fff;" onclick='event.stopPropagation(); openEditModal(<?php echo json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)' title="Edit">
                            <ion-icon name="create-outline"></ion-icon>
                        </button>
                        <button class="card-action-btn" style="background:rgba(239,68,68,0.8); color:#fff;" onclick="event.stopPropagation(); deleteUser(<?php echo $u['id']; ?>, '<?php echo addslashes($u['name']); ?>')" title="Delete">
                            <ion-icon name="trash-outline"></ion-icon>
                        </button>
                    </div>
                    <?php endif; ?>

                    <img class="user-card-avatar" src="<?php echo htmlspecialchars($u['avatar']); ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($u['name']); ?>&background=random'">
                    <div class="user-card-name"><?php echo htmlspecialchars($u['name']); ?></div>
                    <div class="user-card-email"><?php echo htmlspecialchars($u['email']); ?></div>
                    <?php if (!empty($u['phone'])): ?>
                    <div style="color:var(--primary-bright); font-size:0.75rem; font-weight:600;"><?php echo htmlspecialchars($u['phone']); ?></div>
                    <?php endif; ?>

                    <!-- Role Selector -->
                    <?php if ($u['id'] !== $userId && $u['role'] !== 'super_admin'): ?>
                    <select style="width:100%; margin-top:0.75rem; background:rgba(255,255,255,0.05); border:1px solid var(--border); border-radius:0.5rem; padding:0.4rem; color:#fff; font-size:0.75rem; font-family:inherit;"
                            onchange="event.stopPropagation(); changeRole(<?php echo $u['id']; ?>, this.value)"
                            onclick="event.stopPropagation()">
                        <option value="user" <?php echo $u['role']==='user' ? 'selected' : ''; ?>>Member (User)</option>
                        <option value="admin" <?php echo $u['role']==='admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                    <?php else: ?>
                    <div style="margin-top:0.75rem;"><span class="role-badge"><?php echo strtoupper($u['role']); ?></span></div>
                    <?php endif; ?>

                    <!-- Stats -->
                    <div class="user-card-stats">
                        <div class="stat-box">
                            <div class="stat-num"><?php echo $u['campaign_count']; ?></div>
                            <div class="stat-lbl">Campaigns</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num"><?php echo $u['emails_sent']; ?></div>
                            <div class="stat-lbl">Emails Sent</div>
                        </div>
                    </div>
                    <?php if (!empty($u['last_login'])): ?>
                    <div style="color:var(--text-dim); font-size:0.65rem; margin-top:0.75rem;">Last login: <?php echo date('M d, Y h:i A', strtotime($u['last_login'])); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="user-details-view" style="display:none; animation:slideUpFade 0.3s forwards;">
                <!-- Dynamically populated -->
            </div>
        </div>

        <!-- ── TAB: My Profile (Super Admin own profile) ──────────────── -->
        <div class="ctab-panel" id="ctab-myprofile">
            <div style="display:grid; grid-template-columns:auto 1fr; gap:2rem; align-items:start;">
                <div style="text-align:center;">
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid var(--primary);" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random'">
                    <div class="online-badge" style="margin-top:0.75rem;">
                        <span class="online-dot"></span> Online
                    </div>
                </div>
                <div>
                    <form id="sa-profile-form" onsubmit="saveSAProfile(event)">
                        <div class="form-section-title">Personal Info</div>
                        <div class="fields-grid">
                            <div>
                                <label class="field-label">Full Name</label>
                                <input type="text" name="name" class="field-input" value="<?php echo htmlspecialchars($user['name']); ?>">
                            </div>
                            <div>
                                <label class="field-label">Email (Login)</label>
                                <input type="email" class="field-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                        </div>

                        <div class="form-section-title">SMTP Configuration</div>
                        <div class="fields-grid">
                            <div>
                                <label class="field-label">SMTP Host</label>
                                <input type="text" name="smtp_host" class="field-input" placeholder="smtp.gmail.com" value="<?php echo htmlspecialchars($user['smtp_host'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">SMTP Port</label>
                                <input type="number" name="smtp_port" class="field-input" placeholder="465" value="<?php echo htmlspecialchars($user['smtp_port'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">SMTP Username</label>
                                <input type="text" name="smtp_user" class="field-input" placeholder="you@gmail.com" value="<?php echo htmlspecialchars($user['smtp_user'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">SMTP Password</label>
                                <input type="password" name="smtp_pass" class="field-input" placeholder="App password" value="<?php echo htmlspecialchars($user['smtp_pass'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-section-title">Gmail OAuth2</div>
                        <?php if (!empty($user['google_refresh_token'])): ?>
                        <div class="oauth-connected">
                            <ion-icon name="checkmark-circle" style="font-size:1.3rem;"></ion-icon>
                            Gmail OAuth2 Connected & Active
                        </div>
                        <?php else: ?>
                        <a href="google_auth.php" class="oauth-btn">
                            <img src="https://www.gstatic.com/images/branding/product/1x/gsa_512dp.png" style="width:22px; height:22px;">
                            Connect Gmail via OAuth2
                        </a>
                        <?php endif; ?>

                        <button type="submit" style="margin-top:2rem; background:var(--primary); border:none; border-radius:0.75rem; color:#fff; font-weight:800; font-size:0.85rem; padding:0.9rem 2rem; cursor:pointer; box-shadow:0 8px 25px var(--primary-glow);">
                            <ion-icon name="save-outline" style="vertical-align:middle; margin-right:6px;"></ion-icon> Save Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!--                   REGULAR USER / ADMIN PROFILE                    -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="profile-grid">
        <!-- Sidebar -->
        <div>
            <div class="sidebar-card">
                <div class="avatar-wrapper">
                    <img id="avatar-preview" src="<?php echo htmlspecialchars($user['avatar']); ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random'">
                    <button class="avatar-edit-btn" onclick="document.getElementById('avatar-file').click()">
                        <ion-icon name="camera-outline"></ion-icon>
                    </button>
                    <input type="file" id="avatar-file" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                </div>

                <h3 style="font-weight:800; font-size:1.1rem; margin-bottom:0.25rem;"><?php echo htmlspecialchars($user['name']); ?></h3>
                <div style="color:var(--text-dim); font-size:0.8rem; margin-bottom:0.5rem;"><?php echo htmlspecialchars($user['email']); ?></div>
                <divistyle="margin-bottom:0.5rem;"><span class="role-badge"><?php echo strtoupper($user['role']); ?></span></divistyle>
                <div class="online-badge">
                    <span class="online-dot"></span> Online Now
                </div>

                <?php if (!empty($user['referral_code'])): ?>
                <div class="referral-box" style="cursor:pointer;" onclick="copyCode('<?php echo $user['referral_code']; ?>')">
                    <div style="font-size:0.65rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.25rem;">Your Branch Code</div>
                    <div class="code"><?php echo htmlspecialchars($user['referral_code']); ?></div>
                    <small>Click to copy &amp; share with your members</small>
                </div>
                <?php endif; ?>

                <?php if ($user['last_login']): ?>
                <div style="margin-top:1rem; font-size:0.7rem; color:var(--text-dim);">
                    Last login: <?php echo date('M d, Y h:i A', strtotime($user['last_login'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div>
            <div class="content-card">
                <div class="content-tabs">
                    <button class="ctab active" onclick="switchCTab(this,'tab-personal')"><ion-icon name="person-outline" style="vertical-align:middle;"></ion-icon> Personal</button>
                    <button class="ctab" onclick="switchCTab(this,'tab-social')"><ion-icon name="share-social-outline" style="vertical-align:middle;"></ion-icon> Social</button>
                    <button class="ctab" onclick="switchCTab(this,'tab-smtp')"><ion-icon name="mail-outline" style="vertical-align:middle;"></ion-icon> Email Setup</button>
                    <button class="ctab" onclick="switchCTab(this,'tab-settings')"><ion-icon name="settings-outline" style="vertical-align:middle;"></ion-icon> Settings</button>
                </div>

                <form id="profile-form" onsubmit="saveProfile(event)">
                    <!-- ── Personal ──────────────────────────────────── -->
                    <div class="ctab-panel active" id="tab-personal">
                        <div class="form-section-title">Identity & Personal Info</div>
                        <div class="fields-grid">
                            <div>
                                <label class="field-label">First Name</label>
                                <input type="text" name="firstName" class="field-input" value="<?php echo htmlspecialchars($user['firstName'] ?? ''); ?>" placeholder="Juan">
                            </div>
                            <div>
                                <label class="field-label">Last Name</label>
                                <input type="text" name="lastName" class="field-input" value="<?php echo htmlspecialchars($user['lastName'] ?? ''); ?>" placeholder="dela Cruz">
                            </div>
                            <div>
                                <label class="field-label">Age</label>
                                <input type="number" name="age" class="field-input" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>" placeholder="25">
                            </div>
                            <div>
                                <label class="field-label">Gender</label>
                                <select name="gender" class="field-input">
                                    <option value="">Select</option>
                                    <option value="Male" <?php if(($user['gender']??'')==='Male') echo 'selected'; ?>>Male</option>
                                    <option value="Female" <?php if(($user['gender']??'')==='Female') echo 'selected'; ?>>Female</option>
                                    <option value="Other" <?php if(($user['gender']??'')==='Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Birthday</label>
                                <input type="date" name="birthday" class="field-input" value="<?php echo htmlspecialchars($user['birthday'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">Location / Province</label>
                                <input type="text" name="location" class="field-input" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="Manila, Philippines">
                            </div>
                        </div>
                        <div style="margin-bottom:1.25rem;">
                            <label class="field-label">Full Address</label>
                            <input type="text" name="address" class="field-input" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" placeholder="Block/Lot, Street, Barangay">
                        </div>
                        <div>
                            <label class="field-label">Identity Document Info</label>
                            <textarea name="id_info" class="field-input" rows="2" placeholder="ID Number, type, etc."><?php echo htmlspecialchars($user['id_info'] ?? ''); ?></textarea>
                        </div>
                        <div style="margin-top:1.25rem;">
                            <label class="field-label">Bio / Signature</label>
                            <textarea name="bio" class="field-input" rows="3" placeholder="Tell something about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- ── Social ────────────────────────────────────── -->
                    <div class="ctab-panel" id="tab-social">
                        <div class="form-section-title">Contact & Social Links</div>
                        <div class="fields-grid">
                            <div>
                                <label class="field-label"><ion-icon name="call-outline" style="vertical-align:middle;"></ion-icon> Phone Number</label>
                                <input type="tel" name="phone" class="field-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+63 9xx xxxx xxx">
                            </div>
                            <div>
                                <label class="field-label"><ion-icon name="mail-outline" style="vertical-align:middle;"></ion-icon> Personal Gmail</label>
                                <input type="email" name="gmail" class="field-input" value="<?php echo htmlspecialchars($user['gmail'] ?? ''); ?>" placeholder="username@gmail.com">
                            </div>
                            <div>
                                <label class="field-label"><ion-icon name="logo-facebook" style="color:#1877F2; vertical-align:middle;"></ion-icon> Facebook</label>
                                <input type="url" name="facebook" class="field-input" value="<?php echo htmlspecialchars($user['facebook'] ?? ''); ?>" placeholder="https://facebook.com/...">
                            </div>
                            <div>
                                <label class="field-label"><ion-icon name="logo-instagram" style="color:#E4405F; vertical-align:middle;"></ion-icon> Instagram</label>
                                <input type="url" name="instagram" class="field-input" value="<?php echo htmlspecialchars($user['instagram'] ?? ''); ?>" placeholder="https://instagram.com/...">
                            </div>
                        </div>
                    </div>

                    <!-- ── Email Setup ─────────────────────────────── -->
                    <div class="ctab-panel" id="tab-smtp">
                        <div class="form-section-title">Gmail OAuth2 (Recommended)</div>
                        <?php if (!empty($user['google_refresh_token'])): ?>
                        <div class="oauth-connected" style="margin-bottom:1.5rem;">
                            <ion-icon name="checkmark-circle" style="font-size:1.3rem;"></ion-icon>
                            Gmail OAuth2 Connected & Active
                        </div>
                        <?php else: ?>
                        <a href="google_auth.php" class="oauth-btn" style="margin-bottom:1.5rem;">
                            <img src="https://www.gstatic.com/images/branding/product/1x/gsa_512dp.png" style="width:22px; height:22px;">
                            Connect Gmail via OAuth2
                        </a>
                        <?php endif; ?>

                        <div class="form-section-title">Primary SMTP (Priority 1)</div>
                        <div class="fields-grid">
                            <div>
                                <label class="field-label">SMTP Host</label>
                                <input type="text" name="smtp_host" class="field-input" placeholder="smtp.gmail.com" value="<?php echo htmlspecialchars($user['smtp_host'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">SMTP Port</label>
                                <input type="number" name="smtp_port" class="field-input" placeholder="587" value="<?php echo htmlspecialchars($user['smtp_port'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">SMTP User</label>
                                <input type="text" name="smtp_user" class="field-input" placeholder="user@gmail.com" value="<?php echo htmlspecialchars($user['smtp_user'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">SMTP Pass</label>
                                <input type="password" name="smtp_pass" class="field-input" placeholder="••••••••">
                            </div>
                        </div>

                        <div class="form-section-title">Backup SMTP (Failover)</div>
                        <div class="fields-grid">
                            <div>
                                <label class="field-label">Backup Host</label>
                                <input type="text" name="backup_smtp_host" class="field-input" placeholder="smtp.sendgrid.net" value="<?php echo htmlspecialchars($user['backup_smtp_host'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">Backup Port</label>
                                <input type="number" name="backup_smtp_port" class="field-input" placeholder="587" value="<?php echo htmlspecialchars($user['backup_smtp_port'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">Backup User</label>
                                <input type="text" name="backup_smtp_user" class="field-input" value="<?php echo htmlspecialchars($user['backup_smtp_user'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="field-label">Backup Pass</label>
                                <input type="password" name="backup_smtp_pass" class="field-input" placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                    <!-- ── Settings ─────────────────────────────────── -->
                    <div class="ctab-panel" id="tab-settings">
                        <div class="form-section-title">Preferences</div>
                        <label style="display:flex; align-items:center; gap:1rem; cursor:pointer; margin-bottom:1rem;">
                            <input type="checkbox" name="email_notifications" value="1" <?php echo ($user['email_notifications'] ? 'checked' : ''); ?> style="width:18px; height:18px;">
                            <span>Email Notifications</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:1rem; cursor:pointer;">
                            <input type="checkbox" name="dark_mode" value="1" <?php echo ($user['dark_mode'] ? 'checked' : ''); ?> style="width:18px; height:18px;">
                            <span>Dark Mode</span>
                        </label>
                    </div>

                    <!-- Save Button (visible across all tabs for regular users) -->
                    <div style="padding:0 2rem 2rem; display:flex; justify-content:flex-end;">
                        <button type="submit" style="background:var(--primary); border:none; border-radius:0.75rem; color:#fff; font-weight:800; font-size:0.85rem; padding:0.9rem 2rem; cursor:pointer; box-shadow:0 8px 25px var(--primary-glow);">
                            <ion-icon name="save-outline" style="vertical-align:middle; margin-right:6px;"></ion-icon> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Toast ──────────────────────────────────────────────────────────── -->
<div id="toast-container" style="position:fixed; bottom:2rem; right:2rem; z-index:99999; display:flex; flex-direction:column; gap:0.75rem;"></div>

<!-- ── Edit User Modal (Super Admin) ──────────────────────────────────── -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="font-weight:800;">Edit User Record</h3>
            <button class="modal-close" onclick="closeEditModal()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <form id="edit-user-form" onsubmit="saveEditUser(event)">
            <input type="hidden" id="eu-id" name="id">
            <div class="fields-grid">
                <div>
                    <label class="field-label">First Name</label>
                    <input type="text" id="eu-firstName" name="firstName" class="field-input">
                </div>
                <div>
                    <label class="field-label">Last Name</label>
                    <input type="text" id="eu-lastName" name="lastName" class="field-input">
                </div>
                <div>
                    <label class="field-label">Email</label>
                    <input type="email" id="eu-email" name="email" class="field-input">
                </div>
                <div>
                    <label class="field-label">Phone</label>
                    <input type="text" id="eu-phone" name="phone" class="field-input">
                </div>
                <div>
                    <label class="field-label">Age</label>
                    <input type="number" id="eu-age" name="age" class="field-input">
                </div>
                <div>
                    <label class="field-label">Gender</label>
                    <select id="eu-gender" name="gender" class="field-input">
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Birthday</label>
                    <input type="date" id="eu-birthday" name="birthday" class="field-input">
                </div>
                <div>
                    <label class="field-label">Location</label>
                    <input type="text" id="eu-location" name="location" class="field-input">
                </div>
            </div>
            <div style="margin-bottom:1.25rem;">
                <label class="field-label">Address</label>
                <input type="text" id="eu-address" name="address" class="field-input">
            </div>
            <div style="margin-bottom:1.25rem;">
                <label class="field-label">ID Info</label>
                <textarea id="eu-id-info" name="id_info" class="field-input" rows="2"></textarea>
            </div>
            <div class="fields-grid">
                <div>
                    <label class="field-label">System Role</label>
                    <select id="eu-role" name="role" class="field-input">
                        <option value="user">Standard User (Member)</option>
                        <option value="admin">Branch Admin</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Account Status</label>
                    <select id="eu-status" name="status" class="field-input">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Verified?</label>
                    <select id="eu-verified" name="is_verified" class="field-input">
                        <option value="1">Yes - Verified</option>
                        <option value="0">No - Pending</option>
                    </select>
                </div>
            </div>
            <div class="form-section-title">SMTP Settings</div>
            <div class="fields-grid">
                <div>
                    <label class="field-label">SMTP Host</label>
                    <input type="text" id="eu-smtp-host" name="smtp_host" class="field-input" placeholder="smtp.gmail.com">
                </div>
                <div>
                    <label class="field-label">SMTP Port</label>
                    <input type="number" id="eu-smtp-port" name="smtp_port" class="field-input" placeholder="465">
                </div>
                <div>
                    <label class="field-label">SMTP User</label>
                    <input type="text" id="eu-smtp-user" name="smtp_user" class="field-input">
                </div>
                <div>
                    <label class="field-label">SMTP Pass</label>
                    <input type="password" id="eu-smtp-pass" name="smtp_pass" class="field-input">
                </div>
            </div>
            <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
                <button type="submit" style="flex:1; background:var(--primary); border:none; border-radius:0.75rem; color:#fff; font-weight:800; padding:0.9rem; cursor:pointer;">Save Changes</button>
                <button type="button" style="background:rgba(239,68,68,0.8); border:none; border-radius:0.75rem; color:#fff; font-weight:800; padding:0.9rem 1.25rem; cursor:pointer;" onclick="deleteUser(document.getElementById('eu-id').value, 'this user')">
                    <ion-icon name="trash-outline"></ion-icon>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
window.csrfToken = '<?php echo Security::generateCSRF(); ?>';

// ── Toast ─────────────────────────────────────────────────────────────────
function showToast(msg, type='success') {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.style.cssText = `background:var(--card-bg); border:1px solid ${type==='success'?'rgba(52,211,153,0.3)':'rgba(239,68,68,0.3)'}; color:${type==='success'?'#34d399':'#f87171'}; padding:0.85rem 1.25rem; border-radius:0.75rem; font-size:0.85rem; font-weight:600; max-width:320px; animation:slideUpFade 0.3s forwards; box-shadow:0 10px 30px rgba(0,0,0,0.3);`;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ── Tab Switcher ──────────────────────────────────────────────────────────
function switchCTab(btn, id) {
    btn.closest('.content-card, .content-tabs').querySelectorAll ? null : null;
    const card = btn.closest('.content-card') || document.querySelector('.content-card');
    card.querySelectorAll('.ctab').forEach(b => b.classList.remove('active'));
    card.querySelectorAll('.ctab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(id).classList.add('active');
}

// ── Copy referral code ────────────────────────────────────────────────────
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => showToast('Code copied: ' + code));
}
document.getElementById('master-code')?.addEventListener('click', function() {
    copyCode(this.textContent.trim());
});


// ── Preview Avatar ────────────────────────────────────────────────────────
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('avatar-preview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Save Regular User Profile ─────────────────────────────────────────────
async function saveProfile(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const prev = btn.innerHTML; btn.disabled = true; btn.textContent = 'Saving...';

    const fd = new FormData(e.target);
    // handle avatar
    const avatarFile = document.getElementById('avatar-file')?.files[0];
    if (avatarFile) fd.append('avatar', avatarFile);
    fd.append('email_notifications', e.target.querySelector('[name="email_notifications"]')?.checked ? '1' : '0');
    fd.append('dark_mode', e.target.querySelector('[name="dark_mode"]')?.checked ? '1' : '0');

    try {
        const res = await fetch('api.php?action=updateProfile', { method:'POST', headers:{'X-CSRF-TOKEN': window.csrfToken}, body:fd });
        const data = await res.json();
        if (res.ok) showToast(data.message || 'Profile saved!');
        else showToast(data.error || 'Error saving profile.', 'error');
    } catch(ex) {
        showToast('Error: ' + ex.message, 'error');
    }
    btn.disabled = false; btn.innerHTML = prev;
}

// ── Save Super Admin own Profile ──────────────────────────────────────────
async function saveSAProfile(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const res = await fetch('api.php?action=updateProfile', { method:'POST', headers:{'X-CSRF-TOKEN': window.csrfToken}, body:fd });
        const data = await res.json();
        if (res.ok) showToast(data.message || 'Profile saved!');
        else showToast(data.error || 'Error.', 'error');
    } catch(ex) {
        showToast('Error: ' + ex.message, 'error');
    }
}

// ── Change Role ───────────────────────────────────────────────────────────
async function changeRole(userId, newRole) {
    if (!confirm(`Change role to "${newRole}"?`)) return;
    try {
        const res = await fetch('api.php?action=updateRole', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken},
            body: JSON.stringify({ target_id: userId, role: newRole })
        });
        const data = await res.json();
        if (res.ok) showToast('Role updated!');
        else showToast(data.error || 'Failed.', 'error');
    } catch(e) { showToast('Connection error.', 'error'); }
}

// ── Delete User ───────────────────────────────────────────────────────────
async function deleteUser(userId, name) {
    if (!confirm(`⚠️ PERMANENTLY delete "${name}" and ALL their data? This cannot be undone.`)) return;
    try {
        const res = await fetch(`api.php?action=deleteUser&id=${userId}`, { method:'DELETE', headers:{'X-CSRF-TOKEN': window.csrfToken} });
        const data = await res.json();
        if (res.ok) { showToast('User deleted.'); setTimeout(() => location.reload(), 1200); }
        else showToast(data.error || 'Failed.', 'error');
    } catch(e) { showToast('Connection error.', 'error'); }
}

// ── Open Edit Modal ───────────────────────────────────────────────────────
function openEditModal(u) {
    document.getElementById('eu-id').value       = u.id;
    document.getElementById('eu-firstName').value = u.firstName || '';
    document.getElementById('eu-lastName').value  = u.lastName  || '';
    document.getElementById('eu-email').value     = u.email     || '';
    document.getElementById('eu-phone').value     = u.phone     || '';
    document.getElementById('eu-age').value       = u.age       || '';
    document.getElementById('eu-gender').value    = u.gender    || '';
    document.getElementById('eu-birthday').value  = u.birthday  || '';
    document.getElementById('eu-location').value  = u.location  || '';
    document.getElementById('eu-address').value   = u.address   || '';
    document.getElementById('eu-id-info').value   = u.id_info   || '';
    document.getElementById('eu-role').value      = u.role      || 'user';
    document.getElementById('eu-status').value    = u.status    || 'active';
    document.getElementById('eu-verified').value  = u.is_verified ? '1' : '0';
    document.getElementById('eu-smtp-host').value = u.smtp_host || '';
    document.getElementById('eu-smtp-port').value = u.smtp_port || '';
    document.getElementById('eu-smtp-user').value = u.smtp_user || '';
    document.getElementById('eu-smtp-pass').value = '';
    document.getElementById('edit-modal').classList.add('open');
}
function closeEditModal() { document.getElementById('edit-modal').classList.remove('open'); }

// ── Save Edit User ────────────────────────────────────────────────────────
async function saveEditUser(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const prev = btn.innerHTML; btn.disabled = true; btn.textContent = 'Saving...';
    const fd = new FormData(e.target);
    try {
        const res = await fetch('api.php?action=adminUpdateUser', { method:'POST', headers:{'X-CSRF-TOKEN': window.csrfToken}, body:fd });
        const data = await res.json();
        if (res.ok) { showToast('User updated!'); closeEditModal(); setTimeout(() => location.reload(), 1200); }
        else showToast(data.error || 'Error.', 'error');
    } catch(ex) { showToast('Error: ' + ex.message, 'error'); }
    btn.disabled = false; btn.innerHTML = prev;
}

// ── Heartbeat (keeps is_online updated every 60s) ─────────────────────────
async function heartbeat() {
    try { await fetch('auth_api.php?action=heartbeat', { method:'POST' }); } catch(e) {}
}
heartbeat();
setInterval(heartbeat, 60000);

// ── Visit tracking - mark offline on tab close ────────────────────────────
window.addEventListener('beforeunload', () => {
    navigator.sendBeacon('auth_api.php?action=heartbeat');
});

// ── User Details View (Transactions) ──────────────────────────────────────
window.allUsersData = <?php echo json_encode($allUsers ?? [], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>;

function escapeHtml(unsafe) {
    if(!unsafe) return '';
    return (unsafe+'').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function viewUserDetails(u) {
    document.getElementById('users-grid').style.display = 'none';
    const view = document.getElementById('user-details-view');
    view.style.display = 'block';
    
    // Header & Edit Button
    view.innerHTML = `
        <div style="margin-bottom: 1.5rem;">
            <button onclick="closeUserDetails()" style="background:transparent; border:none; color:var(--text-dim); cursor:pointer; font-weight:700;"><ion-icon name="arrow-back-outline" style="vertical-align:middle; margin-right:4px;"></ion-icon> Back to All Users</button>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 2.5rem; background:rgba(255,255,255,0.02); border:1px solid var(--border); padding:2rem; border-radius:1rem;">
            <div style="display:flex; align-items:center; gap: 1.5rem;">
                <img src="${escapeHtml(u.avatar)}" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid var(--primary);" onerror="this.src=\'https://ui-avatars.com/api/?name=${encodeURIComponent(u.name)}&background=random\'">
                <div>
                    <h2 style="font-size:1.8rem; font-weight:800; margin-bottom:0.25rem; color:var(--primary-bright);">${escapeHtml(u.name)}</h2>
                    <div style="color:var(--text-dim); font-size:0.9rem; margin-bottom:0.5rem;">${escapeHtml(u.email)}</div>
                    <span class="role-badge" style="margin-top:0;">${u.role.toUpperCase()}</span>
                </div>
            </div>
            <button class="btn-premium" onclick="openEditModal(${escapeHtml(JSON.stringify(u))})" style="padding: 0.75rem 1.5rem; font-size:0.85rem;"><ion-icon name="create-outline"></ion-icon> Edit User Info</button>
        </div>
        
        <h3 style="font-size:1.1rem; border-bottom:1px solid var(--border); padding-bottom:0.75rem; margin-bottom:1rem; color:var(--text-dim); letter-spacing:1px; text-transform:uppercase;">Recent Transactions (Campaigns)</h3>
        <div id="user-campaigns-list">
            <div style="color:var(--text-dim); padding:2rem 0; font-style:italic;"><ion-icon name="sync-outline" style="animation:pulse 1s infinite;"></ion-icon> Loading transactions...</div>
        </div>
    `;

    // Fetch Transactions
    fetch('api.php?action=getCampaigns&target_user_id=' + u.id)
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('user-campaigns-list');
            if(data.campaigns && data.campaigns.length > 0) {
                let rows = '';
                data.campaigns.forEach(c => {
                    rows += `
                        <tr>
                            <td style="color:var(--text-dim); font-size:0.8rem;">${new Date(c.created_at).toLocaleString()}</td>
                            <td style="font-weight:700; color:#fff;">${escapeHtml(c.name)}</td>
                            <td style="color:var(--text-dim); font-size:0.85rem;">${escapeHtml(u.name)} <br><span style="font-size:0.75rem; opacity:0.7;">via ${escapeHtml(u.smtp_user || u.email)}</span></td>
                            <td style="color:var(--primary-bright); font-weight:800; text-align:center;">${c.sent_count}</td>
                            <td style="text-align:right;">
                                ${c.read_count > 0 ? `<span style="display:inline-flex; align-items:center; gap:0.3rem; background:rgba(52,211,153,0.1); color:#34d399; padding:0.3rem 0.6rem; border-radius:20px; font-size:0.7rem; font-weight:700;"><span class="online-dot" style="animation:none;"></span> ${c.read_count} Opened</span>` : `<span style="color:var(--text-dim); font-size:0.7rem; font-weight:700;">Unread</span>`}
                            </td>
                        </tr>
                    `;
                });
                
                list.innerHTML = `
                    <div style="overflow-x:auto;">
                        <table class="table-premium" style="width:100%;">
                            <thead>
                                <tr>
                                    <th style="padding:1rem;">Date Overview</th>
                                    <th style="padding:1rem;">Email Subject Name</th>
                                    <th style="padding:1rem;">Sent From</th>
                                    <th style="text-align:center; padding:1rem;">Total Sent</th>
                                    <th style="text-align:right; padding:1rem;">Live Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                list.innerHTML = `<div style="background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:0.75rem; color:var(--text-dim); padding:3rem; text-align:center;"><ion-icon name="document-text-outline" style="font-size:3rem; opacity:0.5; margin-bottom:1rem;"></ion-icon><br>This user hasn't sent any email campaigns yet.</div>`;
            }

            // Also fetch the specific user's activity log!
            fetch('api.php?action=getUserLogs&target_user_id=' + u.id)
                .then(lr => lr.json())
                .then(lData => {
                    let logHtml = `<h3 style="font-size:1.1rem; border-bottom:1px solid var(--border); padding-bottom:0.75rem; margin-top:3rem; margin-bottom:1.5rem; color:var(--text-dim); letter-spacing:1px; text-transform:uppercase;">Personal Activity Log</h3>`;
                    if(lData.logs && lData.logs.length > 0) {
                        logHtml += `<div style="background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:1rem; overflow:hidden;"><table class="table-premium" style="width:100%;">
                            <tbody>`;
                        lData.logs.forEach(l => {
                            let clr = 'var(--text-dim)';
                            if(l.action === 'login') clr = '#34d399';
                            if(l.action === 'logout') clr = '#f87171';
                            if(l.action === 'campaign_sent') clr = '#8b5cf6';
                            logHtml += `<tr>
                                <td style="color:var(--text-dim); font-size:0.75rem; width:150px;">${new Date(l.created_at).toLocaleString()}</td>
                                <td style="font-weight:800; color:${clr}; font-size:0.75rem; text-transform:uppercase; width:120px;">${l.action.replace('_',' ')}</td>
                                <td style="color:var(--text-dim); font-size:0.8rem;">${escapeHtml(l.notes)}</td>
                                <td style="color:var(--text-dim); font-size:0.7rem; font-family:monospace; text-align:right;">${escapeHtml(l.ip_address)}</td>
                            </tr>`;
                        });
                        logHtml += `</tbody></table></div>`;
                    } else {
                        logHtml += `<div style="color:var(--text-dim); font-style:italic;">No activity records found.</div>`;
                    }
                    view.innerHTML += logHtml;
                });
            
            // Show registered users under this admin
            const myUsers = window.allUsersData.filter(x => x.referred_by_admin_id == u.id);
            if(myUsers.length > 0) {
                let userRows = myUsers.map(x => `
                    <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:1rem; padding:1.25rem; display:flex; align-items:center; gap:1rem; transition:transform 0.2s; cursor:pointer;" onclick="viewUserDetails(${escapeHtml(JSON.stringify(x).replace(/'/g, '&#39;'))})">
                        <img src="${escapeHtml(x.avatar)}" style="width:48px; height:48px; border-radius:50%; border:2px solid var(--primary);" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(x.name)}&background=random'">
                        <div style="flex:1;">
                            <h4 style="margin:0; font-weight:800; color:var(--primary-bright); font-size:0.95rem;">${escapeHtml(x.name)}</h4>
                            <div style="color:var(--text-dim); font-size:0.75rem;">${escapeHtml(x.email)}</div>
                            <span class="role-badge" style="margin-top:0.4rem; padding:0.2rem 0.6rem; font-size:0.6rem;">${x.role.toUpperCase()}</span>
                        </div>
                        <button onclick="event.stopPropagation(); openEditModal(${escapeHtml(JSON.stringify(x).replace(/'/g, '&#39;'))})" style="background:transparent; border:none; color:var(--primary); font-size:1.2rem; cursor:pointer;"><ion-icon name="create-outline"></ion-icon></button>
                    </div>
                `).join('');

                view.innerHTML += `
                    <h3 style="font-size:1.1rem; border-bottom:1px solid var(--border); padding-bottom:0.75rem; margin-top:3rem; margin-bottom:1.5rem; color:var(--text-dim); letter-spacing:1px; text-transform:uppercase;">Registered Users Under This Admin</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1rem;">
                        ${userRows}
                    </div>
                `;
            }
        })
        .catch(err => {
            document.getElementById('user-campaigns-list').innerHTML = '<div style="color:#f87171; padding:2rem 0;">Error loading transactions.</div>';
        });
}

function closeUserDetails() {
    document.getElementById('user-details-view').style.display = 'none';
    document.getElementById('users-grid').style.display = 'grid';
}

</script>
</body>
</html>
