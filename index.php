<?php 
require_once 'config.php'; 
require_once 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; } 

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$userName = $_SESSION['name'] ?? 'User';

$masterCode = '';
if ($role !== 'user') {
    $res = $conn->query("SELECT referral_code FROM users WHERE id = $userId");
    $u = $res->fetch_assoc();
    if (empty($u['referral_code'])) {
        $prefix = ($role === 'super_admin') ? 'ADM-' : (($role === 'admin') ? 'BRN-' : 'STF-');
        $masterCode = $prefix . strtoupper(substr(md5(uniqid()), 0, 6));
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="mesh-bg"></div>

    <aside class="sidebar">
        <div style="font-size:2rem; font-weight:800; margin-bottom:4rem; padding-left:1rem; letter-spacing:-1px;">
            <span style="color:var(--primary);">Blast</span>Forge
        </div>
        <nav>
            <a href="#" class="nav-link active" onclick="showTab('overview', event)"><ion-icon name="speedometer-outline"></ion-icon> <?php echo ($role === 'user') ? 'My Portal' : 'Overview'; ?></a>
            
            <?php if ($role !== 'user'): ?>
                <a href="#" class="nav-link" onclick="showTab('audience', event)"><ion-icon name="people-outline"></ion-icon> Subscribers</a>
                <a href="#" class="nav-link" onclick="showTab('blasts', event)"><ion-icon name="paper-plane-outline"></ion-icon> Campaigns</a>
                <a href="#" class="nav-link" onclick="showTab('team', event)"><ion-icon name="shield-checkmark-outline"></ion-icon> Team Management</a>
            <?php endif; ?>

            <?php if ($role === 'super_admin'): ?>
                <a href="#" class="nav-link" onclick="showTab('platform', event)"><ion-icon name="shield-outline"></ion-icon> System Audit Log</a>
                <a href="#" class="nav-link" onclick="showTab('deleted', event)"><ion-icon name="trash-outline"></ion-icon> Deleted History</a>
            <?php endif; ?>

            <?php if ($role === 'user'): ?>
                <a href="#" class="nav-link" onclick="showTab('audience', event)"><ion-icon name="people-outline"></ion-icon> Target Leads</a>
                <a href="#" class="nav-link" onclick="showTab('unopened', event)"><ion-icon name="mail-unread-outline"></ion-icon> Pending Opens <span id="nav-msg-badge" style="background:#ef4444; color:#fff; border-radius:10px; padding:2px 6px; font-size:0.6rem; margin-left:5px; display:none;">New</span></a>
                <a href="#" class="nav-link" onclick="showTab('personal-log', event)"><ion-icon name="list-outline"></ion-icon> Activity Log</a>
            <?php endif; ?>

            <a href="profile.php" class="nav-link" style="margin-top:2rem; border-top:1px solid var(--border); padding-top:2rem;"><ion-icon name="settings-sharp"></ion-icon> <?php echo ($role === 'user') ? 'My Profile' : 'Portal Settings'; ?></a>
            <a href="auth_api.php?action=logout" class="nav-link" style="color:var(--danger); opacity:0.8;"><ion-icon name="log-out-outline"></ion-icon> Deauthorize</a>
        </nav>
    </aside>

    <main style="margin-left: var(--sidebar-w); padding: 2.5rem 3rem;">
        <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2.5rem; animation: slideUpFade 0.7s forwards;">
            <div style="display:flex; align-items:center; gap:1.5rem;">
                <button class="menu-toggle" onclick="toggleSidebar()"><ion-icon name="menu-outline"></ion-icon></button>
                <div>
                    <h1 style="font-size:2.2rem; font-weight:800; letter-spacing:-1.2px; margin-bottom:0.25rem;"><?php echo date('H') < 12 ? 'Good Morning' : (date('H') < 18 ? 'Good Afternoon' : 'Good Evening'); ?>, <?php echo explode(' ', $userName)[0]; ?></h1>
                    <p style="color:#94a3b8; font-size:0.95rem; font-weight:500;">Commanding the platform as <span style="color:var(--primary-bright); font-weight:700;"><?php echo strtoupper($role); ?></span></p>
                </div>
            </div>
            <div style="display:flex; gap:1.5rem; align-items:center;">
                    <?php if ($role !== 'user'): ?>
                    <button class="btn-premium" onclick="openModal('campaign-modal')">
                        <ion-icon name="rocket-outline"></ion-icon> NEW CAMPAIGN
                    </button>
                    <div id="worker-status-badge" style="display:inline-flex; align-items:center; gap:0.75rem; background:rgba(0,0,0,0.3); border:1px solid var(--border); padding:0.65rem 1.25rem; border-radius:30px; font-size:0.75rem; font-weight:800; letter-spacing:0.5px; cursor:help; margin-left:1rem; box-shadow:0 0 20px rgba(0,0,0,0.25);" title="BlastForge Engine Mission Control">
                         <div class="pulse-dot" id="worker-status-dot" style="width:10px; height:10px; border-radius:50%; background:#888;"></div>
                         <span style="opacity:0.6; font-size:0.6rem;">ENGINE</span>
                         <span id="worker-status-text">SYNCING...</span>
                         <span id="queue-status-text" style="background:rgba(255,255,255,0.05); padding:2px 8px; border-radius:10px; font-size:0.6rem; margin-left:0.5rem; color:var(--primary-bright);">0 PENDING</span>
                    </div>
                    <?php endif; ?>
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

        <?php if ($role !== 'user'): 
            $startLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/login.php?ref=" . $masterCode;
        ?>
            <div class="card-premium stagger-item" style="margin-bottom:1.75rem; display:flex; align-items:center; justify-content:space-between; border-left:4px solid var(--primary); padding:1.5rem 2.5rem;">
                <div>
                    <h4 style="color:var(--primary-bright); margin-bottom:0.15rem; font-size:0.7rem; letter-spacing:1px; font-weight:800;">MASTER REFERRAL KEY</h4>
                    <p style="color:#94a3b8; font-size:0.75rem; font-weight:500;">Invite <?php echo ($role==='super_admin') ? 'Admins (Companies)' : (($role==='admin') ? 'Branch Staff' : 'Portal Users'); ?>.</p>
                </div>
                <div style="display:flex; gap:1rem; align-items:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#fff; letter-spacing:4px; font-family:monospace; background:rgba(0,0,0,0.3); padding:0.75rem 1.5rem; border-radius:12px; border:1px solid var(--border);">
                        <?php echo $masterCode; ?>
                    </div>
                    <button class="btn-premium" onclick="navigator.clipboard.writeText('<?php echo $startLink; ?>').then(() => showToast('Link to start copied to clipboard!'))" style="height:55px; padding:0 1.25rem;">
                        <ion-icon name="link-outline"></ion-icon> LINK TO START
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <section id="overview-tab" class="tab-content" style="display:block;">
            <div class="grid-command">
                <!-- LEFT COLUMN: Resource & Stats -->
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <?php if ($role === 'super_admin'): ?>
                        <div class="hero-stat stagger-item" style="background:linear-gradient(135deg, rgba(139,92,246,0.1), rgba(167,139,250,0.05)); border-color:rgba(139,92,246,0.3);">
                            <span class="stat-label" style="color:var(--primary-bright); font-weight:900;"><ion-icon name="globe-outline"></ion-icon> SYSTEM OVERLORD COMMAND</span>
                            <div style="margin: 1.5rem 0;">
                                <span class="stat-value" id="stat-users" style="font-size:3rem; font-weight:900;">--</span>
                                <small style="display:block; opacity:0.6; font-size:0.75rem; margin-top:0.25rem;">TOTAL PLATFORM ENTITIES ENABLED</small>
                            </div>
                        </div>
                        <div class="stat-card stagger-item"><span class="stat-label">System-Wide Managed Leads</span><span class="stat-value" id="stat-subs">--</span></div>
                        <div class="stat-card stagger-item"><span class="stat-label">Active Global Campaigns</span><span class="stat-value" id="stat-camps">--</span></div>

                    <?php elseif ($role === 'admin' || $role === 'staff'): ?>
                        <div class="hero-stat stagger-item">
                            <span class="stat-label" style="color:var(--primary-bright); font-weight:900;"><ion-icon name="flash-outline"></ion-icon> BRANCH RESOURCE FUEL</span>
                            <div style="margin: 1.5rem 0;">
                                <span class="stat-value" id="stat-quota" style="font-size:3rem; font-weight:900;">--</span>
                                <small style="display:block; opacity:0.6; font-size:0.7rem; margin-top:0.25rem;">TOTAL EMAILS SENT (MTD)</small>
                            </div>
                            <div style="background:rgba(255,255,255,0.08); height:8px; border-radius:10px; overflow:hidden; border:1px solid rgba(255,255,255,0.05);">
                                <div id="quota-progress" style="width:0%; height:100%; background:linear-gradient(90deg, var(--primary), var(--primary-bright)); box-shadow: 0 0 15px var(--primary-bright);"></div>
                            </div>
                        </div>
                        <div class="stat-card stagger-item"><span class="stat-label">Branch Leads Sourced</span><span class="stat-value" id="stat-subs">--</span></div>

                    <?php else: // 🚀 ELITE SOURCING MISSION CONTROL (MEMBER) ?>
                        <div class="card-premium stagger-item" style="padding:4rem 2rem; text-align:center; display:flex; flex-direction:column; align-items:center; border-bottom: 3px solid #7c3aed;">
                            <div style="background:linear-gradient(135deg, #4f46e5, #ec4899); width:80px; height:80px; border-radius:18px; display:flex; align-items:center; justify-content:center; margin-bottom:2rem; box-shadow:0 15px 35px rgba(79, 70, 229, 0.4);">
                                <ion-icon name="shield-checkmark" style="font-size:3.5rem; color:#fff;"></ion-icon>
                            </div>
                            <h2 style="font-size:1.8rem; font-weight:900; letter-spacing:-0.5px; margin-bottom:1rem;">SOURCING ENGINE ACTIVE</h2>
                            <p style="color:var(--text-dim); line-height:1.7; font-size:0.95rem; max-width:400px; margin-bottom:3rem;">
                                You are a verified <span style="color:#fff; font-weight:700;">Target Sourcer</span>. Import bulk emails and manually acquire targets to support your team's campaigns. Review unopened/bounced stats dynamically.
                            </p>
                            
                            <button onclick="showTab('audience', event)" style="background:linear-gradient(90deg, #7c3aed, #db2777); color:#fff; border:none; padding:1.2rem 2.8rem; border-radius:18px; font-weight:800; font-size:0.9rem; letter-spacing:1px; cursor:pointer; display:flex; align-items:center; gap:0.75rem; box-shadow: 0 10px 25px rgba(219, 39, 119, 0.3); transition:0.3s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                                <ion-icon name="people"></ion-icon> GO TO DATABASE
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RIGHT COLUMN: Intelligence Display -->
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <?php if ($role !== 'user'): ?>
                        <div class="card-premium stagger-item" style="padding:1.5rem 2rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                                <h3 style="font-size:0.95rem; font-weight:800; letter-spacing:0.5px;">LIVE PERFORMANCE LOG</h3>
                                <div style="display:flex; gap:1rem;">
                                    <div class="stat-box" style="text-align:right;">
                                        <div style="font-size:0.65rem; color:var(--text-dim); font-weight:800;">OPENS</div>
                                        <div id="stat-opens" style="font-weight:900; color:#34d399; font-size:1.1rem;">--</div>
                                    </div>
                                    <div class="stat-box" style="text-align:right;">
                                        <div style="font-size:0.65rem; color:var(--text-dim); font-weight:800;">CLICKS</div>
                                        <div id="stat-clicks" style="font-weight:900; color:var(--primary-bright); font-size:1.1rem;">--</div>
                                    </div>
                                </div>
                            </div>
                            <div style="height: 310px;"><canvas id="mainChart"></canvas></div>
                        </div>
                    <?php else: // USER (MEMBER) RIGHT COLUMN ?>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                            <div class="stat-card">
                                <span class="stat-label">Total Targets Captured</span>
                                <span class="stat-value" id="stat-subs">--</span>
                            </div>
                            <div class="stat-card" style="border-right:3px solid #f87171;">
                                <span class="stat-label">Pending Checks</span>
                                <span class="stat-value" id="stat-unread-msg" style="color:#f87171;">--</span>
                            </div>
                        </div>
                        <div class="card-premium stagger-item" style="padding:1.5rem 2rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                                <h3 style="font-size:0.9rem; font-weight:800; letter-spacing:0.5px;">SOURCING VELOCITY</h3>
                                <div class="stat-box" style="text-align:right;">
                                    <div style="font-size:0.65rem; color:var(--text-dim); font-weight:800;">TOTAL ENGAGEMENT</div>
                                    <div id="stat-total-msg" style="font-weight:900; color:#34d399; font-size:1.1rem;">--</div>
                                </div>
                            </div>
                            <div style="height: 250px;"><canvas id="mainChart"></canvas></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($role === 'super_admin'): ?>
            <!-- 🚨 System Monitoring Alerts (Critical Failures & Successes) -->
            <div id="system-alerts-container" class="card-premium stagger-item" style="margin-top:1.75rem; border:1px solid rgba(239, 68, 68, 0.2); background:rgba(239,68,68,0.02);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; padding: 1.25rem 2.5rem 0;">
                     <h3 style="font-size:0.9rem; font-weight:800; letter-spacing:1px; color:#f87171;"><ion-icon name="warning-outline" style="vertical-align:middle;"></ion-icon> SYSTEM ALERTS & MONITORING</h3>
                     <span style="font-size:0.6rem; color:#f87171; background:rgba(239, 68, 68, 0.1); padding:4px 10px; border-radius:10px;">AUTO-RECOVERY ACTIVE</span>
                </div>
                <div id="system-alerts-list" style="padding: 0 2.5rem 1.25rem;">
                    <div style="padding:1rem; color:var(--text-dim); text-align:center; font-size:0.75rem;">Scanning system health...</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($role === 'super_admin' || $role === 'admin' || $role === 'staff'): ?>
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.75rem; margin-top:1.75rem;">
                <!-- 🌡️ Engagement Heatmap -->
                <div class="card-premium stagger-item" style="padding:1.5rem 2.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                         <h3 style="font-size:0.9rem; font-weight:800; letter-spacing:0.5px;">ENGAGEMENT HEATMAP (TIME OF DAY)</h3>
                         <span style="font-size:0.6rem; color:var(--text-dim); background:rgba(255,255,255,0.05); padding:4px 10px; border-radius:10px;">PEAK TIME ANALYSIS</span>
                    </div>
                    <div style="height: 250px;"><canvas id="heatmapChart"></canvas></div>
                </div>

                <!-- 🏆 Performance Leaderboard -->
                <div class="card-premium stagger-item" style="padding:1.5rem 2rem;">
                    <h3 style="font-size:0.9rem; font-weight:800; letter-spacing:0.5px; margin-bottom:1.5rem;">BRANCH LEADERBOARD</h3>
                    <div id="leaderboard-list" style="display:flex; flex-direction:column; gap:0.5rem;">
                        <!-- Injected by elite_sync.js -->
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($role === 'user'): ?>
            <div style="display:grid; grid-template-columns: 1fr; gap:1.75rem; margin-top:1.75rem;">
                <!-- 🧬 Predictive Insights -->
                <div class="card-premium stagger-item" style="padding:1.5rem 2.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                         <h3 style="font-size:0.9rem; font-weight:800; letter-spacing:0.5px;">AI-POWERED LEAD INSIGHTS</h3>
                         <span style="font-size:0.6rem; color:#8b5cf6; background:rgba(139, 92, 246, 0.1); padding:4px 10px; border-radius:10px;">PREDICTIVE CONVERSION</span>
                    </div>
                    <div id="insights-list" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:1rem;">
                        <!-- Injected by elite_sync.js -->
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($role !== 'user'): ?>
            <section id="blasts-tab" class="tab-content">
                <div class="card-premium" style="padding:0;">
                    <div style="padding:2rem 3rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                        <h3>Campaign History & Progress</h3>
                        <button class="btn-premium" onclick="openModal('campaign-modal')">LAUNCH NEW BLAST</button>
                    </div>
                    <div style="padding:1rem 2.5rem;" class="table-responsive">
                        <table class="table-premium" id="campaignListTable">
                            <thead><tr><th>Subject Line</th><th>Status</th><th>Delivered</th><th>Fails</th><th>Open Rate</th><th>Date</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; // End of Blasts Tab ?>

        <section id="audience-tab" class="tab-content">
            <div class="card-premium stagger-item" style="padding:0;">
                <div style="padding:1.5rem 2.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3 style="font-size:1rem; font-weight:800; letter-spacing:0.5px;">Lead Intelligence Database</h3>
                        <p style="color:var(--text-dim); font-size:0.75rem;"><?php echo ($role === 'user') ? 'Intelligence Monitor: Unopened leads from your direct Admin/Staff branch.' : 'Aggregated leads from your entire organization.'; ?></p>
                    </div>
                    <div style="display:flex; gap:0.75rem; align-items:center;">
                        <input type="text" class="form-control" style="width:200px; height:40px; font-size:0.75rem;" placeholder="Filter leads...">
                        
                        <?php if ($role === 'staff' || $role === 'admin' || $role === 'super_admin'): ?>
                        <input type="file" id="bulk-csv" accept=".csv" style="display:none" onchange="handleCSVImport(this)">
                        <button class="btn-premium" style="background:rgba(255,255,255,0.03); border:1px solid var(--border); height:40px; padding:0 1rem; font-size:0.7rem;" onclick="document.getElementById('bulk-csv').click()">
                            IMPORT CSV
                        </button>
                        <button class="btn-premium" onclick="openModal('add-sub-modal')" style="height:40px; padding:0 1.25rem; font-size:0.7rem;">+ ADD LEAD</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="padding:0.75rem 2rem;" class="table-responsive">
                    <table class="table-premium">
                        <thead>
                            <?php if($role === 'user'): ?>
                                <tr><th>Status</th><th>Target Entity</th><th>Captured At</th><th style="text-align:right;">Operational State</th></tr>
                            <?php else: ?>
                                <tr><th>Identity</th><th>Email Vault</th><th>Status</th><th>Source</th><th style="width:40px;"></th></tr>
                            <?php endif; ?>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>

        <?php if ($role !== 'user'): ?>
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
                <div class="card-premium" style="padding:0; background:transparent; border:none; box-shadow:none;">
                    <div style="padding:1.5rem 0rem; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.05); margin-bottom:2rem;">
                        <div id="team-breadcrumb" style="display:flex; align-items:center; gap:0.5rem; color:#fff; font-size:0.9rem; font-weight:700;">
                            <span onclick="loadTeamMembers()" style="cursor:pointer; opacity:0.6; transition:0.3s;" class="bh-item hover-glow">All Organization</span>
                        </div>
                        <button class="btn-premium" onclick="openModal('onboard-modal')" style="height:40px; padding:0 1.25rem; font-size:0.75rem;"><ion-icon name="person-add-outline" style="vertical-align:middle;"></ion-icon> ADD NEW MEMBER</button>
                    </div>

                    <!-- TREE-STYLE HIERARCHY GRID -->
                    <div id="teamGrid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:1.5rem;">
                        <!-- Cards will be injected by JS -->
                    </div>

                    <div style="margin-top:3rem;">


                    <div class="card-premium stagger-item" style="padding:0; margin-top:1.5rem; border-bottom:1px solid rgba(59, 130, 246, 0.2);">
                        <div style="padding:1.5rem 2.5rem; border-bottom:1px solid var(--border); background:rgba(59, 130, 246, 0.03);">
                            <h3 style="color:#60a5fa; font-size:0.85rem; font-weight:800; letter-spacing:1px;"><ion-icon name="person-add-outline"></ion-icon> PENDING ACTIVATIONS</h3>
                            <p style="color:var(--text-dim); font-size:0.7rem;">Verified members waiting for your final account approval.</p>
                        </div>
                        <div style="padding:1rem 2rem;">
                            <table class="table-premium" id="pendingRegTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Date</th>
                                        <th style="width:160px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-premium stagger-item" style="padding:0; margin-top:1rem; border-bottom:1px solid rgba(52, 211, 153, 0.2);">
                        <div style="padding:1.5rem 2.5rem; border-bottom:1px solid var(--border); background:rgba(52, 211, 153, 0.03);">
                            <h3 style="color:#34d399; font-size:0.85rem; font-weight:800; letter-spacing:1px;"><ion-icon name="time-outline"></ion-icon> PENDING IMPORT REQUESTS</h3>
                            <p style="color:var(--text-dim); font-size:0.7rem;">Staff and member CSV imports requiring your verification.</p>
                        </div>
                        <div style="padding:1rem 2rem;">
                            <table class="table-premium" id="pendingImportsTable">
                                <thead>
                                    <tr>
                                        <th>Requester</th>
                                        <th>File Metadata</th>
                                        <th>Date Submitted</th>
                                        <th>Status</th>
                                        <th style="width:160px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-premium stagger-item" style="padding:0; margin-top:1rem; border-bottom:1px solid rgba(239, 68, 68, 0.2);">
                        <div style="padding:1.5rem 2.5rem; border-bottom:1px solid var(--border); background:rgba(239, 68, 68, 0.03);">
                            <h3 style="color:#f87171; font-size:0.85rem; font-weight:800; letter-spacing:1px;"><ion-icon name="warning-outline"></ion-icon> GMAIL ATTEMPTS & BANS</h3>
                            <p style="color:var(--text-dim); font-size:0.7rem;">Security monitor for unauthorized or failed registration attempts.</p>
                        </div>
                        <div style="padding:1rem 2rem;">
                            <table class="table-premium" id="attemptsTable">
                                <thead>
                                    <tr>
                                        <th>Email Entity</th>
                                        <th>Auth Trace</th>
                                        <th>Token Trace</th>
                                        <th>Burst</th>
                                        <th>Security</th>
                                        <th style="width:80px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($role === 'super_admin'): ?>
            <section id="deleted-tab" class="tab-content">
                <div class="card-premium" style="padding:0;">
                    <div style="padding:2rem 3rem; border-bottom:1px solid var(--border);">
                        <h3>Deleted Accounts Archive</h3>
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

        <?php else: // IF ROLE === 'user', they only see these extra tracking tabs ?>
            <section id="unopened-tab" class="tab-content">
                <div class="card-premium stagger-item" style="padding:0;">
                    <div style="padding:1.5rem 2.5rem; border-bottom:1px solid var(--border);">
                        <h3 style="font-size:1rem; font-weight:800; letter-spacing:0.5px;">Pending Target Checks (Unopened)</h3>
                        <p style="color:var(--text-dim); font-size:0.75rem;">Emails sent to your leads that remain unverified by tracking pixels.</p>
                    </div>
                    <div style="padding:0.75rem 2rem;">
                        <table class="table-premium" id="messagesTable">
                            <thead><tr><th>Status</th><th>Target Entity</th><th>Captured At</th><th style="width:80px;">Operational State</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="personal-log-tab" class="tab-content">
                <div class="card-premium stagger-item" style="padding:0;">
                    <div style="padding:1.5rem 2.5rem; border-bottom:1px solid var(--border);">
                        <h3 style="font-size:1rem; font-weight:800; letter-spacing:0.5px;">Activity Command Log</h3>
                        <p style="color:var(--text-dim); font-size:0.75rem;">Comprehensive history of your system commands and session access.</p>
                    </div>
                    <div style="padding:0.75rem 2rem;">
                        <table class="table-premium" id="personalLogTable">
                            <thead><tr><th>Timestamp</th><th>Action</th><th>Notes</th><th>Transmission IP</th></tr></thead>
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
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">INITIAL PASSWORD</label>
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

    <!-- Modal for Manual Add Subscriber -->
    <div id="add-sub-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(15px); z-index:2000; align-items:center; justify-content:center; animation: fadeIn 0.3s;">
        <div class="card-premium" style="max-width:500px; width:95%; padding:3.5rem; animation: slideUpFade 0.5s;">
            <h2 style="font-size:2rem; font-weight:800; margin-bottom:1rem;">NEW <span style="color:var(--primary);">TARGET</span></h2>
            <p style="color:var(--text-dim); margin-bottom:2.5rem;">Add a single contact manually to your database.</p>
            <form onsubmit="addSubscriber(event)">
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">CONTACT NAME</label>
                    <input type="text" name="name" class="form-control" placeholder="Full name..." required>
                </div>
                <div class="form-group" style="margin-bottom:2.5rem;">
                    <label style="color:#94a3b8; margin-bottom:0.8rem; display:block; font-size:0.8rem; font-weight:700;">GMAIL ADDRESS</label>
                    <input type="email" name="email" class="form-control" placeholder="example@gmail.com" required>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:1.5rem;">
                    <button type="button" onclick="closeModal('add-sub-modal')" style="background:transparent; border:none; color:#94a3b8; cursor:pointer; font-weight:700;">CANCEL</button>
                    <button type="submit" class="btn-premium">SAVE CONTACT</button>
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

    <!-- Modal for Detailed User Profile Inspector -->
    <div id="profile-inspect-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); backdrop-filter:blur(20px); z-index:3000; align-items:center; justify-content:center;">
        <div class="card-premium" style="max-width:850px; width:95%; padding:0; overflow:hidden; border-radius:32px;">
            <div id="pi-header" style="height:120px; background:var(--primary); padding:2rem 3.5rem; display:flex; justify-content:space-between; align-items:flex-end; position:relative;">
                <button onclick="closeModal('profile-inspect-modal')" style="position:absolute; top:1.5rem; right:1.5rem; background:rgba(0,0,0,0.3); border:none; color:#fff; width:35px; height:35px; border-radius:50%; cursor:pointer;"><ion-icon name="close-outline"></ion-icon></button>
                <div style="display:flex; gap:1.5rem; align-items:center; transform:translateY(40px);">
                    <div id="pi-avatar" style="width:100px; height:100px; border-radius:50%; background:#fff; border:5px solid var(--card-bg); display:flex; align-items:center; justify-content:center; font-size:2.5rem; font-weight:900; color:var(--primary);"></div>
                    <div style="color:#fff; text-shadow:0 10px 20px rgba(0,0,0,0.5);">
                        <h2 id="pi-name" style="font-size:1.8rem; font-weight:900; margin-bottom:0.25rem;">-</h2>
                        <span id="pi-role-badge" style="background:#000; padding:4px 12px; border-radius:30px; font-size:0.6rem; font-weight:800; letter-spacing:1px;">-</span>
                    </div>
                </div>
                <!-- Admin Edit Button Container -->
                <div style="transform:translateY(20px);" id="pi-admin-edit">
                    <button class="btn-premium" onclick="openEditModalFromPi()" style="padding:0.75rem 1.5rem; font-size:0.8rem; box-shadow:0 15px 35px rgba(0,0,0,0.4);"><ion-icon name="create-outline"></ion-icon> Manage / Edit Settings</button>
                    <input type="hidden" id="pi-user-id-raw">
                </div>
            </div>
            
            <div style="padding:4rem 3.5rem 3.5rem 3.5rem; display:grid; grid-template-columns:1fr 280px; gap:3rem;">
                <div>
                   <h4 style="color:var(--primary); font-size:0.7rem; font-weight:900; letter-spacing:1px; margin-bottom:1rem;">PROFESSIONAL SIGNATURE / BIO</h4>
                   <p id="pi-bio" style="font-size:0.95rem; line-height:1.6; color:#94a3b8; margin-bottom:2rem;">-</p>
                   
                   <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-top:2rem;">
                        <div>
                            <h4 style="color:var(--text-dim); font-size:0.6rem; font-weight:900; letter-spacing:1px;">PERSONAL GMAIL</h4>
                            <div id="pi-gmail" style="color:#fff; font-size:0.9rem; margin-top:0.4rem;">-</div>
                        </div>
                        <div>
                            <h4 style="color:var(--text-dim); font-size:0.6rem; font-weight:900; letter-spacing:1px;">CONTACT PHONE</h4>
                            <div id="pi-phone" style="color:#fff; font-size:0.9rem; margin-top:0.4rem;">-</div>
                        </div>
                   </div>

                   <div style="margin-top:2rem;">
                        <h4 style="color:var(--text-dim); font-size:0.6rem; font-weight:900; letter-spacing:1px;">SOCIAL FOOTPRINT</h4>
                        <div style="display:flex; gap:1rem; margin-top:0.75rem;">
                            <a id="pi-fb" href="#" target="_blank" style="color:#fff; text-decoration:none; background:rgba(255,255,255,0.05); padding:0.6rem 1rem; border-radius:12px; font-size:0.8rem; display:flex; align-items:center; gap:0.5rem;"><ion-icon name="logo-facebook" style="color:#1877f2;"></ion-icon> Facebook</a>
                            <a id="pi-ig" href="#" target="_blank" style="color:#fff; text-decoration:none; background:rgba(255,255,255,0.05); padding:0.6rem 1rem; border-radius:12px; font-size:0.8rem; display:flex; align-items:center; gap:0.5rem;"><ion-icon name="logo-instagram" style="color:#e4405f;"></ion-icon> Instagram</a>
                        </div>
                   </div>
                </div>

                <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); border-radius:24px; padding:1.5rem;">
                    <h4 style="text-align:center; font-size:0.7rem; font-weight:900; margin-bottom:1.5rem; opacity:0.6;">NETWORK METRICS</h4>
                    <div style="margin-bottom:1.5rem; text-align:center;">
                        <div id="pi-subs" style="color:#fff; font-size:1.8rem; font-weight:900;">0</div>
                        <div style="font-size:0.6rem; color:var(--text-dim); font-weight:800;">LEADS CAPTURED</div>
                    </div>
                    <div style="margin-bottom:1.5rem; text-align:center;">
                        <div id="pi-camps" style="color:#fff; font-size:1.8rem; font-weight:900;">0</div>
                        <div style="font-size:0.6rem; color:var(--text-dim); font-weight:800;">INDEPENDENT CAMPAIGNS</div>
                    </div>
                    <div style="text-align:center;">
                        <div id="pi-status" style="font-weight:900; font-size:0.7rem;">-</div>
                        <div style="font-size:0.6rem; color:var(--text-dim); font-weight:800; margin-top:0.4rem;">ACCOUNT STATUS</div>
                    </div>
                </div>
            </div>

            <!-- Full Access / Actions Log Container -->
            <div id="pi-logs-wrapper" style="padding:0 3.5rem 3.5rem 3.5rem; border-top:1px solid rgba(255,255,255,0.05);">
                <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:2rem; margin-bottom:1rem;">
                    <h4 style="color:var(--primary); font-size:0.8rem; font-weight:900; letter-spacing:1px;"><ion-icon name="pulse-outline"></ion-icon> USER ACTIVITY & FULL ACCESS LOGS</h4>
                </div>
                <div id="pi-full-access-logs" style="max-height: 250px; overflow-y:auto; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.05); border-radius:12px; padding:1rem; color:var(--text-dim); font-size:0.8rem;">
                    <div style="text-align:center; padding:1.5rem; opacity:0.5;"><ion-icon name="sync-outline" style="animation:pulse 1s infinite;"></ion-icon> Loading activity records...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.csrfToken = '<?php echo Security::generateCSRF(); ?>';
        window.role = '<?php echo $_SESSION['role']; ?>';
        window.userId = '<?php echo $_SESSION['user_id']; ?>';
    </script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
    <script src="elite_sync.js?v=<?php echo time(); ?>"></script>

<!-- ── Edit User Modal (Admin Managed) ──────────────────────────────────── -->
<div id="edit-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(20px); z-index:4000; align-items:center; justify-content:center; overflow-y:auto; padding:2rem;">
    <div class="modal-box" style="background:var(--card-bg); border:1px solid var(--border); border-radius:1.25rem; padding:2rem; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; animation:slideUpFade 0.3s forwards;">
        <div class="modal-header">
            <h3 style="font-weight:800;">Edit User Record</h3>
            <button class="modal-close" onclick="closeEditModal()" style="background:transparent; border:none; color:var(--text-dim); cursor:pointer; font-size:1.5rem;"><ion-icon name="close-outline"></ion-icon></button>
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
                <label class="field-label">ID Info / Sourcing Credentials</label>
                <textarea id="eu-id-info" name="id_info" class="field-input" rows="2"></textarea>
            </div>
            <div style="margin-bottom:1.25rem;">
                <label class="field-label">Profile Bio / Signature</label>
                <textarea id="eu-bio" name="bio" class="field-input" rows="2"></textarea>
            </div>
            <div class="fields-grid">
                <div>
                    <label class="field-label">Personal Gmail</label>
                    <input type="email" id="eu-gmail" name="gmail" class="field-input">
                </div>
                <div>
                    <label class="field-label">Facebook Profile</label>
                    <input type="url" id="eu-facebook" name="facebook" class="field-input">
                </div>
                <div>
                    <label class="field-label">Instagram Profile</label>
                    <input type="url" id="eu-instagram" name="instagram" class="field-input">
                </div>
            </div>
            <div class="form-section-title">Individual Google OAuth2 Credentials</div>
            <div class="fields-grid">
                <div>
                    <label class="field-label">Google Client ID</label>
                    <input type="text" id="eu-g-id" name="google_client_id" class="field-input">
                </div>
                <div>
                    <label class="field-label">Google Client Secret</label>
                    <input type="text" id="eu-g-secret" name="google_client_secret" class="field-input">
                </div>
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
</body>
</html>

