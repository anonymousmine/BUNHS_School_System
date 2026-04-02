<?php
// Create missing admin dashboard tables
require_once __DIR__ . '/db_connection.php';

echo "Creating missing admin dashboard tables...\n";

// Create finance_records table
$sql = "CREATE TABLE IF NOT EXISTS finance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description TEXT DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    date_added DATE DEFAULT CURRENT_TIMESTAMP,
    added_by VARCHAR(100) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "✅ finance_records table created/exists\n";
} else {
    echo "❌ Error creating finance_records table: " . $conn->error . "\n";
}

// Create chat_messages table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id VARCHAR(50) NOT NULL,
    sender_role ENUM('admin', 'sub-admin', 'student', 'teacher') NOT NULL,
    receiver_id VARCHAR(50) DEFAULT NULL,
    receiver_role ENUM('admin', 'sub-admin', 'student', 'teacher') DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "✅ chat_messages table created/exists\n";
} else {
    echo "❌ Error creating chat_messages table: " . $conn->error . "\n";
}

// Insert some sample finance data if table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM finance_records");
if ($result && $result->fetch_assoc()['count'] == 0) {
    $sample_data = [
        [1500.00, 'School Supplies Purchase', 'supplies', '2026-01-15', 'admin'],
        [2500.00, 'Laboratory Equipment', 'equipment', '2026-02-20', 'admin'],
        [800.00, 'Office Supplies', 'office', '2026-03-10', 'admin'],
        [3200.00, 'Computer Maintenance', 'maintenance', '2026-04-01', 'admin']
    ];
    
    $stmt = $conn->prepare("INSERT INTO finance_records (amount, description, category, date_added, added_by) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($sample_data as $data) {
        $stmt->bind_param('dssss', $data[0], $data[1], $data[2], $data[3], $data[4]);
        $stmt->execute();
    }
    $stmt->close();
    
    echo "✅ Sample finance data inserted\n";
}

echo "\nDashboard tables setup complete!\n";
$conn->close();
?>
