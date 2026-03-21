<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'db.php';

$userId = $_SESSION['user_id'];
$userRes = $conn->query("SELECT * FROM users WHERE id = $userId");
$user = $userRes->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlastForge - My Profile</title>
    <link rel="stylesheet" href="styles.css">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <style>
        .profile-container {
            max-width: 600px; margin: 0 auto;
            background: var(--panel-bg); border: 1px solid var(--panel-border);
            padding: 2.5rem; border-radius: 1.5rem; text-align: center;
        }
        .avatar-preview {
            width: 120px; height: 120px; border-radius: 50%;
            object-fit: cover; border: 3px solid var(--accent-primary);
            margin-bottom: 1rem;
        }
        .file-upload-wrapper { margin-bottom: 1.5rem; }
        .history-list {
            margin-top: 1.5rem; text-align: left;
            max-height: 200px; overflow-y: auto;
            padding-right: 5px;
        }
        .history-list::-webkit-scrollbar { width: 4px; }
        .history-list::-webkit-scrollbar-thumb { background: var(--accent-primary); border-radius: 10px; }
        .history-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 0.75rem; border-radius: 0.75rem; margin-bottom: 0.5rem;
            display: flex; flex-direction: column; gap: 0.25rem;
            transition: background 0.2s ease;
        }
        .history-item:hover { background: rgba(255, 255, 255, 0.05); }
        .history-subject {
            font-size: 0.9rem; font-weight: 500; color: var(--text-main);
            text-decoration: none; display: block;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .history-subject:hover { color: var(--accent-secondary); }
        .history-meta { display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; }
        .profile-card { cursor: pointer; }
        .profile-card:hover { transform: translateY(-5px); border-color: var(--accent-primary); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
        .modal-wide { max-width: 800px; width: 95%; }
        .history-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 1rem; }
        .history-table th { background: rgba(255,255,255,0.02); padding: 1rem; text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); border-bottom: 1px solid var(--panel-border); }
        .history-table td { padding: 1rem; border-bottom: 1px solid var(--panel-border); font-size: 0.9rem; }
        .history-table tr:hover td { background: rgba(255,255,255,0.03); }
        .tabs-nav {
            display: flex;
            gap: 0.5rem;
            margin: 1.5rem 0 2rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--panel-border);
        }
        .tab-btn {
            background: rgba(255,255,255,0.03);
            border: 1px solid transparent;
            color: var(--text-muted);
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem 0.5rem 0 0;
            cursor: pointer;
            font-size: 0.85rem;
            white-space: nowrap;
            transition: all 0.2s ease;
            display: flex; align-items: center; gap: 0.4rem;
        }
        .tab-btn:hover { background: rgba(255,255,255,0.08); }
        .tab-btn.active {
            color: var(--accent-primary);
            border-bottom: 2px solid var(--accent-primary);
            background: transparent;
            font-weight: 600;
        }
        .tab-content { display: none; margin-top: 1rem; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <aside class="sidebar">
        <div class="brand"><ion-icon name="paper-plane"></ion-icon> BlastForge</div>
        <ul class="nav-links">
            <li class="nav-item" onclick="window.location.href='index.php'"><ion-icon name="arrow-back-outline"></ion-icon> Back to Dashboard</li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1><?php echo ($user['role'] !== 'super_admin' ? 'My Profile' : 'System Profiles'); ?></h1>
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <!-- User Container -->
                <div class="user-profile" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 500;">
                    <ion-icon name="person-circle" style="font-size: 2rem; color: var(--accent-primary);"></ion-icon>
                    <span>
                        Hi, <?php echo htmlspecialchars($user['name']); ?>
                        <small style="color:var(--accent-primary); font-size:0.7rem; text-transform:uppercase; border:1px solid var(--accent-primary); padding:2px 8px; border-radius:12px; margin-left:8px; vertical-align:middle;">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $user['role'])); ?>
                        </small>
                        <?php if ($user['role'] !== 'user'): ?>
                            <div style="margin-top: 5px; font-size: 0.8rem; color: var(--accent-secondary); font-weight: 600;">
                                Referral Code: <span style="background: rgba(139, 92, 246, 0.1); padding: 2px 6px; border-radius: 4px; border: 1px dashed var(--accent-secondary);"><?php echo htmlspecialchars($user['referral_code']); ?></span>
                            </div>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </header>
        
        <?php 
            if ($user['role'] === 'super_admin'): 
                if (empty($user['referral_code'])) {
                    $newCode = 'ADM-' . strtoupper(substr(md5(uniqid()), 0, 6));
                    $conn->query("UPDATE users SET referral_code = '$newCode' WHERE id = $userId");
                    $user['referral_code'] = $newCode;
                }
        ?>
            <div style="background: linear-gradient(135deg, rgba(66, 133, 244, 0.1), rgba(168, 85, 247, 0.1)); border: 1px solid var(--panel-border); padding: 1.5rem; border-radius: 1rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h2 style="font-size: 1.2rem; color: var(--accent-primary);"><ion-icon name="key-outline"></ion-icon> Master Branch Registration Code</h2>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Give this code to target Branch Admins to register them as Admins.</p>
                </div>
                <div style="background: rgba(0,0,0,0.3); padding: 0.75rem 1.5rem; border-radius: 0.75rem; border: 1px dashed var(--accent-primary); font-family: monospace; font-size: 1.5rem; font-weight: bold; color: var(--accent-primary); letter-spacing: 2px;">
                    <?php echo $user['referral_code']; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($user['role'] !== 'super_admin'): ?>
            <div class="profile-container">
                <form id="profile-form">
                    <div class="file-upload-wrapper">
                        <label style="color: var(--text-muted); font-size: 0.9rem; display:block; margin-bottom:0.5rem;">Update Avatar</label>
                        <input type="file" id="avatarInput" accept="image/*" class="form-control">
                    </div>

                    <div class="tabs-nav">
                        <button type="button" class="tab-btn active" onclick="switchTab(this, 'tab-personal')"><ion-icon name="person-outline"></ion-icon> Personal</button>
                        <button type="button" class="tab-btn" onclick="switchTab(this, 'tab-social')"><ion-icon name="share-social-outline"></ion-icon> Social</button>
                        <button type="button" class="tab-btn" onclick="switchTab(this, 'tab-account')"><ion-icon name="shield-checkmark-outline"></ion-icon> Account</button>
                        <button type="button" class="tab-btn" onclick="switchTab(this, 'tab-settings')"><ion-icon name="settings-outline"></ion-icon> Settings</button>
                    </div>

                    <!-- TAB: PERSONAL -->
                    <div class="tab-content active" id="tab-personal">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="text-align: left;">
                                <label>First Name</label>
                                <input type="text" id="prof-firstName" class="form-control" value="<?php echo htmlspecialchars($user['firstName'] ?? ''); ?>" placeholder="First Name">
                            </div>
                            <div class="form-group" style="text-align: left;">
                                <label>Last Name</label>
                                <input type="text" id="prof-lastName" class="form-control" value="<?php echo htmlspecialchars($user['lastName'] ?? ''); ?>" placeholder="Last Name">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="text-align: left;">
                                <label>Age</label>
                                <input type="number" id="prof-age" class="form-control" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>">
                            </div>
                            <div class="form-group" style="text-align: left;">
                                <label>Gender</label>
                                <select id="prof-gender" class="form-control">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php if(($user['gender']??'')=='Male') echo 'selected'; ?>>Male</option>
                                    <option value="Female" <?php if(($user['gender']??'')=='Female') echo 'selected'; ?>>Female</option>
                                    <option value="Other" <?php if(($user['gender']??'')=='Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="text-align: left;">
                            <label>Birthday</label>
                            <input type="date" id="prof-birthday" class="form-control" value="<?php echo htmlspecialchars($user['birthday'] ?? ''); ?>">
                        </div>

                        <div class="form-group" style="text-align: left;">
                            <label>Location / Province</label>
                            <input type="text" id="prof-location" class="form-control" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="E.g. Manila, Philippines">
                        </div>

                        <div class="form-group" style="text-align: left;">
                            <label>Strict Address</label>
                            <input type="text" id="prof-address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" placeholder="Block/Lot, Street, Brgy">
                        </div>
                        
                        <div class="form-group" style="text-align: left;">
                            <label>Identity Documents Info</label>
                            <textarea id="prof-id-info" class="form-control" rows="2" placeholder="ID Number, Document reference, etc."><?php echo htmlspecialchars($user['id_info'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- TAB: SOCIAL -->
                    <div class="tab-content" id="tab-social">
                        <div class="form-group" style="text-align: left;">
                            <label><ion-icon name="call-outline"></ion-icon> Phone Number</label>
                            <input type="text" id="prof-phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+63 9xx xxxx xxx">
                        </div>
                        <div class="form-group" style="text-align: left;">
                            <label><ion-icon name="logo-facebook" style="color:#1877F2;"></ion-icon> Facebook Profile URL</label>
                            <input type="url" id="prof-facebook" class="form-control" value="<?php echo htmlspecialchars($user['facebook'] ?? ''); ?>" placeholder="https://facebook.com/username">
                        </div>
                        <div class="form-group" style="text-align: left;">
                            <label><ion-icon name="logo-instagram" style="color:#E4405F;"></ion-icon> Instagram Profile URL</label>
                            <input type="url" id="prof-instagram" class="form-control" value="<?php echo htmlspecialchars($user['instagram'] ?? ''); ?>" placeholder="https://instagram.com/username">
                        </div>
                        <div class="form-group" style="text-align: left;">
                            <label><ion-icon name="mail-outline"></ion-icon> Personal Gmail / Contact Email</label>
                            <input type="email" id="prof-gmail" class="form-control" value="<?php echo htmlspecialchars($user['gmail'] ?? ''); ?>" placeholder="username@gmail.com">
                        </div>
                    </div>

                    <!-- TAB: ACCOUNT -->
                    <div class="tab-content" id="tab-account">
                        <div class="form-group" style="text-align: left;">
                            <label>Account Status</label>
                            <div style="padding: 0.75rem; border-radius: 0.5rem; background: rgba(255,255,255,0.03); border: 1px solid var(--panel-border); font-size: 0.9rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                                <ion-icon name="shield-outline"></ion-icon> 
                                Verified: <?php echo ($user['is_verified'] ? '<span style="color:var(--success)">YES</span>' : '<span style="color:var(--danger)">NO (Pending)</span>'); ?>
                                | Role: <span style="text-transform: uppercase"><?php echo htmlspecialchars($user['role']); ?></span>
                            </div>
                        </div>

                        <div class="form-group" style="text-align: left;">
                            <label>Login Email Address</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small style="color:var(--text-muted);">Email cannot be changed.</small>
                        </div>

                        <div class="form-group" style="text-align: left;">
                            <label>Signature / Bio</label>
                            <textarea id="prof-bio" class="form-control" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- TAB: SETTINGS -->
                    <div class="tab-content" id="tab-settings">
                        <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--panel-border); margin-bottom: 2rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                <span><ion-icon name="moon-outline"></ion-icon> Dark Mode Preferences</span>
                                <input type="checkbox" id="prof-dark_mode" <?php echo ($user['dark_mode'] ? 'checked' : ''); ?>>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span><ion-icon name="notifications-outline"></ion-icon> Email Notifications</span>
                                <input type="checkbox" id="prof-email_notifications" <?php echo ($user['email_notifications'] ? 'checked' : ''); ?>>
                            </div>
                        </div>

                        <?php if ($user['role'] !== 'user'): ?>
                            <!-- SMTP Section -->
                            <div style="margin-top: 1rem; padding-top: 0.5rem; text-align: left;">
                                <h4 style="margin-bottom: 1rem; color: var(--accent-primary); display: flex; align-items: center; gap: 0.5rem;"><ion-icon name="server-outline"></ion-icon> Sending Server Config</h4>
                                
                                <div style="background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--panel-border); margin-bottom: 1.5rem; text-align: center;">
                                    <?php if (!empty($user['google_refresh_token'])): ?>
                                        <div style="color: var(--success); font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                            <ion-icon name="checkmark-circle-outline"></ion-icon> Gmail OAuth2 Connected
                                        </div>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary" onclick="window.location.href='google_auth.php'" style="background: #fff; color: #000; width: 100%; justify-content: center; font-weight: 600; font-size: 0.8rem;">
                                            <ion-icon name="logo-google" style="color: #4285F4;"></ion-icon> Connect to Gmail (OAuth2)
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label>SMTP Host</label>
                                    <input type="text" id="prof-smtp-host" class="form-control" value="<?php echo htmlspecialchars($user['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com">
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group"><label>SMTP User</label><input type="text" id="prof-smtp-user" class="form-control" value="<?php echo htmlspecialchars($user['smtp_user'] ?? ''); ?>"></div>
                                    <div class="form-group"><label>SMTP Pass</label><input type="password" id="prof-smtp-pass" class="form-control" value="<?php echo htmlspecialchars($user['smtp_pass'] ?? ''); ?>"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 2rem;">
                                <ion-icon name="information-circle-outline" style="font-size: 2rem;"></ion-icon>
                                <p>Email configurations are managed by your Administrator.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Save All Profiles</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="profiles-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem;">
                <?php
                    $allUsersRes = $conn->query("
                        SELECT u.*, 
                               (SELECT COUNT(*) FROM campaigns c WHERE c.user_id = u.id) as campaign_count,
                               IFNULL((SELECT SUM(sent_count) FROM campaigns c WHERE c.user_id = u.id), 0) as emails_sent
                        FROM users u 
                        ORDER BY u.name ASC
                    ");
                    while($u = $allUsersRes->fetch_assoc()):
                ?>
                <div class="profile-card" onclick="viewUserHistory(<?php echo $u['id']; ?>, '<?php echo addslashes($u['name']); ?>')" style="background: var(--panel-bg); border: 1px solid var(--panel-border); padding: 1.5rem; border-radius: 1rem; text-align: center; transition: all 0.3s ease; position: relative;">
                    <?php if ($u['id'] !== $userId): ?>
                    <div style="position: absolute; top: 0.75rem; right: 0.75rem; display: flex; gap: 0.4rem; z-index: 10;">
                        <button class="btn" style="background: var(--accent-primary); color: white; padding: 0.35rem; border-radius: 0.5rem;" onclick='event.stopPropagation(); openEditUserModal(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8"); ?>)'>
                            <ion-icon name="create-outline"></ion-icon>
                        </button>
                        <button class="btn" style="background: var(--danger); color: white; padding: 0.35rem; border-radius: 0.5rem;" onclick="event.stopPropagation(); deleteUser(<?php echo $u['id']; ?>)">
                            <ion-icon name="trash-outline"></ion-icon>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <img src="<?php echo htmlspecialchars($u['avatar']); ?>" style="width: 65px; height: 65px; border-radius: 50%; object-fit: cover; margin-bottom: 0.75rem; border: 2px solid var(--accent-primary);" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($u['name']); ?>&background=random'">
                    
                    <h3 style="font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;"><?php echo htmlspecialchars($u['name']); ?></h3>
                    <p style="color: var(--text-muted); font-size: 0.75rem; margin-bottom:0.25rem; word-break: break-all;"><?php echo htmlspecialchars($u['email']); ?></p>
                    <?php if(!empty($u['phone'])): ?>
                        <p style="color: var(--accent-secondary); font-size: 0.75rem; font-weight: 600; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($u['phone']); ?></p>
                    <?php endif; ?>

                    <!-- Quick Role Switcher for Super Admin -->
                    <select class="form-control" style="width: 100%; padding: 0.35rem; font-size: 0.75rem; margin-top: 0.5rem; text-align: center;" onchange="event.stopPropagation(); changeRole(<?php echo $u['id']; ?>, this.value)" <?php echo ($u['role'] === 'super_admin' ? 'disabled' : ''); ?> onclick="event.stopPropagation()">
                        <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>Member (User)</option>
                        <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Branch Admin</option>
                        <?php if($u['role'] === 'super_admin'): ?>
                            <option value="super_admin" selected>Super Admin</option>
                        <?php endif; ?>
                    </select>

                    <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; background: rgba(0,0,0,0.15); padding: 0.6rem; border-radius: 0.5rem;">
                        <div style="text-align: center; border-right: 1px solid var(--panel-border);">
                            <div style="font-size: 1rem; font-weight: bold; color: var(--accent-primary);"><?php echo $u['campaign_count']; ?></div>
                            <div style="font-size: 0.6rem; color: var(--text-muted); text-transform: uppercase;">Blasts</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1rem; font-weight: bold; color: var(--accent-secondary);"><?php echo $u['emails_sent']; ?></div>
                            <div style="font-size: 0.6rem; color: var(--text-muted); text-transform: uppercase;">Sent</div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Edit User Modal (Super Admin Management) -->
    <div class="modal-overlay" id="edit-user-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Edit User Profile</h3>
                <button class="close-btn" onclick="closeModal('edit-user-modal')"><ion-icon name="close-outline"></ion-icon></button>
            </div>
            <form id="edit-user-form">
                <input type="hidden" id="edit-user-id" name="id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap:0.5rem; text-align: left;">
                    <div class="form-group"><label>First Name</label><input type="text" id="edit-user-firstName" name="firstName" class="form-control" required></div>
                    <div class="form-group"><label>Last Name</label><input type="text" id="edit-user-lastName" name="lastName" class="form-control" required></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap:0.5rem; text-align: left;">
                    <div class="form-group"><label>Email Address</label><input type="email" id="edit-user-email" name="email" class="form-control" required></div>
                    <div class="form-group"><label>Status</label>
                        <select id="edit-user-status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; text-align: left;">
                    <div class="form-group"><label>Age</label><input type="number" id="edit-user-age" name="age" class="form-control"></div>
                    <div class="form-group"><label>Gender</label>
                        <select id="edit-user-gender" name="gender" class="form-control">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; text-align: left;">
                    <div class="form-group"><label>Birthday</label><input type="date" id="edit-user-birthday" name="birthday" class="form-control"></div>
                    <div class="form-group"><label>Location</label><input type="text" id="edit-user-location" name="location" class="form-control"></div>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label>Facebook Profile</label>
                    <input type="text" id="edit-user-facebook" name="facebook" class="form-control" placeholder="https://facebook.com/...">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap:0.5rem; text-align: left;">
                    <div class="form-group"><label>Instagram</label><input type="text" id="edit-user-instagram" name="instagram" class="form-control"></div>
                    <div class="form-group"><label>Contact Email</label><input type="text" id="edit-user-gmail" name="gmail" class="form-control"></div>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label>Identity / ID Info</label>
                    <input type="text" id="edit-user-id-info" name="id_info" class="form-control">
                </div>

                <div class="form-group" style="text-align: left;">
                    <label>User Verification</label>
                    <select id="edit-user-is_verified" name="is_verified" class="form-control">
                        <option value="0">Unverified</option>
                        <option value="1">Verified</option>
                    </select>
                </div>

                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--panel-border); text-align: left;">
                     <h4 style="font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--accent-primary);">Email Sending Config</h4>
                     <input type="text" id="edit-user-smtp-host" name="smtp_host" class="form-control" placeholder="SMTP Host (e.g. smtp.gmail.com)" style="padding: 0.5rem; font-size: 0.8rem; margin-bottom:0.5rem;">
                     <input type="text" id="edit-user-smtp-user" name="smtp_user" class="form-control" placeholder="User Email" style="padding: 0.5rem; font-size: 0.8rem; margin-bottom:0.5rem;">
                     <input type="password" id="edit-user-smtp-pass" name="smtp_pass" class="form-control" placeholder="App Password" style="padding: 0.5rem; font-size: 0.8rem;">
                </div>
                <div class="form-group" style="text-align: left;">
                    <label>System Access Level (Role)</label>
                    <select id="edit-user-role" name="role" class="form-control">
                        <option value="user">Standard User (Member)</option>
                        <option value="admin">Branch Admin</option>
                        <option value="super_admin" disabled>Super Admin (Owner)</option>
                    </select>
                </div>

                <div style="margin-top: 1rem; display: flex; gap:0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 3; justify-content: center;">Update User Record</button>
                    <button type="button" class="btn" style="flex: 1; background: var(--danger); color: white; justify-content: center;" onclick="const id = document.getElementById('edit-user-id').value; if(id) deleteUser(id);">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- User History Modal -->
    <div class="modal-overlay" id="history-modal">
        <div class="modal modal-wide">
            <div class="modal-header">
                <div>
                    <h3 id="history-modal-title">Activity History</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Detailed campaign log for this user.</p>
                </div>
                <button class="close-btn" onclick="closeModal('history-modal')"><ion-icon name="close-outline"></ion-icon></button>
            </div>
            <div style="max-height: 500px; overflow-y: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Campaign Subject</th>
                            <th>Status</th>
                            <th>Delivered</th>
                            <th>Sent Date</th>
                        </tr>
                    </thead>
                    <tbody id="history-table-body">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>
    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const t = document.createElement('div'); t.className = `toast ${type}`;
            const icon = type === 'success' ? 'checkmark-circle' : 'alert-circle';
            t.innerHTML = `<ion-icon name="${icon}" style="font-size: 1.5rem; color: var(--${type})"></ion-icon><div>${message}</div>`;
            container.appendChild(t);
            setTimeout(() => t.classList.add('show'), 10);
            setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 400); }, 3000);
        }

        // Tab Switching
        function switchTab(btn, tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        document.getElementById('profile-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const prev = btn.innerHTML; btn.innerHTML = 'Saving...'; btn.disabled = true;

            const fd = new FormData();
            fd.append('firstName', document.getElementById('prof-firstName').value);
            fd.append('lastName', document.getElementById('prof-lastName').value);
            fd.append('bio', document.getElementById('prof-bio').value);
            fd.append('age', document.getElementById('prof-age').value);
            fd.append('gender', document.getElementById('prof-gender').value);
            fd.append('birthday', document.getElementById('prof-birthday').value);
            fd.append('location', document.getElementById('prof-location').value);
            fd.append('address', document.getElementById('prof-address').value);
            fd.append('id_info', document.getElementById('prof-id-info').value);
            
            // Socials
            fd.append('phone', document.getElementById('prof-phone').value);
            fd.append('facebook', document.getElementById('prof-facebook').value);
            fd.append('instagram', document.getElementById('prof-instagram').value);
            fd.append('gmail', document.getElementById('prof-gmail').value);

            // Settings
            fd.append('dark_mode', document.getElementById('prof-dark_mode').checked ? 1 : 0);
            fd.append('email_notifications', document.getElementById('prof-email_notifications').checked ? 1 : 0);

            // SMTP (Optional per role)
            fd.append('smtp_host', document.getElementById('prof-smtp-host')?.value || '');
            fd.append('smtp_user', document.getElementById('prof-smtp-user')?.value || '');
            fd.append('smtp_pass', document.getElementById('prof-smtp-pass')?.value || '');
            fd.append('smtp_port', document.getElementById('prof-smtp-port')?.value || '');

            const avatar = document.getElementById('avatarInput').files[0];
            if (avatar) fd.append('avatar', avatar);

            try {
                const res = await fetch('api.php?action=updateProfile', { method: 'POST', body: fd });
                const data = await res.json();
                if (res.ok) {
                    showToast('Profile updated!');
                    if (data.avatar_url) document.getElementById('avatarImg').src = data.avatar_url;
                } else {
                    showToast(data.error, 'error');
                }
            } catch(e) { 
                showToast('Error: ' + e.message, 'error'); 
            }
            btn.innerHTML = prev; btn.disabled = false;
        });

        // Modal Controls
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        function openEditUserModal(u) {
            document.getElementById('edit-user-id').value = u.id;
            document.getElementById('edit-user-email').value = u.email;
            document.getElementById('edit-user-id-info').value = u.id_info || '';
            document.getElementById('edit-user-role').value = u.role;
            
            // Populate based on JSON
            document.getElementById('edit-user-firstName').value = u.firstName || '';
            document.getElementById('edit-user-lastName').value = u.lastName || '';
            document.getElementById('edit-user-age').value = u.age || '';
            document.getElementById('edit-user-gender').value = u.gender || '';
            document.getElementById('edit-user-phone').value = u.phone || '';
            document.getElementById('edit-user-address').value = u.address || '';
            document.getElementById('edit-user-location').value = u.location || '';
            document.getElementById('edit-user-birthday').value = u.birthday || '';
            document.getElementById('edit-user-facebook').value = u.facebook || '';
            document.getElementById('edit-user-instagram').value = u.instagram || '';
            document.getElementById('edit-user-gmail').value = u.gmail || '';
            document.getElementById('edit-user-id-info').value = u.id_info || '';
            document.getElementById('edit-user-status').value = u.status || 'active';
            document.getElementById('edit-user-is_verified').value = u.is_verified ? "1" : "0";

            document.getElementById('edit-user-smtp-host').value = u.smtp_host || '';
            document.getElementById('edit-user-smtp-user').value = u.smtp_user || '';
            document.getElementById('edit-user-smtp-pass').value = u.smtp_pass || '';
            
            document.getElementById('edit-user-modal').classList.add('active');
        }

        document.getElementById('edit-user-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const prev = btn.innerHTML; btn.innerHTML = 'Saving...'; btn.disabled = true;
            try {
                const formData = new FormData(e.target);
                const payload = Object.fromEntries(formData.entries());
                const res = await fetch(`api.php?action=adminUpdateUser`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (res.ok) {
                    showToast('User updated!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else { showToast('Update failed', 'error'); }
            } catch (err) { showToast('Network error', 'error'); }
            btn.innerHTML = prev; btn.disabled = false;
        });

        async function deleteUser(userId) {
            if (!confirm('Are you sure you want to permanently delete this user and all their data?')) return;
            try {
                const res = await fetch(`api.php?action=deleteUser&id=${userId}`, { method: 'DELETE' });
                if (res.ok) {
                    showToast('User deleted securelly', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else { showToast('Delete failed', 'error'); }
            } catch (e) { showToast('Network error', 'error'); }
        }

        async function changeRole(userId, newRole) {
            if(!confirm(`Change access role to ${newRole.toUpperCase()}?`)) {
                window.location.reload();
                return;
            }
            try {
                const res = await fetch(`api.php?action=updateRole`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ target_id: userId, role: newRole })
                });
                const data = await res.json();
                if (res.ok) {
                    showToast('Access role updated successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.error || 'Update failed', 'error');
                    setTimeout(() => window.location.reload(), 1500);
                }
            } catch (err) {
                showToast('Network error', 'error');
                setTimeout(() => window.location.reload(), 1500);
            }
        }

        async function viewUserHistory(id, name) {
            document.getElementById('history-modal-title').innerText = `${name}'s History`;
            const tbody = document.getElementById('history-table-body');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 2rem;"><ion-icon name="sync-outline" style="font-size: 2rem; animation: spin 2s linear infinite;"></ion-icon><br>Loading history...</td></tr>';
            openModal('history-modal');

            try {
                const res = await fetch(`api.php?action=getCampaigns&target_user_id=${id}`);
                const data = await res.json();
                tbody.innerHTML = '';

                if (!data.campaigns || data.campaigns.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 2rem; color: var(--text-muted)">No activity recorded for this user yet.</td></tr>';
                    return;
                }

                data.campaigns.forEach(c => {
                    const cStatus = c.status;
                    const cBadgeColor = (cStatus === 'sent') ? '#34d399' : ((cStatus === 'pending') ? '#fbbf24' : ((cStatus === 'rejected') ? '#f87171' : '#9ca3af'));
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <a href="view.php?id=${c.id}" target="_blank" style="color: var(--text-main); text-decoration: none; font-weight: 500;">
                                ${c.subject}
                            </a>
                        </td>
                        <td>
                            <span style="color: ${cBadgeColor}; font-weight: 600; text-transform: uppercase; font-size: 0.75rem;">${cStatus}</span>
                        </td>
                        <td style="color: var(--accent-secondary); font-weight: 600;">${c.sent_count}</td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;">${new Date(c.created_at).toLocaleDateString()}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color: var(--danger)">Failed to load history.</td></tr>';
            }
        }

        function autoFillSmtp(prefix) {
            const provider = document.getElementById(prefix + '-smtp-provider').value;
            const hostField = document.getElementById(prefix + '-smtp-host');
            const portField = document.getElementById(prefix + '-smtp-port');
            const detailsDiv = document.getElementById(prefix + '-smtp-details');

            if (!provider) {
                detailsDiv.style.display = 'none';
                return;
            }

            detailsDiv.style.display = (provider === 'custom') ? 'block' : 'none';

            if (provider === 'gmail') {
                hostField.value = 'smtp.gmail.com';
                portField.value = '465';
            } else if (provider === 'outlook') {
                hostField.value = 'smtp-mail.outlook.com';
                portField.value = '587';
            } else if (provider === 'yahoo') {
                hostField.value = 'smtp.mail.yahoo.com';
                portField.value = '465';
            }
        }

        function openModal(id) { document.getElementById(id).classList.add('active'); }
    </script>
</body>
</html>
