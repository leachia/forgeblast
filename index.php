<?php 
require_once 'config.php'; 
require_once 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; } 

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$userName = $_SESSION['name'] ?? 'User';

$masterCode = '';
if ($role === 'super_admin') {
    $res = $conn->query("SELECT referral_code FROM users WHERE id = $userId");
    $u = $res->fetch_assoc();
    if (empty($u['referral_code'])) {
        $masterCode = 'ADM-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $conn->query("UPDATE users SET referral_code = '$masterCode' WHERE id = $userId");
    } else { $masterCode = $u['referral_code']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlastForge | Campaign Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="mesh-bg"></div>

    <aside class="sidebar">
        <div style="font-size:2rem; font-weight:800; margin-bottom:4rem; padding-left:1rem; letter-spacing:-1px;">
            <span style="color:var(--primary);">Blast</span>Forge
        </div>
        <nav>
            <a href="#" class="nav-link active" onclick="showTab('overview', event)"><ion-icon name="speedometer-outline"></ion-icon> Overview</a>
            <a href="#" class="nav-link" onclick="showTab('audience', event)"><ion-icon name="people-outline"></ion-icon> Subscribers</a>
            <a href="#" class="nav-link" onclick="showTab('blasts', event)"><ion-icon name="paper-plane-outline"></ion-icon> Campaigns</a>
            <?php if ($role === 'super_admin' || $role === 'admin'): ?>
                <a href="#" class="nav-link" onclick="showTab('team', event)"><ion-icon name="shield-checkmark-outline"></ion-icon> Team Management</a>
            <?php endif; ?>
            <?php if ($role === 'super_admin'): ?>
                <a href="#" class="nav-link" onclick="showTab('platform', event)"><ion-icon name="shield-outline"></ion-icon> Audit History</a>
                <a href="#" class="nav-link" onclick="showTab('deleted', event)"><ion-icon name="trash-outline"></ion-icon> Deleted History</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link" style="margin-top:2rem; border-top:1px solid var(--border); padding-top:2rem;"><ion-icon name="settings-sharp"></ion-icon> Portal Settings</a>
            <a href="auth_api.php?action=logout" class="nav-link" style="color:var(--danger); opacity:0.8;"><ion-icon name="log-out-outline"></ion-icon> Deauthorize</a>
        </nav>
    </aside>

    <main style="margin-left: 280px; padding: 4rem;">
        <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4rem; animation: slideUpFade 0.7s forwards;">
            <div>
                <h1 style="font-size:3rem; font-weight:800; letter-spacing:-1px; margin-bottom:0.5rem;"><?php echo date('H') < 12 ? 'Good Morning' : (date('H') < 18 ? 'Good Afternoon' : 'Good Evening'); ?>, <?php echo explode(' ', $userName)[0]; ?></h1>
                <p style="color:#94a3b8; font-size:1.1rem;">Commanding the platform as <span style="color:var(--primary-bright); font-weight:600;"><?php echo strtoupper($role); ?></span></p>
            </div>
            <div style="display:flex; gap:1.5rem; align-items:center;">
                <?php if ($role === 'super_admin' || $role === 'admin'): ?>
                <button class="btn-premium" style="background:rgba(255,255,255,0.02); border:1px solid var(--border); box-shadow:none;" onclick="openModal('onboard-modal')">
                    <ion-icon name="person-add-outline"></ion-icon> ADD NEW USER
                </button>
                <?php endif; ?>
                <button class="btn-premium" onclick="openModal('campaign-modal')">
                    <ion-icon name="rocket-outline"></ion-icon> NEW CAMPAIGN
                </button>
                <div id="worker-status-badge" style="display:inline-flex; align-items:center; gap:0.75rem; background:rgba(0,0,0,0.3); border:1px solid var(--border); padding:0.65rem 1.25rem; border-radius:30px; font-size:0.75rem; font-weight:800; letter-spacing:0.5px; cursor:help; margin-left:1rem; box-shadow:0 0 20px rgba(0,0,0,0.25);" title="BlastForge Engine Mission Control">
                     <div class="pulse-dot" id="worker-status-dot" style="width:10px; height:10px; border-radius:50%; background:#888;"></div>
                     <span style="opacity:0.6; font-size:0.6rem;">ENGINE</span>
                     <span id="worker-status-text">SYNCING...</span>
                     <span id="queue-status-text" style="background:rgba(255,255,255,0.05); padding:2px 8px; border-radius:10px; font-size:0.6rem; margin-left:0.5rem; color:var(--primary-bright);">0 PENDING</span>
                </div>
            </div>
        </header>

        <style>
        .pulse-dot {
            transition: 0.3s;
        }
        .pulse-online {
            animation: pulse-glow 2s infinite ease-in-out;
        }
        @keyframes pulse-glow {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(52, 211, 153, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
        }
        </style>

        <?php if ($role === 'super_admin'): ?>
            <div class="card-premium" style="margin-bottom:2.5rem; display:flex; align-items:center; justify-content:space-between; border-left:6px solid var(--primary); animation: slideUpFade 0.8s forwards;">
                <div>
                    <h4 style="color:var(--primary-bright); margin-bottom:0.25rem;">MASTER REGISTRATION KEY</h4>
                    <p style="color:#94a3b8; font-size:0.85rem;">Use this code to authorize new Branch Administrators.</p>
                </div>
                <div style="font-size:1.8rem; font-weight:800; color:#fff; letter-spacing:4px; font-family:monospace; background:rgba(0,0,0,0.3); padding:1rem 2rem; border-radius:16px; border:1px solid var(--border);">
                    <?php echo $masterCode; ?>
                </div>
            </div>
        <?php endif; ?>

        <section id="overview-tab" class="tab-content" style="display:block;">
            <div class="stats-grid">
                <?php if ($role === 'super_admin'): ?>
                <div class="stat-card">
                    <span class="stat-label">Total Platform Users</span>
                    <span class="stat-value" id="stat-users">--</span>
                </div>
                <?php endif; ?>
                <div class="stat-card">
                    <span class="stat-label">Total Subscribers</span>
                    <span class="stat-value" id="stat-subs">--</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Campaigns Sent</span>
                    <span class="stat-value" id="stat-camps">--</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Emails Delivered</span>
                    <span class="stat-value" id="stat-emails">--</span>
                </div>
                <div class="stat-card" style="border-top: 4px solid #34d399;">
                    <span class="stat-label">Total Opens</span>
                    <span class="stat-value" id="stat-opens" style="color:#34d399;">--</span>
                </div>
                <div class="stat-card" style="border-top: 4px solid #60a5fa;">
                    <span class="stat-label">Total Clicks</span>
                    <span class="stat-value" id="stat-clicks" style="color:#60a5fa;">--</span>
                </div>
                <?php if ($role === 'admin'): ?>
                <div class="stat-card" style="border-top: 4px solid var(--primary-bright);">
                    <span class="stat-label">Monthly Quota (<?php echo date('F'); ?>)</span>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.5rem;">
                        <span class="stat-value" id="stat-quota" style="font-size:1.8rem;">--</span>
                        <span style="font-size:0.8rem; color:var(--text-dim);"> / 250k</span>
                    </div>
                    <div style="background:rgba(255,255,255,0.05); height:4px; border-radius:4px; margin-top:0.5rem; overflow:hidden;">
                        <div id="quota-progress" style="width:0%; height:100%; background:var(--primary); transition:0.3s;"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="card-premium" style="margin-top:2.5rem; padding:3rem;">
                <h3 style="margin-bottom:2.5rem; font-size:1.3rem;">Operational Performance Log</h3>
                <div style="height: 350px;"><canvas id="mainChart"></canvas></div>
            </div>
        </section>

        <section id="blasts-tab" class="tab-content">
            <div class="card-premium" style="padding:0;">
                <div style="padding:2rem 3rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                    <h3>Campaign History & Progress</h3>
                    <button class="btn-premium" onclick="openModal('campaign-modal')">LAUNCH NEW BLAST</button>
                </div>
                <div style="padding:1rem 2.5rem;">
                    <table class="table-premium" id="campaignListTable">
                        <thead><tr><th>Subject Line</th><th>Status</th><th>Delivered</th><th>Fails</th><th>Open Rate</th><th>Date</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="audience-tab" class="tab-content">
            <div class="card-premium" style="padding:0;">
                <div style="padding:2rem 3rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                    <h3>Subscribers</h3>
                    <div style="display:flex; gap:1rem; align-items:center;">
                        <input type="text" class="form-control" style="width:250px; height:45px;" placeholder="Search targets...">
                        <input type="file" id="bulk-csv" accept=".csv" style="display:none" onchange="handleCSVImport(this)">
                        <button class="btn-premium" style="background:rgba(255,255,255,0.05); border:1px solid var(--border); height:45px; padding:0 1.25rem; font-size:0.75rem;" onclick="document.getElementById('bulk-csv').click()">
                            <ion-icon name="cloud-upload-outline" style="vertical-align:middle;"></ion-icon> BULK IMPORT
                        </button>
                        <button class="btn-premium" onclick="openModal('add-sub-modal')" style="height:45px; padding:0 1.5rem; font-size:0.8rem;"><ion-icon name="add-outline" style="vertical-align:middle;"></ion-icon> Add</button>
                    </div>
                </div>
                <div style="padding:1rem 2.5rem;">
                    <table class="table-premium">
                        <thead><tr><th>Name</th><th>Email Address</th><th>Status</th><th>Owner</th><th style="width:50px;"></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="platform-tab" class="tab-content">
            <div class="card-premium" style="padding:0;">
                <div style="padding:2rem 3rem; border-bottom:1px solid var(--border);">
                    <h3>Audit Trail & Security Logs</h3>
                </div>
                <div style="padding:1rem 2.5rem;">
                    <table class="table-premium">
                        <thead><tr><th>Timestamp</th><th>Action</th><th>Operation Details</th><th>Terminal / IP</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="team-tab" class="tab-content">
            <div class="card-premium" style="padding:0;">
                <div style="padding:2rem 3rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3>Organization Team</h3>
                        <p style="color:var(--text-dim); font-size:0.8rem;">Manage authorized members and branch accounts.</p>
                    </div>
                    <button class="btn-premium" onclick="openModal('onboard-modal')" style="height:45px; padding:0 1.5rem; font-size:0.8rem;"><ion-icon name="person-add-outline" style="vertical-align:middle;"></ion-icon> Add Member</button>
                </div>
                <div style="padding:1rem 2.5rem;">
                    <table class="table-premium" id="userListTable">
                        <thead><tr><th>Status</th><th>Member Name</th><th>Email Address</th><th>Role</th><th>Joined Date</th><th style="width:120px;">Actions</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>

        <?php if ($role === 'super_admin'): ?>
        <section id="deleted-tab" class="tab-content">
            <div class="card-premium" style="padding:0;">
                <div style="padding:2rem 3rem; border-bottom:1px solid var(--border);">
                    <h3>Deleted Accounts Archive</h3>
                    <p style="color:var(--text-dim); font-size:0.8rem;">Permanent record of removed members and authorization history.</p>
                </div>
                <div style="padding:1rem 2.5rem;">
                    <table class="table-premium" id="deletedUsersTable">
                        <thead><tr><th>Deleted At</th><th>Account Name</th><th>Email</th><th>Original Role</th><th>Deleted By</th><th>Reason</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- Modal for Member Onboarding -->
    <div id="onboard-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(15px); z-index:2000; align-items:center; justify-content:center; animation: fadeIn 0.3s;">
        <div class="card-premium" style="max-width:600px; width:95%; padding:3.5rem; animation: slideUpFade 0.5s;">
            <h2 style="font-size:2.2rem; font-weight:800; margin-bottom:0.5rem;">ADD <span style="color:var(--primary);">USER</span></h2>
            <p style="color:var(--text-dim); margin-bottom:3rem;">Register a new admin or user to the system.</p>
            
            <form id="onboard-form" onsubmit="onboardMember(event)">
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">FULL NAME</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter user's name..." required>
                </div>
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">EMAIL ADDRESS</label>
                    <input type="email" name="email" class="form-control" placeholder="user@domain.com" required>
                </div>
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">TEMPORARY PASSWORD</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <div style="display:flex; gap:1.5rem; justify-content:flex-end; align-items:center; margin-top:3rem;">
                    <button type="button" onclick="closeModal('onboard-modal')" style="background:transparent; border:none; color:#94a3b8; cursor:pointer; font-weight:700;">CANCEL</button>
                    <button type="submit" class="btn-premium">ADD USER</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal for Adding Subscriber -->
    <div id="add-sub-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(15px); z-index:2000; align-items:center; justify-content:center; animation: fadeIn 0.3s;">
        <div class="card-premium" style="max-width:500px; width:95%; padding:3.5rem; animation: slideUpFade 0.5s;">
            <h2 style="font-size:2.2rem; font-weight:800; margin-bottom:0.5rem;">ADD <span style="color:var(--primary);">SUBSCRIBER</span></h2>
            <p style="color:var(--text-dim); margin-bottom:3rem;">Manually add a contact to your audience list.</p>
            
            <form id="add-sub-form" onsubmit="addSubscriber(event)">
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">FULL NAME</label>
                    <input type="text" name="name" class="form-control" placeholder="Subscriber's Name" required>
                </div>
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">EMAIL ADDRESS</label>
                    <input type="email" name="email" class="form-control" placeholder="user@domain.com" required>
                </div>
                
                <div style="display:flex; gap:1.5rem; justify-content:flex-end; align-items:center; margin-top:3rem;">
                    <button type="button" onclick="closeModal('add-sub-modal')" style="background:transparent; border:none; color:#94a3b8; cursor:pointer; font-weight:700;">CANCEL</button>
                    <button type="submit" class="btn-premium">ADD CONTACT</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal for Campaign -->
    <div id="campaign-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(15px); z-index:2000; align-items:center; justify-content:center; animation: fadeIn 0.3s;">
        <div class="card-premium" style="max-width:750px; width:95%; animation: slideUpFade 0.5s; padding:3.5rem;">
            <h2 style="font-size:2.2rem; font-weight:800; letter-spacing:-1px; margin-bottom:0.5rem;">NEW <span style="color:var(--primary);">CAMPAIGN</span></h2>
            <p style="color:var(--text-dim); margin-bottom:3rem; font-size:0.95rem;">Configure and send your email blast.</p>
            
            <div style="display:grid; grid-template-columns:1fr 100px; gap:1.5rem; margin-bottom:1.5rem;">
                <div class="form-group">
                    <label style="color:#94a3b8; margin-bottom:1rem; display:block; font-size:0.8rem; font-weight:700; letter-spacing:1px;">PRIMARY SUBJECT (VERSION A)</label>
                    <input type="text" id="camp-subject" class="form-control" placeholder="Enter primary subject...">
                </div>
                <div class="form-group">
                    <label style="color:#94a3b8; margin-bottom:1rem; display:block; font-size:0.8rem; font-weight:700; letter-spacing:1px;">WT %</label>
                    <input type="number" id="camp-weight-a" class="form-control" value="100" min="0" max="100">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 100px; gap:1.5rem; margin-bottom:2rem;">
                <div class="form-group">
                    <label style="color:#94a3b8; margin-bottom:1rem; display:block; font-size:0.8rem; font-weight:700; letter-spacing:1px;">A/B SUBJECT (VERSION B - OPTIONAL)</label>
                    <input type="text" id="camp-subject-b" class="form-control" placeholder="Optional A/B variant...">
                </div>
                <div class="form-group">
                    <label style="color:#94a3b8; margin-bottom:1rem; display:block; font-size:0.8rem; font-weight:700; letter-spacing:1px;">WT %</label>
                    <input type="number" id="camp-weight-b" class="form-control" value="0" min="0" max="100">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom:2rem;">
                <label style="color:#94a3b8; margin-bottom:1rem; display:block; font-size:0.8rem; font-weight:700; letter-spacing:1px;">EMAIL TEMPLATE</label>
                <select id="camp-template" class="form-control" onchange="applyTemplate()">
                    <option value="">Pure Text Payload</option>
                    <option value="modern">Modern Visual System</option>
                    <option value="dark">Midnight Recon Theme</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:3rem;">
                <label style="color:#94a3b8; margin-bottom:1rem; display:block; font-size:0.8rem; font-weight:700; letter-spacing:1px;">EMAIL CONTENT</label>
                <textarea id="camp-content" class="form-control" rows="10" placeholder="Write your email content here..."></textarea>
            </div>
            
            <div style="display:flex; gap:2rem; justify-content:flex-end; align-items:center;">
                <button onclick="closeModal('campaign-modal')" style="background:transparent; border:none; color:#94a3b8; cursor:pointer; font-weight:700; letter-spacing:1px; font-size:0.8rem;">CANCEL</button>
                <button class="btn-premium" onclick="sendBlast(event)" style="height:55px; width:260px; justify-content:center;">
                    <ion-icon name="rocket-outline" style="font-size:1.4rem;"></ion-icon> SEND CAMPAIGN
                </button>
            </div>
        </div>
    </div>

    <!-- Modal for Admin Password Reset (Handshake) -->
    <div id="reset-pass-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(15px); z-index:2000; align-items:center; justify-content:center; animation: fadeIn 0.3s;">
        <div class="card-premium" style="max-width:500px; width:95%; padding:3.5rem; animation: slideUpFade 0.5s;">
            <h2 id="reset-modal-title" style="font-size:2rem; font-weight:800; margin-bottom:1rem;">CREDENTIAL <span style="color:var(--primary);">VAULT</span></h2>
            
            <div id="reset-stage-1">
                <p style="color:var(--text-dim); margin-bottom:2rem;">Set a new temporary password for this account.</p>
                <div class="form-group" style="margin-bottom:2.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">NEW PASSWORD</label>
                    <input type="text" id="admin-new-pass" class="form-control" placeholder="Minimum 6 characters...">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:1.5rem;">
                    <button onclick="closeModal('reset-pass-modal')" style="background:transparent; border:none; color:#94a3b8; cursor:pointer; font-weight:700;">CANCEL</button>
                    <button onclick="initiatePassChange()" class="btn-premium">GENERATE CODE</button>
                </div>
            </div>

            <div id="reset-stage-2" style="display:none;">
                <p style="color:var(--text-dim); margin-bottom:1rem;">Code sent to user's Gmail. Enter it below to finalize.</p>
                <div style="font-size:0.75rem; color:#ef4444; font-weight:700; background:rgba(239,68,68,0.1); padding:0.5rem 1rem; border-radius:8px; margin-bottom:2rem; border-left:3px solid #ef4444;">
                    ⏳ Code expires in 10 minutes.
                </div>
                <div class="form-group" style="margin-bottom:2.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">VERIFICATION CODE</label>
                    <input type="text" id="admin-otp-confirm" class="form-control" placeholder="000000" maxlength="6" style="text-align:center; font-size:1.8rem; letter-spacing:10px;">
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <a href="#" onclick="initiatePassChange()" style="color:var(--primary); font-size:0.8rem; font-weight:700; text-decoration:none;">RESEND CODE?</a>
                    <div style="display:flex; gap:1.5rem;">
                        <button onclick="closeModal('reset-pass-modal')" style="background:transparent; border:none; color:#94a3b8; cursor:pointer; font-weight:700;">CANCEL</button>
                        <button onclick="finalizePassChange()" class="btn-premium">FINALIZE RESET</button>
                    </div>
                </div>
            </div>
            <input type="hidden" id="reset-target-id">
        </div>
    </div>

    <script>
        window.csrfToken = '<?php echo Security::generateCSRF(); ?>';
        window.role = '<?php echo $role; ?>';
    </script>
    <script src="app.js"></script>
</body>
</html>
