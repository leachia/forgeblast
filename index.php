<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$role = $_SESSION['role'] ?? 'user';
$userName = $_SESSION['name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlastForge - Premium Email Blast API</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Ionicons for beautiful icons -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</head>
<body>
    <!-- Background Effect -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <ion-icon name="paper-plane"></ion-icon>
            BlastForge
        </div>
        <ul class="nav-links">
            <li class="nav-item active" onclick="showTab('dashboard')">
                <ion-icon name="grid-outline"></ion-icon> Dashboard
            </li>
            <li class="nav-item" onclick="showTab('subscribers')">
                <ion-icon name="people-outline"></ion-icon> Subscribers
            </li>
            <li class="nav-item" onclick="showTab('campaigns')">
                <ion-icon name="mail-outline"></ion-icon> Campaigns
            </li>
            <li class="nav-item" onclick="window.location.href='profile.php'">
                <ion-icon name="person-circle-outline"></ion-icon> Profiles
            </li>
            <li class="nav-item" style="margin-top:auto;" onclick="window.location.href='auth_api.php?action=logout'">
                <ion-icon name="log-out-outline"></ion-icon> Logout
            </li>
            <li class="nav-item" style="display:none;" onclick="window.open('api.php?action=getStats', '_blank')">
                <ion-icon name="code-slash-outline"></ion-icon> API Docs
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header">
            <h1 id="page-title">Dashboard</h1>
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <!-- Header Quick Actions -->
                <div class="header-actions" style="display: flex; gap: 0.75rem;">
                    <button class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="openModal('subscriber-modal')">
                        <ion-icon name="person-add-outline"></ion-icon> <span class="hide-mobile">Add Subscriber</span>
                    </button>
                    <button class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="openModal('campaign-modal')">
                        <ion-icon name="send-outline"></ion-icon> <span class="hide-mobile">New Blast</span>
                    </button>
                </div>
                <!-- User Container -->
                <div class="user-profile" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 500; border-left: 1px solid var(--panel-border); padding-left: 1.5rem;">
                    <ion-icon name="person-circle" style="font-size: 2rem; color: var(--accent-primary);"></ion-icon>
                    <span class="hide-mobile">
                        Hi, <?php echo htmlspecialchars($userName); ?>
                        <small style="color:var(--accent-primary); font-size:0.7rem; text-transform:uppercase; border:1px solid var(--accent-primary); padding:2px 8px; border-radius:12px; margin-left:8px; vertical-align:middle;">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $role)); ?>
                        </small>
                    </span>
                </div>
            </div>
        </header>

        <!-- Stats Section (Dashboard Only) -->
        <div id="dashboard-tab" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Subscribers</div>
                    <div class="stat-value" id="stat-subscribers">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Campaigns Sent</div>
                    <div class="stat-value" id="stat-campaigns">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Total Emails Delivered</div>
                    <div class="stat-value" id="stat-emails">0</div>
                </div>
            </div>

            <!-- Recent Activity Panel -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">System Overview</h2>
                </div>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Welcome to BlastForge. This premium interface connects to the PHP REST API.
                    Navigate to the Subscribers tab to manage your mailing list, or to the Campaigns tab to dispatch an email blast. Use the buttons in the header for quick actions.
                </p>
            </div>
        </div>

        <!-- Subscribers Tab -->
        <div id="subscribers-tab" class="tab-content" style="display: none;">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Mailing List</h2>
                    <button class="btn btn-primary" onclick="openModal('subscriber-modal')">
                        <ion-icon name="add-outline"></ion-icon> Add Subscriber
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="subscribers-table">
                        <thead>
                            <tr>
                                <th id="sub-owner-th" style="display:none;">Owner</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Date Added</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Campaigns Tab -->
        <div id="campaigns-tab" class="tab-content" style="display: none;">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Email Campaigns</h2>
                    <button class="btn btn-primary" onclick="openModal('campaign-modal')">
                        <ion-icon name="send-outline"></ion-icon> Create Blast
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="campaigns-table">
                        <thead>
                            <tr>
                                <th id="camp-sender-th" style="display:none;">Sender</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Date Sent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <!-- Add Subscriber Modal -->
    <div class="modal-overlay" id="subscriber-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Add New Subscriber</h3>
                <button class="close-btn" onclick="closeModal('subscriber-modal')"><ion-icon name="close-outline"></ion-icon></button>
            </div>
            <form id="subscriber-form">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="sub-name" class="form-control" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="sub-email" class="form-control" placeholder="john@example.com" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Save Subscriber</button>
            </form>
        </div>
    </div>

    <!-- Create Campaign Modal -->
    <div class="modal-overlay" id="campaign-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Trigger Email Blast</h3>
                <button class="close-btn" onclick="closeModal('campaign-modal')"><ion-icon name="close-outline"></ion-icon></button>
            </div>
            <form id="campaign-form">
                <div class="form-group">
                    <label>Email Subject</label>
                    <input type="text" id="camp-subject" class="form-control" placeholder="Huge Announcement!" required>
                </div>
                <div class="form-group">
                    <label>Email Content (HTML allowed)</label>
                    <textarea id="camp-content" class="form-control" placeholder="Write your email here..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Attachments (Docs, Pics, Videos)</label>
                    <input type="file" id="camp-attachments" class="form-control" style="padding: 0.5rem;" multiple>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Send Blast to All</button>
            </form>
        </div>
    </div>



    <!-- Toast Notifications -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        window.currentUserRole = '<?php echo $role; ?>';
    </script>
    <script src="app.js"></script>
</body>
</html>
