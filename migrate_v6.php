<?php
require_once 'db.php';

$sql = "CREATE TABLE IF NOT EXISTS pending_imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    filename VARCHAR(255),
    csv_data LONGTEXT, -- Stores json_encoded array of contacts
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "Table 'pending_imports' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
