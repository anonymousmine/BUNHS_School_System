<?php

/**
 * session_config.php
 * Include this ONCE at the top of every page BEFORE session_start().
 * Ensures all pages share identical session cookie settings so the
 * session persists correctly across the admin_account/ folder.
 */
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 0,                        // until browser closes
        'path'     => '/',                       // accessible from all paths on this host
        'domain'   => '',                        // current domain only
        'secure'   => $isHttps,                  // HTTPS only in production, HTTP ok on localhost
        'httponly' => true,                      // no JS access to cookie
        'samesite' => 'Lax',                     // Lax allows same-site navigations (Strict was blocking)
    ]);

    session_start();

    // Generate CSRF token if not set
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
