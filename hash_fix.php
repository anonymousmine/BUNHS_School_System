<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  hash_fix.php — ONE-TIME sub_admin password fixer
//  Place at project root (same folder as db_connection.php).
//  Visit: http://localhost/BUNHS_School_System/hash_fix.php
//  DELETE THIS FILE immediately after running it.
// ═══════════════════════════════════════════════════════════════════════════════

// ── Localhost-only guard ──────────────────────────────────────────────────────
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('403 Forbidden — localhost only.');
}

include __DIR__ . '/db_connection.php';   // provides $conn (mysqli)

$fixed   = [];
$skipped = [];
$errors  = [];

// ── Fetch every sub_admin row ──────────────────────────────────────────────────
$result = $conn->query("SELECT id, username, password FROM `sub_admin`");
if (!$result) {
    die('Query failed: ' . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    $id       = (int) $row['id'];
    $username = $row['username'];
    $pw       = $row['password'] ?? '';

    // Detect whether the stored value is already a bcrypt hash.
    // password_get_info() returns algo=1 (PASSWORD_BCRYPT) for valid hashes.
    $info = password_get_info($pw);

    if ($info['algo'] !== 0) {
        // Already a valid hash — skip
        $skipped[] = "#{$id} {$username} — already hashed (algo: {$info['algoName']})";
        continue;
    }

    // Plain-text detected — hash it now
    $new_hash = password_hash($pw, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE `sub_admin` SET `password` = ? WHERE id = ?");
    if (!$stmt) {
        $errors[] = "#{$id} {$username} — prepare failed: " . $conn->error;
        continue;
    }
    $stmt->bind_param('si', $new_hash, $id);
    if ($stmt->execute()) {
        $fixed[] = "#{$id} {$username} — plain-text '{$pw}' → bcrypt hash applied ✓";
    } else {
        $errors[] = "#{$id} {$username} — update failed: " . $stmt->error;
    }
    $stmt->close();
}

// ── Also ensure all sub_admin rows have status = 'approved' if currently NULL/pending
//    (only for rows that existed before the signup flow added the status column)
$conn->query("
    UPDATE `sub_admin`
    SET `status` = 'approved'
    WHERE (`status` IS NULL OR `status` = '')
      AND `password` != ''
");
$status_fixed = $conn->affected_rows;

$conn->close();

// ── Also verify the admin table password is a valid hash ─────────────────────
include __DIR__ . '/db_connection.php';
$admin_issues = [];
$ar = $conn->query("SELECT id, username, password FROM `admin` LIMIT 10");
if ($ar) {
    while ($row = $ar->fetch_assoc()) {
        $info = password_get_info($row['password'] ?? '');
        $admin_issues[] = "#{$row['id']} {$row['username']} — " .
            ($info['algo'] !== 0 ? "✓ bcrypt hash (len=" . strlen($row['password']) . ")" : "⚠ NOT a valid hash — plain-text?");
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>hash_fix.php — BUNHS One-Time Password Fixer</title>
<style>
  body { font-family: monospace; background:#111; color:#e0e0e0; padding:32px; }
  h2   { color:#52b788; }
  h3   { color:#c9a84c; margin-top:28px; }
  .ok  { color:#52b788; }
  .skip{ color:#aaa; }
  .err { color:#f28b82; font-weight:bold; }
  .warn{ color:#ffd700; }
  ul   { line-height:2; }
  .box { background:#1e2d24; border:1px solid #2d6a4f; border-radius:8px; padding:20px 24px; margin-top:16px; }
  .del { background:#3a1a1a; border:1px solid #8b2020; border-radius:8px; padding:14px 20px; margin-top:24px; color:#f28b82; font-size:13px; }
</style>
</head>
<body>

<h2>🔐 BUNHS — hash_fix.php</h2>
<p>One-time run to bcrypt any plain-text passwords in <code>sub_admin</code>.</p>

<div class="box">
    <h3>sub_admin — Fixed (plain-text → bcrypt)</h3>
    <?php if ($fixed): ?>
        <ul><?php foreach ($fixed as $msg): ?><li class="ok"><?= htmlspecialchars($msg) ?></li><?php endforeach; ?></ul>
    <?php else: ?><p class="skip">Nothing to fix — no plain-text passwords found.</p><?php endif; ?>

    <h3>sub_admin — Skipped (already hashed)</h3>
    <?php if ($skipped): ?>
        <ul><?php foreach ($skipped as $msg): ?><li class="skip"><?= htmlspecialchars($msg) ?></li><?php endforeach; ?></ul>
    <?php else: ?><p class="skip">None.</p><?php endif; ?>

    <h3>sub_admin — Status fix</h3>
    <p class="<?= $status_fixed > 0 ? 'warn' : 'skip' ?>">
        <?= $status_fixed ?> row(s) updated from NULL/empty status → <strong>approved</strong>.
    </p>

    <h3>sub_admin — Errors</h3>
    <?php if ($errors): ?>
        <ul><?php foreach ($errors as $msg): ?><li class="err"><?= htmlspecialchars($msg) ?></li><?php endforeach; ?></ul>
    <?php else: ?><p class="ok">No errors.</p><?php endif; ?>
</div>

<div class="box">
    <h3>admin table — Password health check</h3>
    <?php if ($admin_issues): ?>
        <ul><?php foreach ($admin_issues as $msg): ?>
            <li class="<?= str_contains($msg, '✓') ? 'ok' : 'err' ?>"><?= htmlspecialchars($msg) ?></li>
        <?php endforeach; ?></ul>
    <?php else: ?><p class="skip">Could not read admin table.</p><?php endif; ?>
</div>

<div class="box">
    <h3>Next step — where to find your OTP (until PHPMailer is installed)</h3>
    <p>After fixing passwords, try logging in. The OTP is logged to PHP's error log because PHPMailer is not installed yet.</p>
    <p>Find it here:</p>
    <ul>
        <li><strong>XAMPP error log:</strong> <code>C:\xampp\php\logs\php_error_log</code></li>
        <li><strong>Or Apache log:</strong> <code>C:\xampp\apache\logs\error.log</code></li>
        <li>Look for a line like: <code>[login_otp] OTP for you@gmail.com (Admin): 123456</code></li>
    </ul>
    <p>Enter that 6-digit code on the verify page and login will complete.</p>

    <h3>Install PHPMailer (permanent fix)</h3>
    <p>Open a terminal in your project root and run:</p>
    <pre style="background:#0d1a11;padding:12px;border-radius:6px;color:#52b788;">composer require phpmailer/phpmailer</pre>
    <p>If you don't have Composer: <a href="https://getcomposer.org/download/" style="color:#c9a84c;">getcomposer.org/download</a></p>
    <p>After that, login will send real emails through Gmail and you won't need to check the logs.</p>
</div>

<div class="del">
    ⚠ <strong>DELETE THIS FILE NOW.</strong>
    It exposes plain-text passwords in your browser.
    Run: <code>del hash_fix.php</code> (Windows) or <code>rm hash_fix.php</code> (Linux/Mac)
</div>

</body>
</html>
