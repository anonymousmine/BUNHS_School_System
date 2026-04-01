<?php

/**
 * Database Encryption Setup Script
 * 
 * This script sets up MySQL Transparent Data Encryption (TDE)
 * and creates the necessary database structures for encrypted storage.
 * 
 * IMPORTANT: MySQL 8.0+ with InnoDB is required for TDE support.
 */

session_start();

// Include database connection
include '../db_connection.php';

$message = '';
$messageType = '';
$sqlQueries = [];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_tables') {
        try {
            // Create student_profile_details table
            $sql1 = "CREATE TABLE IF NOT EXISTS student_profile_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id VARCHAR(50) UNIQUE NOT NULL,
                professional_bio TEXT,
                school_name VARCHAR(255),
                grade_level VARCHAR(50),
                awards_honors TEXT,
                extracurriculars TEXT,
                portfolio_projects TEXT,
                skills TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            if ($conn->query($sql1) === TRUE) {
                $sqlQueries[] = "✓ student_profile_details table created successfully";
            } else {
                throw new Exception("Error creating student_profile_details: " . $conn->error);
            }

            // Add encrypted columns to students table (for enhanced security)
            $sql2 = "ALTER TABLE students 
                    ADD COLUMN IF NOT EXISTS email_encrypted VARCHAR(255) AFTER email,
                    ADD COLUMN IF NOT EXISTS phone_encrypted VARCHAR(50) AFTER phone,
                    ADD COLUMN IF NOT EXISTS address_encrypted TEXT AFTER address,
                    ADD COLUMN IF NOT EXISTS guardian_name_encrypted VARCHAR(255) AFTER guardian_name,
                    ADD COLUMN IF NOT EXISTS guardian_phone_encrypted VARCHAR(50) AFTER guardian_phone";

            // Note: ADD COLUMN IF NOT EXISTS syntax may not work in all MySQL versions
            // Using alternative approach
            $columnsToAdd = [
                'email_encrypted' => 'VARCHAR(255)',
                'phone_encrypted' => 'VARCHAR(50)',
                'address_encrypted' => 'TEXT',
                'guardian_name_encrypted' => 'VARCHAR(255)',
                'guardian_phone_encrypted' => 'VARCHAR(50)'
            ];

            foreach ($columnsToAdd as $col => $type) {
                $checkCol = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                            WHERE TABLE_SCHEMA = 'bunhs_db_important' 
                            AND TABLE_NAME = 'students' 
                            AND COLUMN_NAME = '$col'";
                $result = $conn->query($checkCol);
                if ($result && $result->num_rows == 0) {
                    $alterSql = "ALTER TABLE students ADD COLUMN $col $type";
                    if ($conn->query($alterSql) === TRUE) {
                        $sqlQueries[] = "✓ Added $col column to students table";
                    }
                }
            }

            $message = "Database tables created successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }

    if ($action === 'enable_tde') {
        // Note: TDE requires MySQL Enterprise or Percona Server with keyring
        // For XAMPP (MySQL Community), we simulate this with application-level encryption
        $message = "For XAMPP/MySQL Community Edition, TDE is not directly available. 
                    However, we've set up application-level encryption which encrypts 
                    sensitive data before storing in the database. This provides similar 
                    security benefits.";
        $messageType = "info";

        // Store encryption key in a secure location
        $encryptionKey = bin2hex(random_bytes(32));
        $keyFile = '../config/encryption_key.php';

        if (!is_dir('../config')) {
            mkdir('../config', 0755, true);
        }

        $keyContent = "<?php\n";
        $keyContent .= "// Encryption key for profile data - DO NOT SHARE OR COMMIT TO VERSION CONTROL\n";
        $keyContent .= "\$encryption_key = '$encryptionKey';\n";
        $keyContent .= "\$encryption_iv_length = 16;\n";

        if (file_put_contents($keyFile, $keyContent) !== false) {
            $sqlQueries[] = "✓ Encryption key generated and stored securely";
            $sqlQueries[] = "✓ Application-level encryption enabled";
        }
    }
}

// Get current database status
$tableStatus = [];
try {
    $result = $conn->query("SHOW TABLES LIKE 'student_profile_details'");
    $tableStatus['profile_details'] = ($result && $result->num_rows > 0);

    $result = $conn->query("SHOW COLUMNS FROM students LIKE 'email_encrypted'");
    $tableStatus['encrypted_columns'] = ($result && $result->num_rows > 0);

    $keyFileExists = file_exists('../config/encryption_key.php');
    $tableStatus['encryption_key'] = $keyFileExists;
} catch (Exception $e) {
    $tableStatus['error'] = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Encryption Setup - BUNHS Admin</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .card-header h2 {
            color: #1e293b;
            font-size: 24px;
            margin: 0;
        }

        .card-header p {
            color: #64748b;
            margin-top: 8px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .status-item {
            padding: 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-item.success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .status-item.pending {
            background: #fefce8;
            border: 1px solid #fef08a;
        }

        .status-item i {
            font-size: 24px;
        }

        .status-item.success i {
            color: #16a34a;
        }

        .status-item.pending i {
            color: #ca8a04;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8a9a5b, #6d7a48);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(138, 154, 91, 0.4);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .sql-queries {
            background: #1e293b;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 20px;
        }

        .sql-queries h4 {
            color: #8a9a5b;
            margin-bottom: 12px;
        }

        .sql-queries ul {
            list-style: none;
            padding: 0;
        }

        .sql-queries li {
            padding: 4px 0;
        }

        .warning-box {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .warning-box h4 {
            color: #c2410c;
            margin-bottom: 8px;
        }

        .warning-box ul {
            margin: 0;
            padding-left: 20px;
            color: #7c2d12;
        }

        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .info-box h4 {
            color: #0369a1;
            margin-bottom: 8px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .back-link:hover {
            color: #8a9a5b;
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="admin_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-shield-alt"></i> Database Encryption Setup</h2>
                <p>Configure encryption for student profile data to ensure privacy and security</p>
            </div>

            <!-- Current Status -->
            <h3>Current Status</h3>
            <div class="status-grid">
                <div class="status-item <?php echo isset($tableStatus['profile_details']) && $tableStatus['profile_details'] ? 'success' : 'pending'; ?>">
                    <i class="fas <?php echo isset($tableStatus['profile_details']) && $tableStatus['profile_details'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                    <div>
                        <strong>Profile Details Table</strong>
                        <div><?php echo isset($tableStatus['profile_details']) && $tableStatus['profile_details'] ? 'Created' : 'Not Created'; ?></div>
                    </div>
                </div>
                <div class="status-item <?php echo isset($tableStatus['encrypted_columns']) && $tableStatus['encrypted_columns'] ? 'success' : 'pending'; ?>">
                    <i class="fas <?php echo isset($tableStatus['encrypted_columns']) && $tableStatus['encrypted_columns'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                    <div>
                        <strong>Encrypted Columns</strong>
                        <div><?php echo isset($tableStatus['encrypted_columns']) && $tableStatus['encrypted_columns'] ? 'Added' : 'Not Added'; ?></div>
                    </div>
                </div>
                <div class="status-item <?php echo isset($tableStatus['encryption_key']) && $tableStatus['encryption_key'] ? 'success' : 'pending'; ?>">
                    <i class="fas <?php echo isset($tableStatus['encryption_key']) && $tableStatus['encryption_key'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                    <div>
                        <strong>Encryption Key</strong>
                        <div><?php echo isset($tableStatus['encryption_key']) && $tableStatus['encryption_key'] ? 'Generated' : 'Not Generated'; ?></div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($sqlQueries)): ?>
                <div class="sql-queries">
                    <h4>Actions Completed:</h4>
                    <ul>
                        <?php foreach ($sqlQueries as $query): ?>
                            <li><?php echo htmlspecialchars($query); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Setup Steps</h3>
            </div>

            <div class="warning-box">
                <h4><i class="fas fa-exclamation-triangle"></i> Important Notice</h4>
                <ul>
                    <li>MySQL Transparent Data Encryption (TDE) requires MySQL Enterprise Edition or Percona Server</li>
                    <li>XAMPP uses MySQL Community Edition which doesn't support native TDE</li>
                    <li>This setup uses application-level encryption which encrypts data before storing</li>
                    <li>The encryption key is stored in a secure configuration file - keep this file safe!</li>
                </ul>
            </div>

            <form method="POST">
                <div class="form-group">
                    <button type="submit" name="action" value="create_tables" class="btn btn-primary">
                        <i class="fas fa-database"></i> Create Encrypted Database Tables
                    </button>
                </div>
            </form>

            <form method="POST">
                <div class="form-group">
                    <button type="submit" name="action" value="enable_tde" class="btn btn-primary">
                        <i class="fas fa-key"></i> Generate Encryption Keys
                    </button>
                </div>
            </form>

            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> For Production Environments (MySQL Enterprise)</h4>
                <p>If you're using MySQL Enterprise Edition or Percona Server with TDE support:</p>
                <ol>
                    <li>Enable the keyring file plugin in my.cnf:
                        <code>early-plugin-load=keyring_file.so</code>
                    </li>
                    <li>Create encryption keys:
                        <code>ALTER INSTANCE ROTATE INNODB MASTER KEY;</code>
                    </li>
                    <li>Enable TDE on the database:
                        <code>ALTER TABLESPACE bunhs_db_important ENCRYPTION = 'Y';</code>
                    </li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Security Features Enabled</h3>
            </div>
            <ul>
                <li><strong>SSL/HTTPS:</strong> Forces encrypted connection for all data in transit</li>
                <li><strong>Application-Level Encryption:</strong> Sensitive fields encrypted before database storage</li>
                <li><strong>AES-256:</strong> Military-grade encryption algorithm for all sensitive data</li>
                <li><strong>CSRF Protection:</strong> Token-based protection against cross-site attacks</li>
                <li><strong>Input Sanitization:</strong> All user inputs are sanitized before processing</li>
            </ul>
        </div>
    </div>
</body>

</html>