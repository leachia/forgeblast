const API_URL = 'api.php';

// Initialization
document.addEventListener('DOMContentLoaded', () => {
    loadData();
    
    // Bind forms
    document.getElementById('subscriber-form').addEventListener('submit', handleAddSubscriber);
    document.getElementById('campaign-form').addEventListener('submit', handleSendCampaign);
});

// UI Navigation
function showTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    // Remove active class from navs
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    
    // Show selected
    document.getElementById(tabId + '-tab').style.display = 'block';
    
    // Set active nav
    const navItems = document.querySelectorAll('.nav-item');
    if(tabId === 'dashboard') navItems[0].classList.add('active');
    if(tabId === 'subscribers') navItems[1].classList.add('active');
    if(tabId === 'campaigns') navItems[2].classList.add('active');

    // Update title
    document.getElementById('page-title').innerText = tabId.charAt(0).toUpperCase() + tabId.slice(1);
    
    // Reload data contextually
    loadData();
}

// Modal Handling
function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    // Reset forms inside
    const form = document.querySelector(`#${id} form`);
    if(form) form.reset();
}

// Data Fetching
async function loadData() {
    fetchStats();
    fetchSubscribers();
    fetchCampaigns();
}

async function fetchStats() {
    try {
        const res = await fetch(`${API_URL}?action=getStats`);
        const data = await res.json();
        document.getElementById('stat-subscribers').innerText = data.total_subscribers || 0;
        document.getElementById('stat-campaigns').innerText = data.total_campaigns || 0;
        document.getElementById('stat-emails').innerText = data.total_emails_sent || 0;
    } catch (err) {
        console.error("Failed to load stats", err);
    }
}

async function fetchSubscribers() {
    try {
        const res = await fetch(`${API_URL}?action=getSubscribers`);
        const data = await res.json();
        const tbody = document.querySelector('#subscribers-table tbody');
        tbody.innerHTML = '';
        
        if (!data.subscribers || data.subscribers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color: var(--text-muted)">No subscribers yet.</td></tr>`;
            return;
        }

        if (window.currentUserRole !== 'user') {
            document.getElementById('sub-owner-th').style.display = 'table-cell';
        }

        data.subscribers.forEach(sub => {
            const statusBadge = sub.status === 'active' ? '<span class="badge badge-active">Active</span>' : '<span class="badge" style="background:var(--danger)">Unsubscribed</span>';
            const ownerTd = window.currentUserRole !== 'user' ? `<td><span style="color:var(--text-muted); font-size:0.85rem;"><ion-icon name="person-outline"></ion-icon> ${sub.owner_name || 'System'}</span></td>` : '';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                ${ownerTd}
                <td>${sub.name}</td>
                <td>${sub.email}</td>
                <td>${statusBadge}</td>
                <td>${new Date(sub.created_at).toLocaleDateString()}</td>
                <td>
                    <button class="btn btn-danger" onclick="deleteSubscriber(${sub.id})">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (err) {
        console.error("Failed to load subscribers", err);
    }
}

async function fetchCampaigns() {
    try {
        const res = await fetch(`${API_URL}?action=getCampaigns`);
        const data = await res.json();
        const tbody = document.querySelector('#campaigns-table tbody');
        tbody.innerHTML = '';
        
        if (!data.campaigns || data.campaigns.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color: var(--text-muted)">No campaigns sent yet.</td></tr>`;
            return;
        }

        if (window.currentUserRole !== 'user') {
            document.getElementById('camp-sender-th').style.display = 'table-cell';
        }

        data.campaigns.forEach(camp => {
            const ownerTd = window.currentUserRole !== 'user' ? `<td><div style="font-weight:500;">${camp.sender_name || 'System'}</div><div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase;">${camp.sender_role ? camp.sender_role.replace('_', ' ') : ''}</div></td>` : '';
            
            let badgeStyle = '';
            if (camp.status === 'pending') badgeStyle = 'background: rgba(245, 158, 11, 0.1); color: #f59e0b;';
            else if (camp.status === 'rejected') badgeStyle = 'background: rgba(239, 68, 68, 0.1); color: var(--danger);';
            else if (camp.status === 'sent') badgeStyle = 'background: rgba(59, 130, 246, 0.1); color: var(--accent-secondary);';
            else badgeStyle = 'background: rgba(255, 255, 255, 0.1); color: var(--text-muted);';

            const statusBadge = `<span class="badge" style="${badgeStyle}">${camp.status}</span>`;
            const canApprove = (window.currentUserRole === 'super_admin' && camp.status === 'pending');

            let attachmentsHtml = '';
            if (camp.attachments) {
                try {
                    const atts = JSON.parse(camp.attachments);
                    if (atts && atts.length > 0) {
                        attachmentsHtml = `<div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.25rem;">`;
                        atts.forEach(a => {
                            attachmentsHtml += `<a href="${a.path}" target="_blank" class="badge" style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: var(--accent-secondary); text-decoration: none; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 0.25rem;"><ion-icon name="document-attach-outline"></ion-icon> ${a.original_name}</a>`;
                        });
                        attachmentsHtml += `</div>`;
                    }
                } catch(e) {}
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                ${ownerTd}
                <td>
                    <strong style="display:block; font-size:1.05rem;">${camp.subject}</strong>
                    ${attachmentsHtml}
                </td>
                <td>${camp.sent_count}</td>
                <td>${statusBadge}</td>
                <td>${new Date(camp.created_at).toLocaleString()}</td>
                <td style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-primary" onclick="window.open('view.php?id=${camp.id}', '_blank')" title="View Public Page" style="padding: 0.25rem 0.5rem;">
                        <ion-icon name="link-outline"></ion-icon>
                    </button>
                    ${canApprove ? `
                    <button class="btn" style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 0.25rem 0.5rem;" onclick="approveCampaign(${camp.id})" title="Approve & Send">
                        <ion-icon name="checkmark-outline"></ion-icon>
                    </button>
                    <button class="btn" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 0.25rem 0.5rem;" onclick="rejectCampaign(${camp.id})" title="Reject">
                        <ion-icon name="close-outline"></ion-icon>
                    </button>
                    ` : ''}
                    <button class="btn btn-danger" onclick="deleteCampaign(${camp.id})" title="Delete History" style="padding: 0.25rem 0.5rem;">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (err) {
        console.error("Failed to load campaigns", err);
    }
}

// Form Handlers
async function handleAddSubscriber(e) {
    e.preventDefault();
    const name = document.getElementById('sub-name').value;
    const email = document.getElementById('sub-email').value;

    try {
        const res = await fetch(`${API_URL}?action=addSubscriber`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email })
        });
        const data = await res.json();
        
        if (res.ok) {
            showToast(data.message, 'success');
            closeModal('subscriber-modal');
            loadData();
        } else {
            showToast(data.error || 'Operation failed', 'error');
        }
    } catch (err) {
        showToast('Network error', 'error');
    }
}

async function handleSendCampaign(e) {
    e.preventDefault();
    const subject = document.getElementById('camp-subject').value;
    const content = document.getElementById('camp-content').value;
    const attachmentInput = document.getElementById('camp-attachments');
    const submitBtn = document.querySelector('#campaign-form button[type="submit"]');

    // Disable button and show loading
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<ion-icon name="hourglass-outline" style="animation: spin 2s linear infinite;"></ion-icon> Sending...';
    submitBtn.disabled = true;
    submitBtn.style.opacity = '0.7';

    // Build FormData to send files along with text
    const formData = new FormData();
    formData.append('subject', subject);
    formData.append('content', content);
    
    // Append each selected file
    if (attachmentInput.files.length > 0) {
        for (let i = 0; i < attachmentInput.files.length; i++) {
            formData.append('attachments[]', attachmentInput.files[i]);
        }
    }

    try {
        const res = await fetch(`${API_URL}?action=sendCampaign`, {
            method: 'POST',
            body: formData // No Headers content-type since fetch handles multipart automatically
        });
        const data = await res.json();
        
        if (res.ok) {
            showToast(data.message, 'success');
            closeModal('campaign-modal');
            loadData();
        } else {
            showToast(data.error || 'Operation failed', 'error');
        }
    } catch (err) {
        showToast('Network error', 'error');
    } finally {
        // Re-enable button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
    }
}

async function deleteSubscriber(id) {
    if(!confirm("Remove this subscriber?")) return;
    
    try {
        const res = await fetch(`${API_URL}?action=deleteSubscriber&id=${id}`, {
            method: 'DELETE'
        });
        if (res.ok) {
            showToast('Subscriber removed', 'success');
            loadData();
        } else {
            showToast('Failed to remove', 'error');
        }
    } catch (err) {
        showToast('Network error', 'error');
    }
}

// Toast Notifications UI
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'checkmark-circle' : 'alert-circle';
    
    toast.innerHTML = `
        <ion-icon name="${icon}" style="font-size: 1.5rem; color: var(--${type})"></ion-icon>
        <div>${message}</div>
    `;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Remove after 3s
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400); // Wait for transition
    }, 3000);
}

async function deleteCampaign(id) {
    if(!confirm("Remove this campaign from history? (Note: This will not unsend emails that are already delivered)")) return;
    
    try {
        const res = await fetch(`${API_URL}?action=deleteCampaign&id=${id}`, {
            method: 'DELETE'
        });
        if (res.ok) {
            showToast('Campaign history deleted', 'success');
            loadData();
        } else {
            showToast('Failed to delete history', 'error');
        }
    } catch (err) {
        showToast('Network error', 'error');
    }
}

async function approveCampaign(id) {
    if(!confirm("Approve this campaign? It will be sent immediately to all active subscribers of the creator.")) return;
    try {
        const res = await fetch(`${API_URL}?action=approveCampaign`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (res.ok) {
            showToast(data.message, 'success');
            loadData();
        } else {
            showToast(data.error || 'Approval failed', 'error');
        }
    } catch (err) { showToast('Network error', 'error'); }
}

async function rejectCampaign(id) {
    if(!confirm("Reject this campaign?")) return;
    try {
        const res = await fetch(`${API_URL}?action=rejectCampaign`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (res.ok) {
            showToast('Campaign rejected', 'success');
            loadData();
        } else {
            showToast(data.error || 'Rejection failed', 'error');
        }
    } catch (err) { showToast('Network error', 'error'); }
}

// ==========================================
// Admin Panel Functions
// ==========================================
// User Management has been moved to profile.php (System Profiles) to keep dashboard clean.
