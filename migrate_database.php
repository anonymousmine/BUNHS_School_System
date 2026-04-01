<?php
/**
 * Database Migration Script - Local to Railway
 * Run this on your Railway deployment to import local database
 */

require_once 'db_connection.php';

echo "<h2>Database Migration Tool</h2>\n";

// Check if this is a migration request
if (isset($_POST['migrate']) && $_POST['migrate'] === 'true') {
    echo "<h3>Starting Migration...</h3>\n";
    
    // Get local database connection (for export)
    $local_host = 'localhost';
    $local_user = 'root';
    $local_pass = ''; // Your local MySQL password
    $local_db = 'bunhs_db_important';
    
    try {
        // Connect to local database
        $local_conn = new mysqli($local_host, $local_user, $local_pass, $local_db);
        
        if ($local_conn->connect_error) {
            throw new Exception("Local DB Connection failed: " . $local_conn->connect_error);
        }
        
        echo "<p style='color: green;'>✅ Connected to local database</p>\n";
        
        // Get all tables from local database
        $tables = [];
        $result = $local_conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        echo "<p>Found " . count($tables) . " tables to migrate</p>\n";
        
        // Migrate each table
        $migrated_count = 0;
        foreach ($tables as $table) {
            echo "<p>Migrating table: <strong>$table</strong>...</p>\n";
            
            // Get table structure
            $create_table = $local_conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc()['Create Table'];
            
            // Create table in Railway
            $create_result = $conn->query($create_table);
            if (!$create_result) {
                echo "<p style='color: orange;'>⚠️ Table $table may already exist</p>\n";
            }
            
            // Get table data
            $data_result = $local_conn->query("SELECT * FROM `$table`");
            $data_count = 0;
            
            if ($data_result->num_rows > 0) {
                // Get column names
                $columns = [];
                $field_info = $local_conn->query("DESCRIBE `$table`");
                while ($field = $field_info->fetch_assoc()) {
                    $columns[] = $field['Field'];
                }
                
                // Insert data row by row
                while ($row = $data_result->fetch_assoc()) {
                    $escaped_values = [];
                    foreach ($columns as $col) {
                        $value = $row[$col];
                        if ($value === null) {
                            $escaped_values[] = 'NULL';
                        } else {
                            $escaped_values[] = "'" . $conn->real_escape_string($value) . "'";
                        }
                    }
                    
                    $insert_sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $escaped_values) . ")";
                    $insert_result = $conn->query($insert_sql);
                    
                    if ($insert_result) {
                        $data_count++;
                    }
                }
            }
            
            echo "<p style='color: green;'>✅ Migrated $data_count records from $table</p>\n";
            $migrated_count++;
        }
        
        $local_conn->close();
        
        echo "<h3 style='color: green;'>Migration Complete!</h3>\n";
        echo "<p>Successfully migrated $migrated_count tables from local database</p>\n";
        
        // Verify migration
        echo "<h3>Verification:</h3>\n";
        $verify_tables = ['admins', 'students', 'teachers', 'news', 'events', 'school_settings'];
        foreach ($verify_tables as $table) {
            $count = $conn->query("SELECT COUNT(*) as cnt FROM `$table`")->fetch_assoc()['cnt'];
            echo "<p>$table: $count records</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Migration Error: " . $e->getMessage() . "</p>\n";
        echo "<p><strong>Note:</strong> This script needs to run on your local machine first, then copy the generated SQL to Railway.</p>\n";
    }
    
} else {
    // Show migration form
    echo "<div style='background: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>\n";
    echo "<h3>Database Migration Options</h3>\n";
    echo "<p><strong>Option 1: Manual Export/Import (Recommended)</strong></p>\n";
    echo "<ol>\n";
    echo "<li>Export from local: <code>mysqldump -u root -p bunhs_db_important > export.sql</code></li>\n";
    echo "<li>Import to Railway MySQL service</li>\n";
    echo "</ol>\n";
    
    echo "<p><strong>Option 2: Use this Migration Tool</strong></p>\n";
    echo "<p>Click below to generate SQL export from your local database:</p>\n";
    echo "<form method='post'>\n";
    echo "<input type='hidden' name='migrate' value='true'>\n";
    echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px;'>Generate Migration SQL</button>\n";
    echo "</form>\n";
    echo "</div>\n";
}

echo "<h3>Current Railway Database Status</h3>\n";
$result = $conn->query("SHOW TABLES");
if ($result && $result->num_rows > 0) {
    echo "<p>Current tables in Railway database:</p>\n";
    echo "<ul>\n";
    while ($row = $result->fetch_array()) {
        echo "<li>" . htmlspecialchars($row[0]) . "</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p style='color: orange;'>No tables found in Railway database yet</p>\n";
}

$conn->close();
?>
