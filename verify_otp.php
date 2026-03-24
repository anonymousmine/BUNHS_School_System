<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  verify_otp.php — Admin / Sub-Admin OTP Verification Page
//  Shown after successful credential check in login.php / login_otp.php.
//  The OTP was already sent by login_otp.php (action=login_verify_credentials).
//  This page handles: display, verify (action=login_verify_otp), and
//  resend (action=login_resend_otp) via fetch() calls to login_otp.php.
// ═══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/session_config.php';

// ── Guard: must have a pending OTP session to land here ───────────────────────
if (!isset($_SESSION['otp_pending'])) {
    header('Location: login.php');
    exit;
}

// ── Derive masked contact for display ─────────────────────────────────────────
$pending        = $_SESSION['otp_pending'];
$masked_contact = 'your registered email';
if (!empty($pending['email'])) {
    [$u, $d]        = explode('@', $pending['email'], 2);
    $masked_contact = substr($u, 0, 2) . str_repeat('*', max(1, strlen($u) - 2)) . '@' . $d;
}
$display_name = htmlspecialchars($pending['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$user_type    = $pending['user_type'] ?? 'admin';
$role_label   = ($user_type === 'admin') ? 'Admin' : 'Sub-Admin';

// ── CSRF token for the verify/resend forms ─────────────────────────────────────
// login_verify_otp and login_resend_otp don't require CSRF (they use session
// state), but we include it for consistency and future-proofing.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login — Buyoan National High School</title>

    <!-- Favicons -->
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Google Fonts — matches modals.php -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ═══════════════════════════════════════════════════════════════
           DESIGN TOKENS — identical to modals.php
           ═══════════════════════════════════════════════════════════════ */
        :root {
            --bunhs-forest:  #1a3a2a;
            --bunhs-green:   #2d6a4f;
            --bunhs-mint:    #52b788;
            --bunhs-sage:    #b7e4c7;
            --bunhs-cream:   #f8f5f0;
            --bunhs-warm:    #fdf6ec;
            --bunhs-gold:    #c9a84c;
            --bunhs-gold-lt: #f0d98a;
            --bunhs-dark:    #111a14;
            --bunhs-ink:     #1e2d24;
            --bunhs-muted:   #6b7c72;
            --bunhs-border:  #dde8e2;
            --bunhs-shadow:  0 24px 64px rgba(26,58,42,.18), 0 4px 16px rgba(26,58,42,.10);
            --bunhs-radius:  20px;
            --bunhs-radius-sm: 12px;
            --bunhs-font:    'DM Sans', sans-serif;
            --bunhs-display: 'Playfair Display', Georgia, serif;
        }

        /* ── Page ───────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--bunhs-font);
            background: var(--bunhs-cream);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow: hidden;
        }

        /* Subtle background pattern */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                radial-gradient(ellipse 80% 60% at 15% 10%,  rgba(82,183,136,.13) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 85%,  rgba(201,168,76,.10) 0%, transparent 55%),
                linear-gradient(rgba(26,58,42,.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(26,58,42,.035) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 32px 32px, 32px 32px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Card ───────────────────────────────────────────────────── */
        .otp-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: var(--bunhs-radius);
            box-shadow: var(--bunhs-shadow);
            overflow: hidden;
            animation: card-rise .38s cubic-bezier(.34,1.28,.64,1);
        }

        @keyframes card-rise {
            from { opacity:0; transform: translateY(24px) scale(.96); }
            to   { opacity:1; transform: translateY(0)    scale(1);   }
        }

        /* ── Hero banner ────────────────────────────────────────────── */
        .otp-hero {
            position: relative;
            background: var(--bunhs-forest);
            padding: 32px 32px 28px;
            overflow: hidden;
            text-align: center;
        }

        .otp-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 60% at 85% 15%, rgba(82,183,136,.28) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at  5% 90%, rgba(201,168,76,.18) 0%, transparent 55%);
        }

        .otp-hero-grid {
            position: absolute;
            inset: 0;
            opacity: .04;
            background-image:
                linear-gradient(rgba(255,255,255,1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        .otp-hero-logo {
            position: relative;
            z-index: 1;
            width: 62px;
            height: 62px;
            border-radius: 50%;
            object-fit: cover;
            border: 2.5px solid rgba(255,255,255,.22);
            box-shadow: 0 4px 18px rgba(0,0,0,.3);
            margin-bottom: 12px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .otp-hero-badge {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(201,168,76,.18);
            border: 1px solid rgba(201,168,76,.38);
            color: var(--bunhs-gold-lt);
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            padding: 3px 11px;
            border-radius: 99px;
            margin-bottom: 10px;
        }

        .otp-hero h2 {
            position: relative;
            z-index: 1;
            font-family: var(--bunhs-display);
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            margin: 0 0 5px;
        }

        .otp-hero p {
            position: relative;
            z-index: 1;
            font-size: 12.5px;
            color: rgba(255,255,255,.55);
            margin: 0;
        }

        /* ── Body ───────────────────────────────────────────────────── */
        .otp-body {
            padding: 30px 32px 32px;
        }

        /* ── Shield icon ─────────────────────────────────────────────── */
        .shield-wrap {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(82,183,136,.14), rgba(45,106,79,.08));
            border: 2px solid rgba(82,183,136,.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--bunhs-green);
            margin: 0 auto 14px;
        }

        .otp-title {
            font-family: var(--bunhs-display);
            font-size: 18px;
            font-weight: 700;
            color: var(--bunhs-forest);
            margin: 0 0 6px;
            text-align: center;
        }

        .otp-subtitle {
            font-size: 13px;
            color: var(--bunhs-muted);
            margin: 0 0 6px;
            text-align: center;
            line-height: 1.55;
        }

        .otp-email-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(45,106,79,.08);
            border: 1px solid rgba(45,106,79,.2);
            color: var(--bunhs-green);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 99px;
            margin: 0 auto 18px;
            display: flex;
            width: fit-content;
            max-width: 100%;
            word-break: break-all;
        }

        /* ── Timer pill ──────────────────────────────────────────────── */
        .otp-timer {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
            background: var(--bunhs-warm);
            border: 1px solid #f0e4cc;
            color: #8b5e1a;
            margin: 0 auto 20px;
            display: flex;
            width: fit-content;
            transition: background .3s, border-color .3s, color .3s;
        }

        .otp-timer.urgent {
            background: #fff1f0;
            border-color: #ffd0cc;
            color: #c62828;
        }

        /* ── OTP boxes ───────────────────────────────────────────────── */
        .otp-row {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 0 0 22px;
        }

        .otp-box {
            width: 52px;
            height: 60px;
            font-family: var(--bunhs-display);
            font-size: 26px;
            font-weight: 700;
            text-align: center;
            color: var(--bunhs-forest);
            background: var(--bunhs-cream);
            border: 2px solid var(--bunhs-border);
            border-radius: var(--bunhs-radius-sm);
            outline: none;
            caret-color: var(--bunhs-mint);
            -webkit-appearance: none;
            appearance: none;
            transition: border-color .2s, background .2s, box-shadow .2s, transform .15s;
        }

        .otp-box:focus {
            border-color: var(--bunhs-mint);
            background: #fff;
            box-shadow: 0 0 0 3.5px rgba(82,183,136,.18);
            transform: scale(1.08);
        }

        .otp-box.is-filled {
            border-color: var(--bunhs-green);
            background: rgba(45,106,79,.06);
        }

        .otp-box.is-error {
            border-color: #e53935;
            animation: box-shake .38s ease;
        }

        @keyframes box-shake {
            0%,100% { transform: translateX(0); }
            25%      { transform: translateX(-5px); }
            75%      { transform: translateX(5px); }
        }

        /* ── Error box ───────────────────────────────────────────────── */
        .otp-err {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            margin-bottom: 14px;
            background: #fff1f0;
            border: 1px solid #ffd0cc;
            border-left: 3px solid #e53935;
            border-radius: 8px;
            color: #c62828;
            font-size: 12.5px;
            font-weight: 500;
        }

        .otp-err.show { display: flex; }
        .otp-err i    { flex-shrink: 0; }

        /* ── Success box ─────────────────────────────────────────────── */
        .otp-success {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            margin-bottom: 14px;
            background: #f0faf5;
            border: 1px solid #b7e4c7;
            border-left: 3px solid var(--bunhs-mint);
            border-radius: 8px;
            color: var(--bunhs-green);
            font-size: 12.5px;
            font-weight: 500;
        }

        .otp-success.show { display: flex; }

        /* ── Primary button ──────────────────────────────────────────── */
        .otp-btn {
            width: 100%;
            padding: 13px 20px;
            font-family: var(--bunhs-font);
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: .03em;
            color: #fff;
            background: linear-gradient(135deg, #3a8c6a 0%, var(--bunhs-forest) 100%);
            border: none;
            border-radius: var(--bunhs-radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(26,58,42,.3);
            position: relative;
            overflow: hidden;
            transition: transform .15s, box-shadow .15s, opacity .15s;
        }

        .otp-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.12) 0%, transparent 60%);
        }

        .otp-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 7px 22px rgba(26,58,42,.36);
        }

        .otp-btn:active:not(:disabled) { transform: translateY(0); }

        .otp-btn:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none !important;
        }

        .otp-btn .btn-spinner {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            animation: spin .6s linear infinite;
            display: none;
            flex-shrink: 0;
        }

        .otp-btn.loading .btn-label  { display: none; }
        .otp-btn.loading .btn-spinner { display: block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Ghost / link buttons ────────────────────────────────────── */
        .otp-ghost {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            font-family: var(--bunhs-font);
            font-size: 12.5px;
            color: var(--bunhs-muted);
            font-weight: 600;
            transition: color .2s;
        }

        .otp-ghost:hover:not(:disabled) { color: var(--bunhs-green); }
        .otp-ghost:disabled { cursor: default; opacity: .5; }
        .otp-ghost.active   { color: var(--bunhs-green); }

        /* ── Actions row ─────────────────────────────────────────────── */
        .otp-actions {
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
        }

        .otp-divider {
            width: 1px;
            height: 14px;
            background: var(--bunhs-border);
        }

        /* ── Back link ───────────────────────────────────────────────── */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 22px;
            font-size: 12.5px;
            color: var(--bunhs-muted);
            text-decoration: none;
            font-weight: 600;
            transition: color .2s;
        }

        .back-link:hover { color: var(--bunhs-green); }
        .back-link i { font-size: 10px; margin-right: 4px; }

        /* ── Expired overlay ─────────────────────────────────────────── */
        #expiredOverlay {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(4px);
            z-index: 10;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            text-align: center;
            padding: 32px;
            border-radius: var(--bunhs-radius);
        }

        #expiredOverlay.show { display: flex; }

        /* Responsive — shrink boxes slightly on very small screens */
        @media (max-width: 380px) {
            .otp-box { width: 42px; height: 52px; font-size: 22px; }
            .otp-body { padding: 24px 20px 28px; }
            .otp-hero { padding: 26px 20px 22px; }
        }
    </style>
</head>
<body>

<div class="otp-card">

    <!-- Hero -->
    <div class="otp-hero">
        <div class="otp-hero-grid"></div>
        <img src="assets/img/logo.jpg" alt="BUNHS Logo" class="otp-hero-logo"
             onerror="this.style.display='none'">
        <div class="otp-hero-badge">
            <i class="fas fa-shield-halved"></i>
            <?= $role_label ?> Portal
        </div>
        <h2>Two-Step Verification</h2>
        <p>Confirm it's really you before accessing the system</p>
    </div>

    <!-- Body -->
    <div class="otp-body" style="position:relative;">

        <!-- Expired overlay (shown when timer hits 0) -->
        <div id="expiredOverlay">
            <div style="width:58px;height:58px;border-radius:50%;background:#fff1f0;border:2px solid #ffd0cc;
                        display:flex;align-items:center;justify-content:center;font-size:22px;color:#c62828;">
                <i class="fas fa-clock-rotate-left"></i>
            </div>
            <p style="font-family:var(--bunhs-display);font-size:16px;font-weight:700;
                      color:var(--bunhs-forest);margin:0;">Code Expired</p>
            <p style="font-size:12.5px;color:var(--bunhs-muted);margin:0;line-height:1.5;">
                Your verification code has expired.<br>Request a new one to continue.
            </p>
            <button class="otp-btn" id="expiredResendBtn" style="max-width:220px;">
                <span class="btn-label"><i class="fas fa-rotate-right"></i>&ensp;Send New Code</span>
                <div class="btn-spinner"></div>
            </button>
            <a href="login.php" class="otp-ghost" style="font-size:12px;">
                <i class="fas fa-arrow-left" style="font-size:10px;"></i> Back to Login
            </a>
        </div>

        <!-- Shield + heading -->
        <div class="shield-wrap">
            <i class="fas fa-shield-alt"></i>
        </div>

        <p class="otp-title">Enter Verification Code</p>
        <p class="otp-subtitle">
            Hello, <strong><?= $display_name ?></strong>. We sent a 6-digit code to:
        </p>
        <div class="otp-email-badge">
            <i class="fas fa-envelope" style="font-size:11px;"></i>
            <span id="maskedContact"><?= htmlspecialchars($masked_contact, ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- Timer -->
        <div style="text-align:center;">
            <div class="otp-timer" id="otpTimer">
                <i class="fas fa-clock"></i>
                Expires in <span id="timerVal">05:00</span>
            </div>
        </div>

        <!-- OTP form -->
        <form id="otpForm" novalidate autocomplete="off">
            <input type="hidden" name="action"     value="login_verify_otp">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" id="otpHidden"    name="otp">

            <div class="otp-row" id="otpBoxes">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
            </div>

            <!-- Error / success -->
            <div class="otp-err"     id="otpErr"><i class="fas fa-exclamation-circle"></i><span id="otpErrTxt"></span></div>
            <div class="otp-success" id="otpSuccess"><i class="fas fa-check-circle"></i><span id="otpSuccessTxt">Verifying…</span></div>

            <button type="submit" class="otp-btn" id="verifyBtn">
                <span class="btn-label"><i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign In</span>
                <div class="btn-spinner"></div>
            </button>
        </form>

        <!-- Resend + Back row -->
        <div class="otp-actions">
            <button type="button" class="otp-ghost" id="resendBtn" disabled>
                Resend · <span id="resendCountdown">30</span>s
            </button>
            <div class="otp-divider"></div>
            <a href="login.php" class="otp-ghost">
                <i class="fas fa-arrow-left" style="font-size:10px;"></i> Back
            </a>
        </div>

    </div><!-- /.otp-body -->
</div><!-- /.otp-card -->


<script>
/* ═══════════════════════════════════════════════════════════════════════════
   verify_otp.php — Client-Side Logic
   ═══════════════════════════════════════════════════════════════════════════ */

// ── DOM refs ──────────────────────────────────────────────────────────────────
const boxes        = [...document.querySelectorAll('.otp-box')];
const otpHidden    = document.getElementById('otpHidden');
const otpForm      = document.getElementById('otpForm');
const verifyBtn    = document.getElementById('verifyBtn');
const resendBtn    = document.getElementById('resendBtn');
const otpErr       = document.getElementById('otpErr');
const otpErrTxt    = document.getElementById('otpErrTxt');
const otpSuccess   = document.getElementById('otpSuccess');
const otpSuccessTxt= document.getElementById('otpSuccessTxt');
const timerEl      = document.getElementById('timerVal');
const timerPill    = document.getElementById('otpTimer');
const resendCount  = document.getElementById('resendCountdown');
const expiredOv    = document.getElementById('expiredOverlay');
const expiredBtn   = document.getElementById('expiredResendBtn');
const maskedEl     = document.getElementById('maskedContact');

// ── OTP box behaviour ─────────────────────────────────────────────────────────
boxes.forEach((box, i) => {
    box.addEventListener('input', e => {
        const val = e.target.value.replace(/\D/g, '');
        e.target.value = val.slice(-1);               // keep only last digit
        toggleFilled(box);
        if (val && i < boxes.length - 1) boxes[i + 1].focus();
        updateHidden();
        clearErr();
    });

    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace') {
            if (!box.value && i > 0) {
                boxes[i - 1].value = '';
                toggleFilled(boxes[i - 1]);
                boxes[i - 1].focus();
            }
            setTimeout(updateHidden, 0);
        }
        if (e.key === 'ArrowLeft'  && i > 0)                boxes[i - 1].focus();
        if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
    });

    box.addEventListener('paste', e => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        text.split('').slice(0, boxes.length).forEach((ch, idx) => {
            if (boxes[idx]) { boxes[idx].value = ch; toggleFilled(boxes[idx]); }
        });
        const nextEmpty = boxes.find(b => !b.value);
        (nextEmpty || boxes[boxes.length - 1]).focus();
        updateHidden();
    });

    box.addEventListener('focus', () => box.select());
});

function toggleFilled(box) {
    box.classList.toggle('is-filled', box.value !== '');
}

function updateHidden() {
    otpHidden.value = boxes.map(b => b.value).join('');
}

function clearErr() {
    otpErr.classList.remove('show');
    boxes.forEach(b => b.classList.remove('is-error'));
}

function showErr(msg) {
    otpErrTxt.textContent = msg;
    otpErr.classList.add('show');
    otpSuccess.classList.remove('show');
    boxes.forEach(b => b.classList.add('is-error'));
    setTimeout(() => boxes.forEach(b => b.classList.remove('is-error')), 600);
}

function showSuccess(msg) {
    otpSuccessTxt.textContent = msg;
    otpSuccess.classList.add('show');
    otpErr.classList.remove('show');
}

// Auto-focus first box on load
boxes[0].focus();

// ── Countdown timer (5 min = 300 s) ──────────────────────────────────────────
let secondsLeft = 300;
let timerInterval;

function formatTime(s) {
    const m = String(Math.floor(s / 60)).padStart(2, '0');
    const sec = String(s % 60).padStart(2, '0');
    return `${m}:${sec}`;
}

function startTimer() {
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        secondsLeft--;
        timerEl.textContent = formatTime(secondsLeft);
        if (secondsLeft <= 60) timerPill.classList.add('urgent');
        if (secondsLeft <= 0)  { clearInterval(timerInterval); onExpired(); }
    }, 1000);
}

function onExpired() {
    verifyBtn.disabled = true;
    boxes.forEach(b => b.disabled = true);
    expiredOv.classList.add('show');
}

startTimer();

// ── Resend countdown (30 s) ───────────────────────────────────────────────────
let resendLeft = 30;
let resendInterval;

function startResendCountdown(seconds = 30) {
    resendLeft = seconds;
    resendBtn.disabled = true;
    resendBtn.classList.remove('active');
    clearInterval(resendInterval);
    resendInterval = setInterval(() => {
        resendLeft--;
        resendCount.textContent = resendLeft;
        if (resendLeft <= 0) {
            clearInterval(resendInterval);
            resendBtn.disabled = false;
            resendBtn.classList.add('active');
            resendBtn.innerHTML = "Didn't receive it? <strong>Resend</strong>";
        }
    }, 1000);
}

startResendCountdown(30);

// ── Shared fetch helper ───────────────────────────────────────────────────────
async function postToOtpHandler(body) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(body)) fd.append(k, v);
    const res = await fetch('login_otp.php', { method: 'POST', body: fd });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

// ── Verify OTP ────────────────────────────────────────────────────────────────
otpForm.addEventListener('submit', async e => {
    e.preventDefault();
    clearErr();

    const otp = otpHidden.value;
    if (otp.length < 6) { showErr('Please enter all 6 digits.'); return; }

    verifyBtn.classList.add('loading');
    verifyBtn.disabled = true;

    try {
        const data = await postToOtpHandler({
            action:     'login_verify_otp',
            csrf_token: document.querySelector('[name="csrf_token"]').value,
            otp:        otp
        });

        if (data.success) {
            clearInterval(timerInterval);
            clearInterval(resendInterval);
            showSuccess('Identity verified! Redirecting…');
            verifyBtn.classList.remove('loading');
            verifyBtn.disabled = false;

            // Redirect based on user_type
            setTimeout(() => {
                const type = data.user_type || 'admin';
                window.location.href = (type === 'admin' || type === 'sub-admin')
                    ? 'admin_account/admin_dashboard.php'
                    : 'index.php';
            }, 800);
        } else {
            showErr(data.message || 'Invalid code. Please try again.');
            verifyBtn.classList.remove('loading');
            verifyBtn.disabled = false;
            // Clear boxes and re-focus
            boxes.forEach(b => { b.value = ''; b.classList.remove('is-filled'); });
            otpHidden.value = '';
            boxes[0].focus();
        }
    } catch {
        showErr('Connection error. Please try again.');
        verifyBtn.classList.remove('loading');
        verifyBtn.disabled = false;
    }
});

// ── Resend OTP (inline button) ────────────────────────────────────────────────
async function doResend(btn) {
    btn.disabled = true;
    clearErr();
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Sending…';

    try {
        const data = await postToOtpHandler({ action: 'login_resend_otp' });

        if (data.success) {
            // Reset timer
            secondsLeft = 300;
            timerEl.textContent = formatTime(secondsLeft);
            timerPill.classList.remove('urgent');
            clearInterval(timerInterval);
            startTimer();

            // Update masked contact if changed
            if (data.masked_contact) {
                maskedEl.textContent = data.masked_contact;
            }

            // Re-enable boxes if they were locked
            boxes.forEach(b => { b.disabled = false; b.value = ''; b.classList.remove('is-filled'); });
            otpHidden.value = '';
            verifyBtn.disabled = false;
            expiredOv.classList.remove('show');
            boxes[0].focus();

            showSuccess('A new code has been sent to your email.');
            setTimeout(() => otpSuccess.classList.remove('show'), 3500);

            // Restart resend countdown
            btn.innerHTML = `Resend · <span id="resendCountdown">30</span>s`;
            resendCount.textContent = '30'; // re-bind ref
            startResendCountdown(30);
        } else {
            btn.innerHTML = origHtml;
            btn.disabled  = false;
            showErr(data.message || 'Could not resend. Please try again.');
        }
    } catch {
        btn.innerHTML = origHtml;
        btn.disabled  = false;
        showErr('Connection error. Please try again.');
    }
}

resendBtn.addEventListener('click', () => doResend(resendBtn));
expiredBtn.addEventListener('click', () => doResend(expiredBtn));
</script>

</body>
</html>
