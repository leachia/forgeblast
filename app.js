/* 🚀 BLASTFORGE SYNC ENGINE v4.1 (Clean Architecture) */

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
        if (!res.ok) throw new Error("Network Response Fail");
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
        'stat-clicks': data.total_clicks
    };

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

    renderChart(data.chart_labels, data.chart_data);
}

function renderChart(labels, data) {
    const canvas = document.getElementById('mainChart');
    if (!canvas) return;
    if (window.performanceChart) window.performanceChart.destroy();
    window.performanceChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Global Activity',
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

async function loadTeamMembers() {
    const res = await securedFetch('api.php?action=getUsers');
    if (!res) return;
    const data = await res.json();
    const tbody = document.querySelector('#team-tab table tbody');
    if (tbody) {
        tbody.innerHTML = data.users.map(u => `
            <tr class="stagger-item">
                <td style="text-align:center;">
                    <div style="width:10px; height:10px; border-radius:50%; background:${u.is_online ? '#34d399' : '#475569'}; box-shadow:${u.is_online ? '0 0 10px rgba(52,211,153,0.5)' : 'none'}; display:inline-block;" title="${u.is_online ? 'Active Now' : 'Last seen: ' + (u.last_activity || 'Never')}"></div>
                </td>
                <td style="font-weight:600; color:#fff;">${u.name}</td>
                <td style="color:var(--primary-bright);">${u.email}</td>
                <td><span class="badge-${u.role.toLowerCase()}">${u.role.toUpperCase()}</span></td>
                <td style="font-size:0.75rem; color:var(--text-dim);">${u.created_at}</td>
                <td style="text-align:right; display:flex; gap:0.5rem; justify-content:flex-end;">
                    ${window.role === 'super_admin' ? `<button onclick="openPassReset(${u.id})" style="background:rgba(59,130,246,0.1); border:none; color:var(--primary); width:30px; height:30px; border-radius:0.4rem; cursor:pointer;" title="Credential Vault"><ion-icon name="key-outline" style="vertical-align:middle;"></ion-icon></button>` : ''}
                    <button onclick="deleteUser(${u.id})" style="background:rgba(239,68,68,0.1); border:none; color:#f87171; width:30px; height:30px; border-radius:0.4rem; cursor:pointer;" title="Delete Member"><ion-icon name="trash-outline" style="vertical-align:middle;"></ion-icon></button>
                </td>
            </tr>
        `).join('');
    }
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
                    <td><div style="font-weight:600; color:#fff;">${c.subject}</div><div style="font-size:0.7rem; color:var(--text-dim);">${c.template || 'Plain Text'}</div></td>
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
    if (id === 'team') loadTeamMembers();
    if (id === 'blasts') loadCampaigns();
    if (id === 'deleted') loadDeletedUsers();
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

document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
    document.querySelectorAll('.stat-card').forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
        el.classList.add('stagger-item');
    });
});
