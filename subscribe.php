<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to Our Newsletter</title>
    <link rel="stylesheet" href="styles.css">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <style>
        /* Specific overrides for the public landing page */
        body {
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .subscribe-container {
            width: 100%;
            max-width: 500px;
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            backdrop-filter: var(--glass-blur);
            padding: 3rem 2rem;
            border-radius: 1.5rem;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .subscribe-icon {
            font-size: 4rem;
            color: var(--accent-primary);
            margin-bottom: 1rem;
            display: inline-block;
        }
        .subscribe-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .subscribe-desc {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 1rem;
            line-height: 1.6;
        }
        .form-group {
            text-align: left;
        }
        .form-control {
            margin-bottom: 0.5rem;
        }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            margin-top: 1rem;
            justify-content: center;
        }
        .success-state {
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            padding: 2rem 0;
        }
        .success-state ion-icon {
            font-size: 5rem;
            color: var(--success);
        }
    </style>
</head>
<body>
    <!-- Background Effect -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2" style="background: var(--accent-primary); width: 600px; height: 600px; opacity: 0.3;"></div>

    <div class="subscribe-container" id="main-container">
        
        <!-- Subscription Form Section -->
        <div id="form-section">
            <ion-icon name="mail-open-outline" class="subscribe-icon"></ion-icon>
            <h1 class="subscribe-title">Join Our Community!</h1>
            <p class="subscribe-desc">
                Be the first to know about updates, exclusive promos, and exciting news straight to your inbox. No spam, we promise!
            </p>

            <form id="public-subscribe-form">
                <div class="form-group">
                    <label>What's your full name?</label>
                    <input type="text" id="sub-name" class="form-control" placeholder="Juan Dela Cruz" required>
                </div>
                <div class="form-group">
                    <label>Where should we send the updates?</label>
                    <input type="email" id="sub-email" class="form-control" placeholder="juan@example.com" required>
                </div>
                <button type="submit" class="btn btn-primary btn-submit">
                    Subscribe Now
                </button>
            </form>
        </div>

        <!-- Success Message Section -->
        <div id="success-section" class="success-state">
            <ion-icon name="checkmark-circle"></ion-icon>
            <h2 style="font-size: 1.8rem; font-weight: 600;">You're on the list!</h2>
            <p style="color: var(--text-muted);">
                Salamat sa pag-sign up! Bantayan mo ang Inbox mo para sa aming susunod na email updates.
            </p>
            <button class="btn btn-primary" onclick="location.reload()" style="margin-top: 1.5rem;">
                Back
            </button>
        </div>

    </div>

    <!-- Toast Notifications (Imported from styles.css) -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        document.getElementById('public-subscribe-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('sub-name').value;
            const email = document.getElementById('sub-email').value;
            const btn = document.querySelector('.btn-submit');

            // Loading state
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Subscribing...';
            btn.disabled = true;

            try {
                // Post to the exact same backend API created earlier
                const res = await fetch('api.php?action=addSubscriber', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, email })
                });
                const data = await res.json();

                if (res.ok) {
                    // Hide form, show success message
                    document.getElementById('form-section').style.display = 'none';
                    document.getElementById('success-section').style.display = 'flex';
                } else {
                    showToast(data.error || 'Failed to subscribe', 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (err) {
                showToast('Network Error', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });

        // Simplified toast function specifically for this page
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? 'checkmark-circle' : 'alert-circle';
            toast.innerHTML = `<ion-icon name="${icon}" style="font-size: 1.5rem; color: var(--${type})"></ion-icon><div>${message}</div>`;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }
    </script>
</body>
</html>
