/* 🚀 BLASTFORGE SYNC ENGINE v4.1 (Elite Restoration) */

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
        'stat-users': data.total_users
    };

    Object.entries(syncMap).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el) el.innerText = val !== undefined ? val : '--';
    });

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
    btn.innerText = 'ENROLLING MEMBER...';
    
    const fd = new FormData(e.target);
    const res = await securedFetch('api.php?action=addUser', {
        method: 'POST',
        body: fd
    });
    
    const data = await res.json();
    if (data.status === 'success') {
        alert(data.message);
        closeModal('onboard-modal');
        e.target.reset();
        loadDashboard();
    } else {
        alert(data.error || 'Enrollment Failed');
    }
    btn.innerHTML = originalText;
}

async function sendBlast(e) {
    if (e) e.preventDefault();
    const btn = e.currentTarget || e.target;
    const originalText = btn.innerHTML;
    btn.innerText = 'DEPLOYING...';
    
    const fd = new FormData();
    fd.append('subject', document.getElementById('camp-subject').value);
    fd.append('template', document.getElementById('camp-template').value);
    fd.append('content', document.getElementById('camp-content').value);
    
    const res = await securedFetch('api.php?action=sendBlast', {
        method: 'POST',
        body: fd
    });
    
    const data = await res.json();
    if (data.status === 'success') {
        alert(`Distributed ${data.sent} payloads successfully.`);
        closeModal('campaign-modal');
        loadDashboard();
    } else {
        alert(data.error || 'Uplink Interrupted');
    }
    btn.innerHTML = originalText;
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
}

const openModal = (id) => document.getElementById(id).style.display = 'flex';
const closeModal = (id) => document.getElementById(id).style.display = 'none';

document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
    document.querySelectorAll('.stat-card').forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
        el.classList.add('stagger-item');
    });
});
