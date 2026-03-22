<?php
require_once 'config.php';

// Upgrade all existing accounts to 250,000 monthly limit
$sql = "UPDATE users SET monthly_limit = 250000";
if ($conn->query($sql)) {
    echo "<h1>🚀 Quota Upgrade Success!</h1>";
    echo "<p>All users have been upgraded to 250,000 emails per month.</p>";
    echo "<a href='index.php'>Return to Dashboard</a>";
} else {
    echo "<h1>❌ Error during upgrade</h1>";
    echo $conn->error;
}
?>
