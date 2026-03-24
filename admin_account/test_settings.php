<?php
echo "<h2>Testing Settings.php</h2>";

// Test if settings.php loads without errors
ob_start();
include 'settings.php';
$output = ob_get_clean();

if (strpos($output, '<!DOCTYPE html') !== false) {
    echo "✅ Settings.php loads successfully<br>";
    echo "<a href='settings.php' target='_blank'>Open Settings Page</a>";
} else {
    echo "❌ Settings.php failed to load<br>";
    echo "Output: " . htmlspecialchars(substr($output, 0, 500)) . "...<br>";
}

echo "<hr>";
echo "<a href='check_db.php'>Check Database Structure</a><br>";
echo "<a href='debug_settings.php'>Debug Session Info</a><br>";
echo "<a href='../index.php'>Go to Index</a>";
?>
