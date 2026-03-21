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
    <title>BlastForge | Tactical Command Dashboard</title>
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
            <a href="#" class="nav-link active" onclick="showTab('overview', event)"><ion-icon name="speedometer-outline"></ion-icon> Tactical Overview</a>
            <a href="#" class="nav-link" onclick="showTab('audience', event)"><ion-icon name="people-outline"></ion-icon> Target List</a>
            <a href="#" class="nav-link" onclick="showTab('blasts', event)"><ion-icon name="paper-plane-outline"></ion-icon> Email Operations</a>
            <?php if ($role === 'super_admin'): ?>
                <a href="#" class="nav-link" onclick="showTab('platform', event)"><ion-icon name="shield-outline"></ion-icon> Audit History</a>
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
                    <ion-icon name="person-add-outline"></ion-icon> ONBOARD MEMBER
                </button>
                <?php endif; ?>
                <button class="btn-premium" onclick="openModal('campaign-modal')">
                    <ion-icon name="rocket-outline"></ion-icon> INITIATE BLAST
                </button>
            </div>
        </header>

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
                    <span class="stat-label">Tactical Contacts</span>
                    <span class="stat-value" id="stat-subs">--</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Active Commands</span>
                    <span class="stat-value" id="stat-camps">--</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Total Deliveries</span>
                    <span class="stat-value" id="stat-emails">--</span>
                </div>
            </div>

            <div class="card-premium" style="margin-top:2.5rem; padding:3rem;">
                <h3 style="margin-bottom:2.5rem; font-size:1.3rem;">Operational Performance Log</h3>
                <div style="height: 350px;"><canvas id="mainChart"></canvas></div>
            </div>
        </section>

        <section id="audience-tab" class="tab-content">
            <div class="card-premium" style="padding:0;">
                <div style="padding:2rem 3rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                    <h3>Target Audience</h3>
                    <input type="text" class="form-control" style="max-width:300px; height:45px;" placeholder="Search targets...">
                </div>
                <div style="padding:1rem 2.5rem;">
                    <table class="table-premium">
                        <thead><tr><th>Codename / Name</th><th>Email Vector</th><th>Status</th></tr></thead>
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
    </main>

    <!-- Modal for Member Onboarding -->
    <div id="onboard-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(15px); z-index:2000; align-items:center; justify-content:center; animation: fadeIn 0.3s;">
        <div class="card-premium" style="max-width:600px; width:95%; padding:3.5rem; animation: slideUpFade 0.5s;">
            <h2 style="font-size:2.2rem; font-weight:800; margin-bottom:0.5rem;">NEW <span style="color:var(--primary);">ONBOARDING</span></h2>
            <p style="color:var(--text-dim); margin-bottom:3rem;">Register a new branch operator or system user.</p>
            
            <form id="onboard-form" onsubmit="onboardMember(event)">
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">FULL CODENAME / NAME</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter operator name..." required>
                </div>
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">COMMAND EMAIL</label>
                    <input type="email" name="email" class="form-control" placeholder="user@domain.com" required>
                </div>
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">TEMPORARY PASSWORD</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <div style="display:flex; gap:1.5rem; justify-content:flex-end; align-items:center; margin-top:3rem;">
                    <button type="button" onclick="closeModal('onboard-modal')" style="background:transparent; border:none; color:#94a3b8; cursor:pointer; font-weight:700;">ABORT</button>
                    <button type="submit" class="btn-premium">SYNC & ENROLL</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal for Campaign -->
    <div id="campaign-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(15px); z-index:2000; align-items:center; justify-content:center; animation: fadeIn 0.3s;">
        <div class="card-premium" style="max-width:750px; width:95%; animation: slideUpFade 0.5s; padding:3.5rem;">
            <h2 style="font-size:2.2rem; font-weight:800; letter-spacing:-1px; margin-bottom:0.5rem;">TACTICAL OPERATION <span style="color:var(--primary);">BLAST</span></h2>
            <p style="color:var(--text-dim); margin-bottom:3rem; font-size:0.95rem;">Configure your payload for mass deployment.</p>
            
            <div class="form-group" style="margin-bottom:2rem;">
                <label style="color:#94a3b8; margin-bottom:1rem; display:block; font-size:0.8rem; font-weight:700; letter-spacing:1px;">TARGET SUBJECT LINE</label>
                <input type="text" id="camp-subject" class="form-control" placeholder="Enter high-conversion subject...">
            </div>
            
            <div class="form-group" style="margin-bottom:2rem;">
                <label style="color:#94a3b8; margin-bottom:1rem; display:block; font-size:0.8rem; font-weight:700; letter-spacing:1px;">PAYLOAD DESIGN (TEMPLATE)</label>
                <select id="camp-template" class="form-control" onchange="applyTemplate()">
                    <option value="">Pure Text Payload</option>
                    <option value="modern">Modern Visual System</option>
                    <option value="dark">Midnight Recon Theme</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:3rem;">
                <label style="color:#94a3b8; margin-bottom:1rem; display:block; font-size:0.8rem; font-weight:700; letter-spacing:1px;">MESSAGE MATRIX (CONTENT)</label>
                <textarea id="camp-content" class="form-control" rows="10" placeholder="Deploy your intelligence here..."></textarea>
            </div>
            
            <div style="display:flex; gap:2rem; justify-content:flex-end; align-items:center;">
                <button onclick="closeModal('campaign-modal')" style="background:transparent; border:none; color:#94a3b8; cursor:pointer; font-weight:700; letter-spacing:1px; font-size:0.8rem;">ABORT MISSION</button>
                <button class="btn-premium" onclick="sendBlast(event)" style="height:55px; width:260px; justify-content:center;">
                    <ion-icon name="rocket-outline" style="font-size:1.4rem;"></ion-icon> INITIATE DEPLOYMENT
                </button>
            </div>
        </div>
    </div>

    <script>
        window.csrfToken = '<?php echo Security::generateCSRF(); ?>';
        window.role = '<?php echo $role; ?>';
    </script>
    <script src="app.js"></script>
</body>
</html>
