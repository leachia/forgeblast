<?php
require_once 'config.php';
$res = $conn->query("SELECT status, sent_success, sent_failed FROM campaigns WHERE id = 4");
echo json_encode($res->fetch_assoc());
echo "\nQUEUE COUNT: " . $conn->query("SELECT COUNT(*) FROM email_queue WHERE campaign_id = 4 AND status != 'pending'")->fetch_row()[0];
echo "\nPENDING COUNT: " . $conn->query("SELECT COUNT(*) FROM email_queue WHERE campaign_id = 4 AND status = 'pending'")->fetch_row()[0];
