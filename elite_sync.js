/* 🚀 BLASTFORGE SYNC ENGINE v4.5 (Elite Hierarchy Patch) */

function showToast(msg, type = 'success') {
    let c = document.getElementById('toast-container');
    if (!c) {
        c = document.createElement('div');
        c.id = 'toast-container';
        c.style.cssText = 'position:fixed; bottom:2rem; right:2rem; z-index:99999; display:flex; flex-direction:column; gap:0.75rem;';
        document.body.appendChild(c);
    }
    const t = document.createElement('div');
    t.style.cssText = `background:var(--card-bg, #111); border:1px solid ${type==='success'?'rgba(52,211,153,0.3)':'rgba(239,68,68,0.3)'}; color:${type==='success'?'#34d399':'#f87171'}; padding:0.85rem 1.25rem; border-radius:0.75rem; font-size:0.85rem; font-weight:600; max-width:320px; animation:slideUpFade 0.3s forwards; box-shadow:0 10px 30px rgba(0,0,0,0.3);`;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3500);
}

const securedFetch = async (url, options = {}) => {
    try {
        const headers = options.headers || {};
        if (window.csrfToken) headers['X-CSRF-TOKEN'] = window.csrfToken;
        options.headers = headers;
        const res = await fetch(url, options);
        if (!res) return null;
        if (res.status === 401 || res.status === 403) {
             console.warn("EliteSync: Session expired or unauthorized.");
             return null;
        }
        const contentType = res.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
             console.error("EliteSync: Server returned non-JSON response from " + url);
             return null;
        }
        return res;
    } catch (e) {
        console.error("Critical Sync Error:", e);
        return null;
    }
};

async function loadDashboard() {
    const res = await securedFetch('api.php?action=getStats');
    if (!res) return;
    const data = await res.json();
    
    const syncMap = {
        'stat-subs': data.total_subs,
        'stat-camps': data.total_campaigns,
        'stat-emails': data.total_delivered,
        'stat-users': data.total_users,
        'stat-quota': data.quota_used,
        'stat-opens': data.total_opens,
        'stat-clicks': data.total_clicks,
        'stat-total-msg': data.total_messages,
        'stat-unread-msg': data.unread_messages
    };

    const badge = document.getElementById('nav-msg-badge');
    if (badge && data.unread_messages > 0) {
        badge.style.display = 'inline-block';
        badge.innerText = data.unread_messages + ' New';
    } else if (badge) {
        badge.style.display = 'none';
    }

    // 🕵️ System Health Monitor (ADVANCED)
    const wDot = document.getElementById('worker-status-dot');
    const wText = document.getElementById('worker-status-text');
    const qText = document.getElementById('queue-status-text');
    
    if (wDot && wText) {
        if (data.worker_online) {
            wDot.style.background = '#34d399';
            wDot.classList.add('pulse-online');
            wText.innerText = 'ONLINE (' + data.last_heartbeat + ')';
            wText.style.color = '#34d399';
        } else {
            wDot.style.background = '#f87171';
            wDot.classList.remove('pulse-online');
            wDot.style.boxShadow = 'none';
            wText.innerText = 'OFFLINE';
            wText.style.color = '#f87171';
        }
    }

    if (qText) {
        qText.innerText = (data.pending_queue || 0) + ' PENDING';
        qText.style.display = data.pending_queue > 0 ? 'inline-block' : (data.worker_online ? 'inline-block' : 'none');
    }

    Object.entries(syncMap).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el) el.innerText = val !== undefined ? val : '--';
    });

    if (data.quota_used !== undefined) {
        const progress = document.getElementById('quota-progress');
        if (progress) progress.style.width = Math.min((data.quota_used / 250000) * 100, 100) + '%';
    }

    const chartLabel = document.getElementById('stat-total-msg') ? 'Sourcing Velocity' : 'Global Activity';
    renderChart(data.chart_labels, data.chart_data, chartLabel);

    if (typeof window.role === 'undefined') {
        console.warn("EliteSync: Role undefined.");
        return;
    }

    if (window.role !== 'user') {
        if (document.getElementById('overview-team-list')) loadBranchPersonnel();
        if (document.getElementById('leaderboard-list')) loadLeaderboard();
        if (document.getElementById('heatmapChart')) loadHeatmap();
        if (document.getElementById('system-alerts-list')) loadSystemAlerts();
    } else {
        if (document.getElementById('insights-list')) loadLeadInsights();
    }
}

async function loadBranchPersonnel() {
    const res = await securedFetch('api.php?action=getUsers');
    if (!res) return;
    const data = await res.json();
    const list = document.getElementById('overview-team-list');
    if (!list) return;

    list.innerHTML = data.users.filter(u => u.id != window.userId).map(u => {
        const initials = u.name.split(' ').map(n=>n[0]).join('').toUpperCase().substring(0,2);
        return `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:1rem; background:rgba(255,255,255,0.03); border-radius:15px; margin-bottom:0.75rem; border:1px solid rgba(255,255,255,0.05);">
                <div style="display:flex; gap:1rem; align-items:center;">
                    <div style="width:40px; height:40px; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem; color:#000;">${initials}</div>
                    <div>
                        <div style="font-weight:700; color:#fff; font-size:0.85rem;">${u.name}</div>
                        <div style="font-size:0.65rem; color:var(--text-dim);">${u.role.toUpperCase()} &bull; ${u.is_online ? '<span style="color:#34d399;">Active</span>' : 'Idle'}</div>
                    </div>
                </div>
                <button onclick='viewProfileDetails(${JSON.stringify(u).replace(/'/g, "&apos;")})' style="background:transparent; border:1px solid var(--border); color:#fff; font-size:0.65rem; padding:6px 14px; border-radius:10px; cursor:pointer;">PROFILE</button>
            </div>
        `;
    }).join('') || '<div style="text-align:center; padding:2rem; opacity:0.5; font-size:0.8rem;">No branch personnel detected.</div>';
}

let piTargetUser = null;

function viewProfileDetails(u) {
    piTargetUser = u;
    let isAuthorized = false;
    // Strictly restrict new features to Admin viewing their Staff
    if (window.role === 'admin' && u.role === 'staff') {
        isAuthorized = true;
    }

    const editBtnBox = document.getElementById('pi-admin-edit');
    if (editBtnBox) {
        editBtnBox.style.display = isAuthorized ? 'block' : 'none';
    }

    document.getElementById('pi-name').innerText = u.name;
    document.getElementById('pi-role-badge').innerText = u.role.toUpperCase();
    document.getElementById('pi-bio').innerText = u.bio || 'This member has not provided a professional signature yet.';
    document.getElementById('pi-gmail').innerText = u.gmail || u.email;
    document.getElementById('pi-phone').innerText = u.phone || 'Not Specified';
    document.getElementById('pi-subs').innerText = u.total_emails || 0;
    document.getElementById('pi-camps').innerText = u.total_campaigns || 0;
    document.getElementById('pi-status').innerText = (u.status || 'Active').toUpperCase();
    document.getElementById('pi-status').style.color = u.status === 'suspended' ? '#f87171' : '#34d399';
    
    document.getElementById('pi-avatar').innerText = u.name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
    
    const fb = document.getElementById('pi-fb');
    const ig = document.getElementById('pi-ig');
    fb.style.display = u.facebook ? 'flex' : 'none';
    if(u.facebook) fb.href = u.facebook;
    ig.style.display = u.instagram ? 'flex' : 'none';
    if(u.instagram) ig.href = u.instagram;
    
    openModal('profile-inspect-modal');

    // Fetch and load Full Access Activity Logs
    const logsWrapper = document.getElementById('pi-logs-wrapper');
    if (logsWrapper) {
        logsWrapper.style.display = isAuthorized ? 'block' : 'none';
    }

    const logsContainer = document.getElementById('pi-full-access-logs');
    if (isAuthorized && logsContainer) {
        logsContainer.innerHTML = '<div style="text-align:center; padding:1.5rem; opacity:0.5;"><ion-icon name="sync-outline" style="animation:pulse 1s infinite;"></ion-icon> Loading activity records...</div>';
        securedFetch('api.php?action=getLogs&target_id=' + u.id)
            .then(res => res ? res.json() : {logs: []})
            .then(data => {
                if(data.logs && data.logs.length > 0) {
                    let logHtml = '<table style="width:100%; border-collapse:collapse;"><tbody>';
                    data.logs.forEach(l => {
                        let clr = 'var(--text-dim)';
                        if(l.action === 'login') clr = '#34d399';
                        if(l.action === 'logout') clr = '#f87171';
                        if(l.action === 'campaign_sent') clr = '#8b5cf6';
                        logHtml += `<tr>
                            <td style="color:var(--text-dim); padding:0.4rem 0; width:130px;">${new Date(l.created_at).toLocaleString()}</td>
                            <td style="font-weight:800; color:${clr}; text-transform:uppercase; padding:0.4rem 0.5rem; width:120px;">${l.action.replace(/_/g,' ')}</td>
                            <td style="color:var(--text-dim); padding:0.4rem 0.5rem;">${l.details || l.notes || '-'}</td>
                            <td style="color:var(--text-dim); font-size:0.7rem; font-family:monospace; text-align:right; padding:0.4rem 0;">${l.ip_address || ''}</td>
                        </tr>`;
                    });
                    logHtml += '</tbody></table>';
                    logsContainer.innerHTML = logHtml;
                } else {
                    logsContainer.innerHTML = '<div style="color:var(--text-dim); text-align:center; padding:1.5rem; font-style:italic;">No activity records found for this team member.</div>';
                }
            })
            .catch(err => {
                logsContainer.innerHTML = '<div style="color:#f87171; text-align:center; padding:1.5rem;">Error loading activity logs.</div>';
            });
    }
}

function renderChart(labels, data, label = 'Global Activity') {
    const canvas = document.getElementById('mainChart');
    if (!canvas || typeof Chart === 'undefined') return;
    if (window.performanceChart) window.performanceChart.destroy();
    window.performanceChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                borderColor: '#8b5cf6',
                borderWidth: 4,
                pointRadius: 6,
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(139, 92, 246, 0.05)'
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
}

async function loadHeatmap() {
    const canvas = document.getElementById('heatmapChart');
    if (!canvas || typeof Chart === 'undefined') return;
    const res = await securedFetch('api.php?action=getHeatmap');
    const data = await res.json();
    
    if (window.heatmapChart) window.heatmapChart.destroy();
    window.heatmapChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => i + ':00'),
            datasets: [{
                label: 'Engagement Density',
                data: Object.values(data.heatmap),
                backgroundColor: 'rgba(52, 211, 153, 0.5)',
                borderColor: '#34d399',
                borderWidth: 1
            }]
        },
        options: { 
            maintainAspectRatio: false, 
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } } }
        }
    });
}

async function loadSystemAlerts() {
    const list = document.getElementById('system-alerts-list');
    if (!list) return;
    const res = await securedFetch('api.php?action=getSystemAlerts');
    const alerts = await res.json();
    
    if (alerts.length === 0) {
        list.innerHTML = '<div style="padding:1rem; color: #34d399; text-align:center; font-size:0.8rem; font-weight:600;"><ion-icon name="checkmark-circle" style="vertical-align:middle;"></ion-icon> All systems operational. No active alerts.</div>';
        return;
    }

    list.innerHTML = alerts.map(a => `
        <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(239, 68, 68, 0.05); border:1px solid rgba(239, 68, 68, 0.1); border-radius:10px; padding:0.75rem 1rem; margin-bottom:0.5rem;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <ion-icon name="${a.type === 'critical' ? 'flame-outline' : 'warning-outline'}" style="color:#f87171; font-size:1.2rem;"></ion-icon>
                <div>
                    <div style="font-weight:800; font-size:0.75rem; color:#fff;">${a.source.toUpperCase()}</div>
                    <div style="font-size:0.7rem; color:var(--text-dim);">${a.message}</div>
                </div>
            </div>
            <div style="font-size:0.6rem; color:var(--text-dim); text-align:right;">${new Date(a.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
        </div>
    `).join('');
}

async function loadLeaderboard() {
    const res = await securedFetch('api.php?action=getLeaderboard');
    if (!res) return;
    const data = await res.json();
    const container = document.getElementById('leaderboard-list');
    if (!container) return;

    container.innerHTML = data.leaderboard.map((u, i) => `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:1rem; background:rgba(255,255,255,0.02); border-radius:12px; margin-bottom:0.5rem; border:1px solid rgba(255,255,255,0.05);">
            <div style="display:flex; gap:1rem; align-items:center;">
                <span style="font-weight:900; color:var(--primary); width:20px;">#${i+1}</span>
                <span style="font-weight:700;">${u.name}</span>
            </div>
            <div style="text-align:right;">
                <div style="font-weight:900; color:#34d399;">${Math.round(u.open_rate)}%</div>
                <div style="font-size:0.6rem; color:var(--text-dim);">${u.total_opens} OPENS</div>
            </div>
        </div>
    `).join('') || '<div style="text-align:center; padding:2rem; opacity:0.5;">Calculating rankings...</div>';
}

async function loadLeadInsights() {
    const res = await securedFetch('api.php?action=getLeadInsights');
    if (!res) return;
    const data = await res.json();
    const container = document.getElementById('insights-list');
    if (!container) return;

    container.innerHTML = data.insights.map(l => {
        const prob = Math.round(l.conv_prob);
        const color = prob > 70 ? '#34d399' : (prob > 40 ? '#fbbf24' : '#f87171');
        return `
            <div style="padding:1rem; background:rgba(255,255,255,0.02); border-radius:12px; margin-bottom:0.75rem; border-left:4px solid ${color};">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:700; font-size:0.85rem;">${l.name}</span>
                    <span style="font-weight:900; color:${color}; font-size:0.9rem;">${prob}%</span>
                </div>
                <div style="font-size:0.7rem; color:var(--text-dim); margin-top:0.3rem;">Score: ${l.lead_score} &bull; Conversion Probability</div>
            </div>
        `;
    }).join('') || '<div style="text-align:center; padding:2rem; opacity:0.5;">Analyzing behavioral patterns...</div>';
}

async function handleCSVImport(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const fd = new FormData();
    fd.append('csv', file);

    const btn = document.querySelector('button[onclick*="csv-import-input"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<ion-icon name="sync-outline" style="animation:pulse 1s infinite;"></ion-icon> IMPORTING...';

    try {
        const res = await fetch('api.php?action=importSubscribers', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.csrfToken },
            body: fd
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message);
            loadSubscribers();
        } else {
            showToast(data.error || 'Import failed.', 'error');
        }
    } catch (e) {
        showToast('Network error during import.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
        input.value = '';
    }
}

async function loadSubscribers() {
    const res = await securedFetch('api.php?action=getSubscribers');
    if (!res) return;
    const data = await res.json();
    const tbody = document.querySelector('#audience-tab table tbody');
    if (tbody) {
        tbody.innerHTML = data.subscribers.map(s => `
            <tr class="stagger-item">
                <td style="font-weight:600; color:#fff;">${s.name}</td>
                <td style="color:var(--primary-bright);">${s.email}</td>
                <td><span class="badge-${s.status.toLowerCase()}">${s.status.toUpperCase()}</span></td>
                <td style="font-size:0.75rem; color:var(--text-dim);">${s.owner || '(Me)'}</td>
                <td style="text-align:right;">
                    <button onclick="deleteSubscriber(${s.id})" style="background:rgba(239,68,68,0.1); border:none; color:#f87171; width:30px; height:30px; border-radius:0.4rem; cursor:pointer; display:flex; align-items:center; justify-content:center; float:right;" title="Remove Subscriber"><ion-icon name="trash-outline"></ion-icon></button>
                </td>
            </tr>
        `).join('');
    }
}

async function applyTemplate() {
    const sel = document.getElementById('camp-template').value;
    const contentArea = document.getElementById('camp-content');
    if (!sel) {
        contentArea.value = '';
        return;
    }
    const res = await securedFetch('api.php?action=getTemplates');
    const data = await res.json();
    const tpl = data.templates.find(t => t.id === sel);
    if (tpl) {
        contentArea.value = tpl.content; 
    }
}

async function onboardMember(e) {
    if (e) e.preventDefault();
    const btn = e.submitter || e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerText = 'ADDING USER...';
    
    const fd = new FormData(e.target);
    const res = await securedFetch('api.php?action=addUser', {
        method: 'POST',
        body: fd
    });
    
    if (!res) {
        showToast('Network error, please try again.', 'error');
        btn.innerHTML = originalText;
        return;
    }

    const data = await res.json();
    if (data.status === 'success') {
        showToast(data.message);
        closeModal('onboard-modal');
        e.target.reset();
        loadDashboard();
    } else {
        showToast(data.error || 'Failed to add user', 'error');
    }
    btn.innerHTML = originalText;
}

async function addSubscriber(e) {
    if (e) e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerText = 'ADDING...';
    
    const fd = new FormData(e.target);
    const res = await securedFetch('api.php?action=addSubscriber', { method: 'POST', body: fd });
    
    if (!res) {
        showToast('Network error, please try again.', 'error');
        btn.innerHTML = originalText;
        return;
    }

    const data = await res.json();
    if (data.status === 'success') {
        showToast(data.message);
        closeModal('add-sub-modal');
        e.target.reset();
        loadSubscribers();
    } else {
        showToast(data.error || 'Failed to add subscriber.', 'error');
    }
    btn.innerHTML = originalText;
}

async function deleteSubscriber(id) {
    if (!confirm('Are you sure you want to remove this subscriber from the list?')) return;
    
    const res = await securedFetch('api.php?action=deleteSubscriber&id=' + id);
    if (!res) {
        showToast('Error removing subscriber.', 'error');
        return;
    }
    const data = await res.json();
    if (data.status === 'success') {
        showToast(data.message);
        loadSubscribers();
    } else {
        showToast(data.error || 'Failed to remove subscriber.', 'error');
    }
}

async function sendBlast(btn) {
    if (btn) btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<ion-icon name="sync-outline" style="animation:pulse 1s infinite;"></ion-icon> QUEUING...';
    
    const fd = new FormData();
    fd.append('subject', document.getElementById('camp-subject').value);
    fd.append('subject_b', document.getElementById('camp-subject-b').value);
    fd.append('weight_a', document.getElementById('camp-weight-a').value);
    fd.append('weight_b', document.getElementById('camp-weight-b').value);
    fd.append('template', 'Modern Visual'); // fixed for now
    fd.append('content', document.getElementById('camp-content').value);
    
    try {
        const res = await fetch('api.php?action=sendBlast', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.csrfToken },
            body: fd
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message);
            closeModal('campaign-modal');
            loadDashboard();
            loadCampaigns();
        } else {
            showToast(data.error || 'Failed to queue blast.', 'error');
        }
    } catch(e) {
        showToast('Network error.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function handleCSVImport(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    const fd = new FormData();
    fd.append('csv', file);
    
    showToast("Processing CSV import, please wait...");
    const res = await securedFetch('api.php?action=importSubscribers', {
        method: 'POST',
        body: fd
    });
    
    if (res) {
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message);
            loadSubscribers();
            loadDashboard();
        } else {
            showToast(data.error || "Import failed", "error");
        }
    }
    input.value = ""; // clear input
}

let currentTeamParentId = null;
let teamDataCache = [];

async function loadTeamMembers(parentId = null) {
    currentTeamParentId = parentId;
    const res = await securedFetch('api.php?action=getUsers');
    if (!res) return;
    const data = await res.json();
    teamDataCache = data.users;
    renderTeamGrid(null); // start root view
}

function renderTeamGrid(parentId) {
    const grid = document.getElementById('teamGrid');
    const bcrumb = document.getElementById('team-breadcrumb');
    if (!grid) return;

    // 🔬 HIERARCHY SHIELD: What do we see at the top?
    const filtered = teamDataCache.filter(u => {
        // We are at the main dashboard (no branch selected yet)
        if (!parentId || parentId === null) {
            if (window.role === 'super_admin') {
                // IMPORTANT: Super Admin should ONLY see Tier 2 (Admins).
                // Hide self (SA), Hide Tier 3 (Staff), Hide Tier 4 (Users).
                return u.role === 'admin';
            } else if (window.role === 'admin') {
                // Admins see their Tier 3 (Staff) first.
                return u.role === 'staff' && u.referred_by_admin_id == window.userId;
            } else if (window.role === 'staff') {
                // Staff see their Tier 4 (Users) first.
                return u.role === 'user' && u.referred_by_admin_id == window.userId;
            }
            return false;
        }
        // Drill-Down: Show all direct children of this node
        return u.referred_by_admin_id == parentId;
    });

    // Tier Label Mapping (Strict)
    const getTierLabel = (r) => {
        if (r === 'super_admin') return 'SYSTEM OVERLORD (TIER 1)';
        if (r === 'admin') return 'REGIONAL ADMIN (TIER 2)';
        if (r === 'staff') return 'BRANCH STAFF (TIER 3)';
        return 'TEAM MEMBER (TIER 4)';
    };

    // Update Crumbs
    if (!parentId) {
        bcrumb.innerHTML = `<span onclick="renderTeamGrid(null)" style="cursor:pointer; color:var(--primary-bright);"><ion-icon name="business-outline"></ion-icon> All Organization Branches</span>`;
    } else {
        const parent = teamDataCache.find(x => x.id == parentId);
        bcrumb.innerHTML = `
            <span onclick="renderTeamGrid(null)" style="cursor:pointer; opacity:0.6;"><ion-icon name="chevron-back-outline"></ion-icon> Root Level</span>
            <ion-icon name="chevron-forward-outline" style="opacity:0.3;"></ion-icon>
            <span style="color:var(--primary-bright);">${parent ? parent.name : 'Branch'}'s Department</span>
        `;
    }

    grid.innerHTML = filtered.map(u => {
        const initials = u.name.split(' ').map(n=>n[0]).join('').toUpperCase().substring(0,2);
        const hasChildren = teamDataCache.some(child => child.referred_by_admin_id == u.id);
        const lastLogin = u.last_login_at ? new Date(u.last_login_at.replace(' ', 'T')).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : 'Never';
        const isSelf = u.id == window.userId;

        const colors = ['#6366f1', '#a855f7', '#ec4899', '#f97316', '#10b981', '#06b6d4'];
        const avatarBg = colors[u.id % colors.length];

        return `
            <div class="card-premium stagger-item" style="padding:1.8rem; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.02); display:flex; flex-direction:column; border-radius:28px; min-height:430px; position:relative;">
                
                <!-- Status & Utility Controls -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <div style="width:12px; height:12px; border-radius:50%; background:${u.is_online ? '#34d399' : '#4b5563'}; box-shadow:${u.is_online ? '0 0 15px rgba(52,211,153,0.4)' : 'none'};" title="${u.is_online?'Active':'Idle'}"></div>
                    <div style="display:flex; gap:0.5rem;">
                         ${(window.role === 'super_admin' || (window.role === 'admin' && u.role !== 'admin')) ? `
                            <button onclick="openPassReset(${u.id})" style="width:36px; height:36px; border-radius:10px; border:none; background:rgba(167,139,250,0.15); color:#a78bfa; cursor:pointer;" title="Credential Vault"><ion-icon name="key-outline"></ion-icon></button>
                        ` : ''}
                        <button onclick='viewProfileDetails(${JSON.stringify(u).replace(/'/g, "&apos;")})' style="width:36px; height:36px; border-radius:10px; border:none; background:rgba(255,255,255,0.05); color:#fff; cursor:pointer;" title="Identity Profile"><ion-icon name="person-outline"></ion-icon></button>
                        ${!isSelf ? `<button onclick="deleteUser(${u.id})" style="width:36px; height:36px; border-radius:10px; border:none; background:rgba(239, 68, 68, 0.15); color:#f87171; cursor:pointer;" title="Remove User"><ion-icon name="trash-outline"></ion-icon></button>` : ''}
                    </div>
                </div>

                <!-- Card Body: User Info -->
                <div style="text-align:center; flex-grow:1; margin-bottom:1.5rem;">
                    <div style="width:95px; height:95px; border-radius:50%; background:${avatarBg}; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:2.5rem; font-weight:800; border:5px solid rgba(255,255,255,0.05); box-shadow:0 15px 35px rgba(0,0,0,0.4); margin-bottom:1.5rem;">${initials}</div>
                    <h3 style="color:#fff; font-size:1.25rem; font-weight:900; margin-bottom:0.25rem; letter-spacing:-0.5px;">${u.name}</h3>
                    <p style="color:var(--text-dim); font-size:0.8rem; margin-bottom:1.25rem;">${u.email}</p>
                    
                    <div class="badge-${u.role.toLowerCase()}" style="display:inline-block; font-size:0.6rem; padding:6px 16px; border-radius:30px; letter-spacing:1px; font-weight:900; background:rgba(167,139,250,0.1); color:var(--primary-bright); border:1px solid rgba(167,139,250,0.2);">${getTierLabel(u.role)}</div>
                </div>

                <!-- Stats Section -->
                <div style="background:rgba(0,0,0,0.3); border-radius:20px; padding:1.25rem; display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; border:1px solid rgba(255,255,255,0.03); margin-bottom:1.25rem;">
                    <div style="text-align:center; border-right:1px solid rgba(255,255,255,0.05);">
                        <div style="color:#fff; font-size:1.3rem; font-weight:900;">${u.total_campaigns || 0}</div>
                        <div style="font-size:0.55rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; font-weight:800;">Campaigns</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="color:#fff; font-size:1.3rem; font-weight:900;">${Math.round(u.total_emails || 0)}</div>
                        <div style="font-size:0.55rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; font-weight:800;">Captured Emails</div>
                    </div>
                </div>

                <!-- Navigation Action -->
                ${hasChildren ? `
                <button onclick="renderTeamGrid(${u.id})" class="btn-premium" style="width:100%; justify-content:center; height:52px; font-size:0.8rem; background:var(--primary); color:#000; font-weight:800; border:none; box-shadow:0 0 30px rgba(139, 92, 246, 0.2); border-radius:16px;">
                    <ion-icon name="people-outline" style="font-size:1.2rem;"></ion-icon> VIEW REFERRED BRANCH
                </button>
                ` : `
                <div style="padding:1.2rem; color:var(--text-dim); font-size:0.65rem; text-align:center; font-style:italic; border:1px dashed rgba(255,255,255,0.05); border-radius:15px; background:rgba(255,255,255,0.01);">No members under this branch</div>
                `}

                <div style="margin-top:1.5rem; text-align:center; font-size:0.65rem; opacity:0.6;">
                    Active Since: ${lastLogin}
                </div>
            </div>
        `;
    }).join('') || `<div style="grid-column:1/-1; text-align:center; padding:6rem; opacity:0.5;">
        <ion-icon name="file-tray-outline" style="font-size:4.5rem; margin-bottom:1.5rem; color:var(--primary-bright);"></ion-icon>
        <p style="font-size:1rem; font-weight:700;">No members found at this tier level.</p>
    </div>`;
}

async function deleteUser(id) {
    if (!confirm('CRITICAL: Are you sure you want to PERMANENTLY remove this team member? This action cannot be undone.')) return;
    const res = await securedFetch('api.php?action=deleteUser&id=' + id);
    if (!res) return;
    const data = await res.json();
    if (data.status === 'success') {
        showToast('Member successfully removed from the organization.');
        loadTeamMembers();
        loadDashboard();
    } else {
        showToast(data.error || 'Failed to remove member.', 'error');
    }
}

async function loadDeletedUsers() {
    const res = await securedFetch('api.php?action=getDeletedUsers');
    if (!res) return;
    const data = await res.json();
    const tbody = document.querySelector('#deleted-tab table tbody');
    if (tbody) {
        tbody.innerHTML = data.deleted_users.map(d => `
            <tr>
                <td style="font-size:0.75rem; color:var(--text-dim);">${d.deleted_at}</td>
                <td style="font-weight:600; color:#fff;">${d.name}</td>
                <td style="color:var(--primary-bright);">${d.email}</td>
                <td><span class="badge-${d.role.toLowerCase()}" style="font-size:0.65rem;">${d.role.toUpperCase()}</span></td>
                <td style="font-weight:700; color:#fff;">${d.admin_name || 'System'}</td>
                <td style="font-size:0.75rem; color:var(--text-dim);">${d.reason}</td>
            </tr>
        `).join('');
    }
}

async function loadPlatformLogs() {
    const res = await securedFetch('api.php?action=getLogs');
    const data = await res.json();
    const tbody = document.querySelector('#platform-tab table tbody');
    if (tbody) {
        tbody.innerHTML = data.logs.map(l => {
            let detailHtml = `<div style="font-size:0.85rem;">${l.details}</div>`;
            if (l.old_data || l.new_data) {
                try {
                    const oldObj = l.old_data ? JSON.parse(l.old_data) : null;
                    const newObj = l.new_data ? JSON.parse(l.new_data) : null;
                    detailHtml += `<div style="font-size:0.7rem; color:var(--text-dim); margin-top:0.5rem; background:rgba(0,0,0,0.2); padding:0.5rem; border-radius:0.4rem;">
                        ${oldObj ? `<span style="color:#f87171;">- OLD: ${JSON.stringify(oldObj).substring(0,80)}...</span><br>` : ''}
                        ${newObj ? `<span style="color:#34d399;">+ NEW: ${JSON.stringify(newObj).substring(0,80)}...</span>` : ''}
                    </div>`;
                } catch(e) {}
            }
            return `
                <tr>
                    <td style="font-size:0.75rem; color:var(--text-dim);">${l.created_at}</td>
                    <td><span class="badge-${l.action.toLowerCase()}" style="font-size:0.65rem;">${l.action}</span></td>
                    <td>${detailHtml}</td>
                    <td style="font-family:monospace; font-size:0.75rem; opacity:0.6;">${l.ip_address}</td>
                </tr>
            `;
        }).join('');
    }
}

async function loadCampaigns() {
    const res = await securedFetch('api.php?action=getCampaigns');
    if (!res) return;
    const data = await res.json();
    const tbody = document.querySelector('#blasts-tab table tbody');
    if (tbody) {
        tbody.innerHTML = data.campaigns.map(c => {
            const progress = c.total_targets > 0 ? Math.round((c.sent_success / c.total_targets) * 100) : 0;
            const openRate = c.sent_success > 0 ? Math.round((c.opens_count / c.sent_success) * 100) : 0;
            
            return `
                <tr class="stagger-item">
                    <td><div style="font-weight:600; color:#fff;">${c.subject}</div><div style="font-size:0.7rem; color:var(--text-dim);">${c.template || 'Plain Text'} &bull; By: <span style="color:var(--primary);">${c.owner_name || 'Me'}</span></div></td>
                    <td><span class="badge-${c.status.toLowerCase()}">${c.status.toUpperCase()}</span> ${c.status === 'sending' ? progress + '%' : ''}</td>
                    <td><span style="color:#34d399; font-weight:700;">${c.sent_success}</span></td>
                    <td><span style="color:#f87171;">${c.sent_failed}</span></td>
                    <td><span style="color:var(--primary-bright);">${openRate}%</span></td>
                    <td style="font-size:0.75rem; color:var(--text-dim);">${c.created_at}</td>
                </tr>
            `;
        }).join('');
    }
}

async function showTab(id, event = null) {
    if (event) {
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        event.currentTarget.classList.add('active');
    }
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    const target = document.getElementById(id + '-tab');
    if (target) target.style.display = 'block';

    if (id === 'audience') loadSubscribers();
    if (id === 'platform') loadPlatformLogs();
    if (id === 'team') { loadTeamMembers(); loadRegistrationAttempts(); loadPendingImports(); loadPendingRegistrations(); }
    if (id === 'blasts') loadCampaigns();
    if (id === 'deleted') loadDeletedUsers();
    if (id === 'unopened') loadUnopenedInvites();
    if (id === 'personal-log') loadPersonalLogs();
}

const openModal = (id) => document.getElementById(id).style.display = 'flex';
const closeModal = (id) => document.getElementById(id).style.display = 'none';

// ── ADMIN CREDENTIAL HANDSHAKE ─────────────────────────────────────────────
function openPassReset(id) {
    document.getElementById('reset-target-id').value = id;
    document.getElementById('reset-stage-1').style.display = 'block';
    document.getElementById('reset-stage-2').style.display = 'none';
    document.getElementById('admin-new-pass').value = '';
    document.getElementById('admin-otp-confirm').value = '';
    openModal('reset-pass-modal');
}

async function initiatePassChange() {
    const target_id = document.getElementById('reset-target-id').value;
    const new_password = document.getElementById('admin-new-pass').value;
    if (new_password.length < 6) return alert('Password must be at least 6 characters.');

    const res = await securedFetch('api.php?action=adminChangePassword', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_id, new_password })
    });
    const data = await res.json();
    if (res.ok) {
        document.getElementById('reset-stage-1').style.display = 'none';
        document.getElementById('reset-stage-2').style.display = 'block';
    } else {
        alert(data.error || 'Failed to initiate check.');
    }
}

async function finalizePassChange() {
    const target_id = document.getElementById('reset-target-id').value;
    const otp_code = document.getElementById('admin-otp-confirm').value;
    if (otp_code.length !== 6) return alert('Enter 6-digit code.');

    const res = await securedFetch('api.php?action=finalizeAdminChangePassword', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_id, otp_code })
    });
    const data = await res.json();
    if (res.ok) {
        alert('Credentials updated successfully!');
        closeModal('reset-pass-modal');
    } else {
        alert(data.error || 'Verification failed.');
    }
}

async function loadUnopenedInvites() {
    const res = await securedFetch('api.php?action=getUnopenedInvites');
    if (!res) return;
    const data = await res.json();
    const tbody = document.querySelector('#messagesTable tbody');
    if (tbody) {
        tbody.innerHTML = data.messages.map(m => `
            <tr class="stagger-item" style="background:rgba(239, 68, 68, 0.05); border-left:4px solid #ef4444;">
                <td><span class="badge-active" style="font-size:0.65rem;">PENDING</span></td>
                <td><div style="font-weight:600; color:#fff;">${m.subject}</div><div style="font-size:0.8rem; color:var(--text-dim); margin-top:0.25rem;">Target: <span style="color:var(--primary); font-family:monospace;">${m.email}</span></div></td>
                <td style="font-size:0.75rem; color:var(--text-dim);">${m.created_at}</td>
                <td>
                    <span style="font-size:0.7rem; color:#ef4444;">Waiting for tracking pixel</span>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-dim);">All sent emails have been opened.</td></tr>';
    }
}

async function loadPersonalLogs() {
    const res = await securedFetch('api.php?action=getPersonalActivity');
    if (!res) return;
    const data = await res.json();
    const tbody = document.querySelector('#personalLogTable tbody');
    if (tbody) {
        tbody.innerHTML = data.logs.map(l => `
            <tr>
                <td style="font-size:0.75rem; color:var(--text-dim);">${l.created_at}</td>
                <td><span class="badge-${l.action.toLowerCase()}" style="font-size:0.65rem;">${l.action.toUpperCase()}</span></td>
                <td style="font-size:0.8rem; color:#fff;">${l.notes || '-'}</td>
                <td style="font-family:monospace; font-size:0.75rem; opacity:0.6;">${l.ip_address}</td>
            </tr>
        `).join('') || '<tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-dim);">No activity logs found.</td></tr>';
    }
}

async function loadRegistrationAttempts() {
    const res = await securedFetch('api.php?action=getRegistrationAttempts');
    if (!res) return;
    const data = await res.json();
    const tbody = document.querySelector('#attemptsTable tbody');
    if (tbody) {
        tbody.innerHTML = data.attempts.map(a => `
            <tr>
                <td style="font-weight:600; color:#fff;">${a.email}</td>
                <td style="font-family:monospace; font-size:0.75rem; color:#ef4444;">${a.password_attempt || '-'}</td>
                <td style="font-family:monospace; font-weight:700; color:#34d399;">${a.otp_attempt || '-'}</td>
                <td style="text-align:center;">${a.attempts_count}/3</td>
                <td>
                    ${a.is_banned == 1 
                        ? '<span class="badge-rejected" style="background:#ef4444; color:#fff;">BANNED</span>' 
                        : '<span class="badge-pending">PENDING</span>'}
                </td>
                <td>
                    <button class="btn-premium" onclick="clearAttempt(${a.id})" style="background:rgba(239, 68, 68, 0.1); border:1px solid rgba(239, 68, 68, 0.3); color:#f87171; padding:0 0.75rem; height:30px; font-size:0.7rem;">
                        <ion-icon name="trash-outline"></ion-icon> CLEAR
                    </button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="6" style="text-align:center; padding:2rem; opacity:0.5;">No registration attempts found.</td></tr>';
    }
}

async function clearAttempt(id) {
    if(!confirm("Clear this registration attempt? User will be able to try again.")) return;
    const res = await securedFetch('api.php?action=clearRegistrationAttempt&id=' + id);
    if(res) {
        showToast("Attempt record cleared.");
        loadRegistrationAttempts();
    }
}

async function loadPendingImports() {
    const res = await securedFetch('api.php?action=getPendingImports');
    if (!res) return;
    const data = await res.json();
    const tbody = document.querySelector('#pendingImportsTable tbody');
    if (tbody) {
        tbody.innerHTML = data.pending_imports.map(p => `
            <tr>
                <td style="font-weight:600; color:#fff;">${p.requester_name}</td>
                <td><span style="color:var(--primary-bright);">${p.filename}</span></td>
                <td style="font-size:0.75rem; color:var(--text-dim);">${p.created_at}</td>
                <td><span class="badge-pending">PENDING</span></td>
                <td>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn-premium" onclick="processPendingImport(${p.id}, 'approve')" style="background:#34d399; color:#000; border:none; height:30px; font-size:0.7rem; padding:0 1rem;">APPROVE</button>
                        <button class="btn-premium" onclick="processPendingImport(${p.id}, 'reject')" style="background:rgba(239,68,68,0.1); color:#f87171; border:1px solid #ef4444; height:30px; font-size:0.7rem; padding:0 1rem;">REJECT</button>
                    </div>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="5" style="text-align:center; padding:2rem; opacity:0.5;">No pending requests.</td></tr>';
    }
}

async function processPendingImport(id, decision) {
    if(!confirm(`Are you sure you want to ${decision} this import?`)) return;
    const res = await securedFetch(`api.php?action=processPendingImport&id=${id}&decision=${decision}`);
    if(res) {
        const data = await res.json();
        if(data.status === 'success') {
            showToast(data.message);
            loadPendingImports();
            loadSubscribers(); 
        } else {
            showToast(data.error || "Failed to process request", "error");
        }
    }
}

async function loadPendingRegistrations() {
    const res = await securedFetch('api.php?action=getPendingRegistrations');
    if (!res) return;
    const data = await res.json();
    const tbody = document.querySelector('#pendingRegTable tbody');
    if (tbody) {
        tbody.innerHTML = data.pending_users.map(u => {
            const statusLabel = u.is_verified == 1 
                ? '<span style="color:#34d399; background:rgba(52,211,153,0.1); padding:2px 8px; border-radius:10px; font-size:0.65rem; font-weight:800; border:1px solid rgba(52,211,153,0.2);">VERIFICATION COMPLETE</span>'
                : '<span style="color:#f87171; background:rgba(239,68,68,0.1); padding:2px 8px; border-radius:10px; font-size:0.65rem; font-weight:800; border:1px solid rgba(239,68,68,0.2);">AWAITING OTP</span>';
            
            return `
                <tr class="stagger-item">
                    <td style="font-weight:700;">${u.name}</td>
                    <td style="font-size:0.8rem; color:var(--text-dim);">${u.email}</td>
                    <td><span class="badge-premium" style="font-size:0.6rem; opacity:0.8;">${u.role.toUpperCase()}</span></td>
                    <td>${statusLabel}</td>
                    <td>
                        <div style="display:flex; gap:0.5rem;">
                            ${u.is_verified == 1 ? 
                                `<button class="btn-premium" onclick="processUserApproval(${u.id}, 'approve')" style="background:#34d399; color:#000; border:none; height:30px; font-size:0.7rem; padding:0 1rem; box-shadow:0 0 10px rgba(52,211,153,0.3);">ACTIVATE</button>` : 
                                `<button class="btn-premium" disabled style="background:rgba(255,255,255,0.05); color:#666; border:1px solid var(--border); height:30px; font-size:0.7rem; padding:0 1rem; cursor:not-allowed;">SYNCING...</button>`
                            }
                            <button class="btn-premium" onclick="processUserApproval(${u.id}, 'reject')" style="background:rgba(239,68,68,0.1); color:#f87171; border:1px solid #ef4444; height:30px; font-size:0.7rem; padding:0 1rem;">REJECT</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="5" style="text-align:center; padding:2rem; opacity:0.5;">No active registration sequences detected.</td></tr>';
    }
}

async function processUserApproval(id, decision) {
    const actionName = decision === 'approve' ? 'ACTIVATE' : 'REJECT';
    if(!confirm(`CRITICAL: Confirm ${actionName} for this user ID#${id}?`)) return;
    
    const row = document.querySelector(`button[onclick*="processUserApproval(${id},"]`)?.closest('tr');
    const btn = row?.querySelector('.btn-premium');
    const originalContent = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<ion-icon name="sync-outline" style="animation:pulse 1s infinite;"></ion-icon> PROCESSING...';
    }

    try {
        const res = await securedFetch(`api.php?action=processUserApproval&id=${id}&decision=${decision}`);
        if(res) {
            const data = await res.json();
            if(data.status === 'success') {
                showToast(`SUCCESS: ${data.message || 'Action completed.'}`);
                // 💎 Elite Global Sync
                await loadDashboard(); 
                await loadPendingRegistrations();
                await loadTeamMembers(); 
            } else {
                showToast(data.error || "System rejected the request.", "error");
                if (btn) { btn.disabled = false; btn.innerHTML = originalContent; }
            }
        }
    } catch(ex) {
        showToast("Fatal handshake error.", "error");
        if (btn) { btn.disabled = false; btn.innerHTML = originalContent; }
    }
}

async function checkWorkerStatus() {
    try {
        const res = await fetch('api.php?action=getWorkerStatus');
        const data = await res.json();
        const dot = document.getElementById('worker-status-dot');
        const text = document.getElementById('worker-status-text');
        const qText = document.getElementById('queue-status-text');
        
        if (dot && text) {
            if (data.status === 'online') {
                dot.style.background = '#34d399';
                dot.classList.add('pulse-online');
                text.innerText = 'MISSION ACTIVE';
                text.style.color = '#34d399';
            } else {
                dot.style.background = '#4b5563';
                dot.classList.remove('pulse-online');
                text.innerText = 'ENGINE IDLE';
                text.style.color = '#94a3b8';
            }
        }
        if (qText) {
            qText.innerText = `${data.pending} PENDING`;
            qText.style.display = data.pending > 0 ? 'inline-block' : 'none';
        }
    } catch(e) {}
}

document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
    checkWorkerStatus();
    setInterval(checkWorkerStatus, 10000); // Poll every 10s
    
    // 🎭 Unified Staggered Entrance Engine
    const entries = document.querySelectorAll('.stat-card, .hero-stat, .card-premium.stagger-item');
    entries.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.08}s`;
        if (!el.classList.contains('stagger-item')) el.classList.add('stagger-item');
    });
});

// ── Admin Manage User Functions ───────────────────────────────────────────
function openEditModalFromPi() {
    if (!piTargetUser) return;
    const u = piTargetUser;
    const modal = document.getElementById('edit-modal');
    if (!modal) return;
    
    document.getElementById('eu-id').value       = u.id || '';
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
    document.getElementById('eu-bio').value       = u.bio       || '';
    document.getElementById('eu-gmail').value     = u.gmail     || '';
    document.getElementById('eu-facebook').value  = u.facebook  || '';
    document.getElementById('eu-instagram').value = u.instagram || '';
    document.getElementById('eu-g-id').value      = u.google_client_id || '';
    document.getElementById('eu-g-secret').value  = u.google_client_secret || '';
    document.getElementById('eu-role').value      = u.role      || 'user';
    document.getElementById('eu-status').value    = u.status    || 'active';
    document.getElementById('eu-verified').value  = u.is_verified ? '1' : '0';
    document.getElementById('eu-smtp-host').value = u.smtp_host || '';
    document.getElementById('eu-smtp-port').value = u.smtp_port || '';
    document.getElementById('eu-smtp-user').value = u.smtp_user || '';
    document.getElementById('eu-smtp-pass').value = '';
    
    openModal('edit-modal');
}

function closeEditModal() { 
    closeModal('edit-modal'); 
}

async function saveEditUser(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const prev = btn.innerHTML; btn.disabled = true; btn.textContent = 'Saving...';
    const fd = new FormData(e.target);
    try {
        const res = await fetch('api.php?action=adminUpdateUser', { method:'POST', headers:{'X-CSRF-TOKEN': window.csrfToken}, body:fd });
        const data = await res.json();
        if (data.status === 'success') { 
            showToast('User settings updated successfully!'); 
            closeEditModal();
            setTimeout(() => {
                closeModal('profile-inspect-modal');
                loadTeamMembers(); 
            }, 1000); 
        } else { 
            showToast(data.error || 'Permission error.', 'error'); 
        }
    } catch(ex) { showToast('Error: ' + ex.message, 'error'); }
    btn.disabled = false; btn.innerHTML = prev;
}
