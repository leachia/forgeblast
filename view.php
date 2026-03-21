<?php
require_once 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign = null;

if ($id > 0) {
    $res = $conn->query("SELECT * FROM campaigns WHERE id = $id");
    if ($res->num_rows > 0) {
        $campaign = $res->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement: <?php echo $campaign ? htmlspecialchars($campaign['subject']) : 'Not Found'; ?></title>
    <link rel="stylesheet" href="styles.css">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 3rem 1.5rem;
            min-height: 100vh;
        }
        .campaign-container {
            width: 100%;
            max-width: 800px;
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            backdrop-filter: var(--glass-blur);
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            animation: fadeIn 0.8s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .campaign-header {
            padding: 3rem 2.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid var(--panel-border);
        }
        .campaign-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .campaign-date {
            color: var(--text-muted);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .campaign-body {
            padding: 3rem 2.5rem;
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-main);
            background: rgba(0, 0, 0, 0.2);
        }
        /* Style standard HTML elements inside the body since it's injected */
        .campaign-body h1, .campaign-body h2, .campaign-body h3 {
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            color: white;
        }
        .campaign-body p { margin-bottom: 1rem; }
        .campaign-body a { color: var(--accent-secondary); text-decoration: none; }
        .campaign-body a:hover { text-decoration: underline; }
        .campaign-body img { max-width: 100%; height: auto; border-radius: 0.5rem; margin: 1rem 0; }
        
        .cta-section {
            padding: 2.5rem;
            text-align: center;
            background: rgba(139, 92, 246, 0.1);
            border-top: 1px solid var(--panel-border);
        }
        .cta-section h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: white;
        }
        .cta-section p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
        .error-state {
            text-align: center;
            padding: 5rem 2rem;
        }
        .error-state ion-icon {
            font-size: 5rem;
            color: var(--danger);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="campaign-container">
        <?php if ($campaign): ?>
            <div class="campaign-header">
                <h1><?php echo stripslashes($campaign['subject']); ?></h1>
                <div class="campaign-date">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Published on: <?php echo date("F j, Y, g:i a", strtotime($campaign['created_at'])); ?>
                </div>
            </div>
            
            <div class="campaign-body">
                <!-- Output the raw HTML of the email blast -->
                <?php echo stripslashes($campaign['content']); ?>
            </div>

            <?php
            $attachments = json_decode($campaign['attachments'] ?? '[]', true) ?? [];
            if (!empty($attachments)):
            ?>
            <div style="padding: 1.5rem 2.5rem; background: rgba(0,0,0,0.3); border-top: 1px solid var(--panel-border);">
                <h4 style="color: var(--text-muted); margin-bottom: 0.75rem; font-size: 0.85rem; text-transform: uppercase;">Attached Files:</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <?php foreach ($attachments as $att): ?>
                    <a href="<?php echo htmlspecialchars($att['path']); ?>" target="_blank" class="badge" style="background: rgba(255,255,255,0.1); color: var(--accent-secondary); text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; display: flex; align-items: center; gap: 0.5rem; transition: background 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                        <ion-icon name="document-attach-outline"></ion-icon> <?php echo htmlspecialchars($att['original_name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="cta-section">
                <h3>Want updates like this?</h3>
                <p>Join our exclusive mailing list today and never miss an announcement.</p>
                <a href="subscribe.php" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem; text-decoration: none;">
                    <ion-icon name="mail-open-outline"></ion-icon> Subscribe Now
                </a>
            </div>
        <?php else: ?>
            <div class="error-state">
                <ion-icon name="close-circle-outline"></ion-icon>
                <h2>Announcement Not Found</h2>
                <p style="color: var(--text-muted); margin-top: 1rem;">The announcement you are looking for might have been deleted or does not exist.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
