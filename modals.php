<?php
// modals.php is fetched as a standalone HTTP request by the browser.
// It must start its own session (using the same config as index.php)
// so that $_SESSION['csrf_token'] is available to render in the form.
require_once __DIR__ . '/session_config.php';

// Generate CSRF token if not already set (e.g. direct fetch without index.php)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!-- ═══════════════════════════════════════════════════════════════
     BUNHS AUTH MODALS  —  Premium Redesign
     Login + Signup with 6-box OTP UI
     ═══════════════════════════════════════════════════════════════ -->

<!-- ── Google Fonts ─────────────────────────────────────────── -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>
    /* ═══════════════════════════════════════════════════════════════
   DESIGN TOKENS
   ═══════════════════════════════════════════════════════════════ */
    :root {
        --bunhs-forest: #1a3a2a;
        --bunhs-green: #2d6a4f;
        --bunhs-mint: #52b788;
        --bunhs-sage: #b7e4c7;
        --bunhs-cream: #f8f5f0;
        --bunhs-warm: #fdf6ec;
        --bunhs-gold: #c9a84c;
        --bunhs-gold-lt: #f0d98a;
        --bunhs-dark: #111a14;
        --bunhs-ink: #1e2d24;
        --bunhs-muted: #6b7c72;
        --bunhs-border: #dde8e2;
        --bunhs-shadow: 0 24px 64px rgba(26, 58, 42, .18), 0 4px 16px rgba(26, 58, 42, .10);
        --bunhs-radius: 20px;
        --bunhs-radius-sm: 12px;
        --bunhs-font: 'DM Sans', sans-serif;
        --bunhs-display: 'Playfair Display', Georgia, serif;
    }

    /* ═══════════════════════════════════════════════════════════════
   MODAL SHELL
   ═══════════════════════════════════════════════════════════════ */
    #loginModal .modal-dialog {
        max-width: 460px;
    }

    #signupModal .modal-dialog {
        max-width: 540px;
    }

    #loginModal .modal-content,
    #signupModal .modal-content {
        border: none;
        border-radius: var(--bunhs-radius);
        box-shadow: var(--bunhs-shadow);
        overflow: hidden;
        font-family: var(--bunhs-font);
        background: #fff;
        animation: bm-rise .32s cubic-bezier(.34, 1.28, .64, 1);
    }

    @keyframes bm-rise {
        from {
            opacity: 0;
            transform: translateY(20px) scale(.96);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* ── Hero banner ────────────────────────────────────────────── */
    .bm-hero {
        position: relative;
        background: var(--bunhs-forest);
        padding: 32px 32px 26px;
        overflow: hidden;
    }

    .bm-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 70% 60% at 85% 15%, rgba(82, 183, 136, .25) 0%, transparent 60%),
            radial-gradient(ellipse 50% 40% at 5% 90%, rgba(201, 168, 76, .18) 0%, transparent 55%);
    }

    .bm-hero-grid {
        position: absolute;
        inset: 0;
        opacity: .04;
        background-image:
            linear-gradient(rgba(255, 255, 255, 1) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 1) 1px, transparent 1px);
        background-size: 28px 28px;
    }

    .bm-hero-close {
        position: absolute;
        top: 14px;
        right: 16px;
        z-index: 3;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .1);
        border: 1px solid rgba(255, 255, 255, .15);
        color: rgba(255, 255, 255, .65);
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .2s, color .2s;
    }

    .bm-hero-close:hover {
        background: rgba(255, 255, 255, .2);
        color: #fff;
    }

    .bm-hero-logo {
        position: relative;
        z-index: 1;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 2.5px solid rgba(255, 255, 255, .22);
        box-shadow: 0 4px 18px rgba(0, 0, 0, .3);
        margin-bottom: 12px;
        display: block;
    }

    .bm-hero-badge {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(201, 168, 76, .18);
        border: 1px solid rgba(201, 168, 76, .38);
        color: var(--bunhs-gold-lt);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
        padding: 3px 11px;
        border-radius: 99px;
        margin-bottom: 8px;
    }

    .bm-hero h2 {
        position: relative;
        z-index: 1;
        font-family: var(--bunhs-display);
        font-size: 24px;
        font-weight: 700;
        color: #fff;
        margin: 0 0 5px;
    }

    .bm-hero p {
        position: relative;
        z-index: 1;
        font-size: 13px;
        color: rgba(255, 255, 255, .55);
        margin: 0;
    }

    /* Step pills */
    .bm-steps {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 16px;
        gap: 0;
    }

    .bm-step {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .05em;
        color: rgba(255, 255, 255, .35);
        text-transform: uppercase;
        transition: color .3s;
    }

    .bm-step.active {
        color: rgba(255, 255, 255, .88);
    }

    .bm-step-dot {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 800;
        background: rgba(255, 255, 255, .1);
        color: rgba(255, 255, 255, .35);
        border: 1.5px solid rgba(255, 255, 255, .18);
        transition: all .3s;
    }

    .bm-step.active .bm-step-dot {
        background: var(--bunhs-mint);
        color: var(--bunhs-forest);
        border-color: var(--bunhs-mint);
        box-shadow: 0 0 0 3px rgba(82, 183, 136, .25);
    }

    .bm-step-line {
        width: 30px;
        height: 1.5px;
        background: rgba(255, 255, 255, .14);
        margin: 0 6px;
    }

    /* ── Body ───────────────────────────────────────────────────── */
    .bm-body {
        padding: 26px 30px 30px;
        background: #fff;
    }

    #signupModal .bm-body {
        max-height: 72vh;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--bunhs-sage) transparent;
    }

    #signupModal .bm-body::-webkit-scrollbar {
        width: 4px;
    }

    #signupModal .bm-body::-webkit-scrollbar-thumb {
        background: var(--bunhs-sage);
        border-radius: 99px;
    }

    /* ── Fields ─────────────────────────────────────────────────── */
    .bm-field {
        margin-bottom: 14px;
    }

    .bm-label {
        display: block;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: .055em;
        color: var(--bunhs-ink);
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    /* Wrapper that holds icon + input side by side */
    .bm-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .bm-field-icon {
        position: absolute;
        left: 13px;
        color: var(--bunhs-muted);
        font-size: 13px;
        pointer-events: none;
        z-index: 2;
        transition: color .2s;
        /* vertically centered inside the wrapper, not the whole field */
        top: 50%;
        transform: translateY(-50%);
    }

    .bm-input {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 13px 12px 38px;
        font-family: var(--bunhs-font);
        font-size: 13.5px;
        color: var(--bunhs-ink);
        background: var(--bunhs-cream);
        border: 1.5px solid var(--bunhs-border);
        border-radius: var(--bunhs-radius-sm);
        outline: none;
        -webkit-appearance: none;
        appearance: none;
        transition: border-color .2s, background .2s, box-shadow .2s;
    }

    .bm-input::placeholder {
        color: #b5c0bb;
        font-size: 13px;
    }

    .bm-input:focus {
        border-color: var(--bunhs-mint);
        background: #fff;
        box-shadow: 0 0 0 3.5px rgba(82, 183, 136, .15);
    }

    .bm-input-wrap:focus-within .bm-field-icon {
        color: var(--bunhs-green);
    }

    .bm-input.has-toggle {
        padding-right: 42px;
    }

    .bm-toggle-pwd {
        position: absolute;
        right: 11px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        padding: 3px;
        cursor: pointer;
        color: var(--bunhs-muted);
        font-size: 13px;
        transition: color .2s;
        z-index: 2;
    }

    .bm-toggle-pwd:hover {
        color: var(--bunhs-green);
    }

    /* Two-column row */
    .bm-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    /* ── Contact method cards ───────────────────────────────────── */
    .bm-contact-wrap {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 14px;
    }

    .bm-contact-opt {
        position: relative;
    }

    .bm-contact-opt input[type=radio] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .bm-contact-card {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 10px 14px;
        border: 1.5px solid var(--bunhs-border);
        border-radius: var(--bunhs-radius-sm);
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: var(--bunhs-muted);
        background: var(--bunhs-cream);
        transition: all .2s;
        user-select: none;
    }

    .bm-contact-card i {
        font-size: 15px;
        transition: transform .2s;
    }

    .bm-contact-opt input:checked+.bm-contact-card {
        border-color: var(--bunhs-mint);
        color: var(--bunhs-green);
        background: rgba(82, 183, 136, .07);
        box-shadow: 0 0 0 3px rgba(82, 183, 136, .13);
    }

    .bm-contact-opt input:checked+.bm-contact-card i {
        transform: scale(1.15);
    }

    /* ── Primary button ──────────────────────────────────────────── */
    .bm-btn {
        width: 100%;
        padding: 13px 20px;
        margin-top: 4px;
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
        box-shadow: 0 4px 16px rgba(26, 58, 42, .3);
        position: relative;
        overflow: hidden;
        transition: transform .15s, box-shadow .15s;
    }

    .bm-btn::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255, 255, 255, .12) 0%, transparent 60%);
    }

    .bm-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 7px 22px rgba(26, 58, 42, .36);
    }

    .bm-btn:active:not(:disabled) {
        transform: translateY(0);
    }

    .bm-btn:disabled {
        opacity: .55;
        cursor: not-allowed;
        transform: none !important;
    }

    .bm-btn .bm-spinner {
        width: 15px;
        height: 15px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, .3);
        border-top-color: #fff;
        animation: bm-spin .6s linear infinite;
        display: none;
    }

    .bm-btn.loading .bm-btn-label {
        display: none;
    }

    .bm-btn.loading .bm-spinner {
        display: block;
    }

    @keyframes bm-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ── Terms check ─────────────────────────────────────────────── */
    .bm-terms {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        margin-bottom: 14px;
    }

    .bm-terms input {
        margin-top: 3px;
        accent-color: var(--bunhs-green);
        cursor: pointer;
        flex-shrink: 0;
    }

    .bm-terms label {
        font-size: 12px;
        color: var(--bunhs-muted);
        cursor: pointer;
        line-height: 1.55;
    }

    .bm-terms label a {
        color: var(--bunhs-green);
        font-weight: 700;
        text-decoration: none;
    }

    /* ── Inline error ────────────────────────────────────────────── */
    .bm-err {
        display: none;
        align-items: center;
        gap: 8px;
        padding: 9px 13px;
        margin-bottom: 12px;
        background: #fff1f0;
        border: 1px solid #ffd0cc;
        border-left: 3px solid #e53935;
        border-radius: 8px;
        color: #c62828;
        font-size: 12.5px;
        font-weight: 500;
    }

    .bm-err.show {
        display: flex;
    }

    .bm-err i {
        flex-shrink: 0;
    }

    /* ── OTP boxes ───────────────────────────────────────────────── */
    .bm-otp-row {
        display: flex;
        justify-content: center;
        gap: 7px;
        margin: 6px 0 20px;
    }

    .bm-otp-box {
        width: 48px;
        height: 56px;
        font-family: var(--bunhs-display);
        font-size: 24px;
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

    .bm-otp-box:focus {
        border-color: var(--bunhs-mint);
        background: #fff;
        box-shadow: 0 0 0 3.5px rgba(82, 183, 136, .18);
        transform: scale(1.07);
    }

    .bm-otp-box.is-filled {
        border-color: var(--bunhs-green);
        background: rgba(45, 106, 79, .06);
    }

    .bm-otp-box.is-error {
        border-color: #e53935;
        animation: bm-shake .38s ease;
    }

    @keyframes bm-shake {

        0%,
        100% {
            transform: translateX(0);
        }

        25% {
            transform: translateX(-5px);
        }

        75% {
            transform: translateX(5px);
        }
    }

    /* ── Timer pill ─────────────────────────────────────────────── */
    .bm-timer {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 13px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 600;
        background: var(--bunhs-warm);
        border: 1px solid #f0e4cc;
        color: #8b5e1a;
    }

    .bm-timer.urgent {
        background: #fff1f0;
        border-color: #ffd0cc;
        color: #c62828;
    }

    /* ── Shield icon ─────────────────────────────────────────────── */
    .bm-shield-wrap {
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(82, 183, 136, .14), rgba(45, 106, 79, .08));
        border: 2px solid rgba(82, 183, 136, .28);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 23px;
        color: var(--bunhs-green);
        margin: 0 auto 10px;
    }

    /* ── Resend / back links ─────────────────────────────────────── */
    .bm-ghost {
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

    .bm-ghost:hover {
        color: var(--bunhs-green);
    }

    .bm-ghost:disabled {
        cursor: default;
        opacity: .5;
    }

    .bm-ghost.on {
        color: var(--bunhs-green);
    }

    /* ── Step panel fade-slide ───────────────────────────────────── */
    .bm-panel {
        animation: bm-panel-in .28s ease;
    }

    @keyframes bm-panel-in {
        from {
            opacity: 0;
            transform: translateX(14px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* ── Toast ───────────────────────────────────────────────────── */
</style>


<!-- ═══════════════════════════════════════════════════════════════
     LOGIN MODAL
     ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="bm-hero">
                <div class="bm-hero-grid"></div>
                <button class="bm-hero-close" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
                <img src="assets/img/logo.jpg" alt="BUNHS" class="bm-hero-logo">
                <div class="bm-hero-badge"><i class="fas fa-shield-alt"></i> Admin Portal</div>
                <h2>Welcome Back</h2>
                <p>Sign in to access the school management system</p>
            </div>

            <div class="bm-body">

                <!-- Step 1: credentials -->
                <div id="loginStep1">
                    <form id="loginCredentialsForm" method="POST" autocomplete="on">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">


                        <div class="bm-field">
                            <label class="bm-label" for="loginUsername">Username</label>
                            <div class="bm-input-wrap">
                                <i class="fas fa-user bm-field-icon"></i>
                                <input class="bm-input" type="text" id="loginUsername" name="username"
                                    placeholder="Enter your username" required autocomplete="username">
                            </div>
                        </div>

                        <div class="bm-field">
                            <label class="bm-label" for="loginPassword">Password</label>
                            <div class="bm-input-wrap">
                                <i class="fas fa-lock bm-field-icon"></i>
                                <input class="bm-input has-toggle" type="password" id="loginPassword" name="password"
                                    placeholder="Enter your password" required autocomplete="current-password">
                                <button type="button" class="bm-toggle-pwd" id="toggleLoginPwd" tabindex="-1">
                                    <i class="fas fa-eye" id="loginEyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="bm-err" id="loginErrBox">
                            <i class="fas fa-exclamation-circle"></i>
                            <span id="loginErrTxt"></span>
                        </div>

                        <div style="text-align:right; margin: -4px 0 16px;">
                            <a href="admin_account/forgot_password.php" class="bm-ghost on" style="font-size:12px; font-weight:600;">Forgot password?</a>
                        </div>

                        <button type="submit" class="bm-btn" id="loginSubmitBtn">
                            <span class="bm-btn-label"><i class="fas fa-arrow-right-to-bracket"></i>&ensp;Sign In</span>
                            <div class="bm-spinner"></div>
                        </button>
                    </form>
                </div>

                <!-- Step 2: OTP -->
                <div id="loginStep2" style="display:none;">
                    <div class="bm-panel" style="text-align:center;">
                        <div class="bm-shield-wrap"><i class="fas fa-shield-alt"></i></div>
                        <p style="font-family:var(--bunhs-display);font-size:17px;font-weight:700;color:var(--bunhs-forest);margin:0 0 5px;">Two-Step Verification</p>
                        <p style="font-size:12.5px;color:var(--bunhs-muted);margin:0 0 12px;" id="loginOtpSubtitle">Enter the 6-digit code sent to your contact.</p>
                        <div style="margin-bottom:16px;">
                            <span class="bm-timer" id="loginTimer"><i class="fas fa-clock"></i> <span id="loginTimerVal">05:00</span></span>
                        </div>

                        <form id="loginOtpForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                            <div class="bm-otp-row" id="loginOtpBoxes">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            </div>
                            <input type="hidden" id="loginOtpHidden" name="otp">

                            <div class="bm-err" id="loginOtpErrBox">
                                <i class="fas fa-exclamation-circle"></i>
                                <span id="loginOtpErrTxt"></span>
                            </div>

                            <button type="submit" class="bm-btn" id="loginVerifyBtn">
                                <span class="bm-btn-label"><i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign In</span>
                                <div class="bm-spinner"></div>
                            </button>

                            <div style="margin-top:14px; display:flex; align-items:center; justify-content:center; gap:12px;">
                                <button type="button" class="bm-ghost" id="loginResendBtn" disabled>
                                    Resend · <span id="loginResendTimer">30</span>s
                                </button>
                                <span style="color:var(--bunhs-border); font-size:14px;">|</span>
                                <button type="button" class="bm-ghost" id="loginBackBtn">
                                    <i class="fas fa-arrow-left" style="font-size:10px;"></i> Back
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════
     SIGNUP MODAL
     ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="signupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="bm-hero">
                <div class="bm-hero-grid"></div>
                <button class="bm-hero-close" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
                <img src="assets/img/logo.jpg" alt="BUNHS" class="bm-hero-logo">
                <div class="bm-hero-badge"><i class="fas fa-user-plus"></i> New Account</div>
                <h2>Join Our Community</h2>
                <p>Create your admin account to get started</p>

                <div class="bm-steps">
                    <div class="bm-step active" id="spill1">
                        <div class="bm-step-dot">1</div><span>Details</span>
                    </div>
                    <div class="bm-step-line"></div>
                    <div class="bm-step" id="spill2">
                        <div class="bm-step-dot">2</div><span>Verify</span>
                    </div>
                </div>
            </div>

            <div class="bm-body">

                <!-- Step 1: form -->
                <div id="signupFormContainer">
                    <form id="signupForm" method="POST" novalidate onsubmit="return false;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
                        <!-- ✅ FIX: action changed from "signup" to "send_otp" -->
                        <input type="hidden" name="action" value="send_otp">

                        <!-- ── Name row: First · Middle Name ── -->
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
                            <div class="bm-field">
                                <label class="bm-label" for="firstName">First Name <span style="color:#e53935;">*</span></label>
                                <div class="bm-input-wrap">
                                    <i class="fas fa-user bm-field-icon"></i>
                                    <input class="bm-input" type="text" id="firstName" name="first_name"
                                        placeholder="Juan" required
                                        oninput="this.value=this.value.replace(/[^A-Za-z\s\-]/g,'').replace(/\b\w/g, c => c.toUpperCase())">
                                </div>
                            </div>
                            <div class="bm-field">
                                <label class="bm-label" for="middleInitial">Middle Name</label>
                                <div class="bm-input-wrap">
                                    <i class="fas fa-user bm-field-icon"></i>
                                    <input class="bm-input" type="text" id="middleInitial" name="middle_initial"
                                        placeholder="Santos" maxlength="50"
                                        oninput="this.value=this.value.replace(/[^A-Za-z\s\-]/g,'').replace(/\b\w/g, c => c.toUpperCase())">
                                </div>
                            </div>
                        </div>
                        <!-- Validation indicators for name fields -->
                        <div id="firstNameError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">First name is required</div>
                        <div id="middleNameError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Middle name is optional</div>

                        <!-- ── Last Name and Suffix row ── -->
                        <div style="display:grid;grid-template-columns:1fr 180px;gap:10px;margin-bottom:16px;">
                            <div class="bm-field">
                                <label class="bm-label" for="lastName">Last Name <span style="color:#e53935;">*</span></label>
                                <div class="bm-input-wrap">
                                    <i class="fas fa-user bm-field-icon"></i>
                                    <input class="bm-input" type="text" id="lastName" name="last_name"
                                        placeholder="Dela Cruz" required
                                        oninput="this.value=this.value.replace(/[^A-Za-z\s\-]/g,'').replace(/\b\w/g, c => c.toUpperCase())">
                                </div>
                            </div>
                            <div class="bm-field">
                                <label class="bm-label" for="signupSuffix">Suffix</label>
                                <div class="bm-input-wrap" style="position:relative;">
                                    <i class="fas fa-id-badge bm-field-icon"></i>
                                    <select class="bm-input" id="signupSuffix" name="suffix"
                                        style="padding-left:38px;padding-right:35px;-webkit-appearance:none;appearance:none;cursor:pointer;">
                                        <option value="">None</option>
                                        <option value="Jr.">Jr.</option>
                                        <option value="Sr.">Sr.</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                        <option value="V">V</option>
                                    </select>
                                    <i class="fas fa-chevron-down" style="position:absolute;right:13px;top:50%;transform:translateY(-50%);color:#6b7c72;font-size:12px;pointer-events:none;"></i>
                                </div>
                            </div>
                        </div>
                        <!-- Validation indicator for last name -->
                        <div id="lastNameError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Last name is required</div>
                        <div id="suffixError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Suffix is optional</div>

                        <!-- ── Email Field (Gmail Only) ── -->
                        <div class="bm-field">
                            <label class="bm-label" for="email">Gmail Address <span style="color:#e53935;">*</span></label>
                            <div class="bm-input-wrap">
                                <i class="fas fa-envelope bm-field-icon"></i>
                                <input class="bm-input" type="email" id="email" name="email"
                                    placeholder="your@gmail.com" required
                                    autocomplete="email">
                            </div>
                        </div>
                        <div id="emailError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Email is required</div>
                        <div id="emailWarning" style="display:none;font-size:11.5px;color:#c62828;margin-top:4px;margin-bottom:8px;padding-left:2px;"></div>
                        <div id="emailFormatError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Please enter a valid email address</div>

                        <div id="phoneField" class="bm-field" style="display:none;">
                            <label class="bm-label" for="phone">Phone Number <span style="color:#e53935;">*</span></label>
                            <div class="bm-input-wrap">
                                <i class="fas fa-phone bm-field-icon"></i>
                                <input class="bm-input" type="tel" id="phone" name="phone"
                                    placeholder="09xxxxxxxxx" maxlength="11"
                                    autocomplete="tel"
                                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)">
                            </div>
                            <div style="font-size:11px;color:var(--bunhs-muted);margin-top:3px;padding-left:2px;">Philippine format: 09xxxxxxxxx</div>
                        </div>
                        <div id="phoneError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Phone is required</div>
                        <div id="phoneFormatError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Please enter a valid Philippine phone number</div>

                        <!-- ── Username ── -->
                        <div class="bm-field">
                            <label class="bm-label" for="signupUsername">Username <span style="color:#e53935;">*</span></label>
                            <div class="bm-input-wrap">
                                <i class="fas fa-at bm-field-icon"></i>
                                <input class="bm-input" type="text" id="signupUsername" name="username"
                                    placeholder="Choose a username (3–50 chars)" required minlength="3" maxlength="50"
                                    autocomplete="username"
                                    oninput="this.value=this.value.replace(/[^A-Za-z0-9_\-\.]/g,'')">
                            </div>
                            <div id="usernameHint" style="font-size:11px;color:var(--bunhs-muted);margin-top:3px;padding-left:2px;">
                                Letters, numbers, underscore, hyphen, dot only.
                            </div>
                        </div>
                        <div id="usernameError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Username is required</div>
                        <div id="usernameLengthError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Username must be 3-50 characters</div>
                        <div id="usernameFormatError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Invalid characters in username</div>

                        <!-- ── Password + Confirm ── -->
                        <div class="bm-row">
                            <div class="bm-field">
                                <label class="bm-label" for="signupPassword">Password <span style="color:#e53935;">*</span></label>
                                <div class="bm-input-wrap">
                                    <i class="fas fa-lock bm-field-icon"></i>
                                    <input class="bm-input has-toggle" type="password" id="signupPassword" name="password"
                                        placeholder="Min. 8 characters" required minlength="8"
                                        autocomplete="new-password"
                                        oninput="validateSignupPassword(this.value)">
                                    <button type="button" class="bm-toggle-pwd" id="toggleSignupPwd" tabindex="-1">
                                        <i class="fas fa-eye" id="signupEyeIcon"></i>
                                    </button>
                                </div>
                                <!-- Password strength bar -->
                                <div id="pwStrengthWrap" style="margin-top:6px;display:none;">
                                    <div style="display:flex;gap:3px;margin-bottom:3px;">
                                        <div id="pws1" style="flex:1;height:3px;border-radius:99px;background:#dde8e2;transition:background .2s;"></div>
                                        <div id="pws2" style="flex:1;height:3px;border-radius:99px;background:#dde8e2;transition:background .2s;"></div>
                                        <div id="pws3" style="flex:1;height:3px;border-radius:99px;background:#dde8e2;transition:background .2s;"></div>
                                        <div id="pws4" style="flex:1;height:3px;border-radius:99px;background:#dde8e2;transition:background .2s;"></div>
                                    </div>
                                    <div id="pwStrengthLabel" style="font-size:10.5px;color:var(--bunhs-muted);"></div>
                                </div>
                                <div style="font-size:10.5px;color:var(--bunhs-muted);margin-top:3px;padding-left:2px;">
                                    Must include uppercase, lowercase, number &amp; special char.
                                </div>
                            </div>
                            <div class="bm-field">
                                <label class="bm-label" for="signupConfirmPassword">Confirm <span style="color:#e53935;">*</span></label>
                                <div class="bm-input-wrap">
                                    <i class="fas fa-lock bm-field-icon"></i>
                                    <input class="bm-input" type="password" id="signupConfirmPassword" name="confirm_password"
                                        placeholder="Repeat password" required
                                        autocomplete="new-password">
                                    <button type="button" class="bm-toggle-pwd" id="toggleSignupConfirmPwd" tabindex="-1">
                                        <i class="fas fa-eye" id="signupConfirmEyeIcon"></i>
                                    </button>
                                </div>
                                <div id="pwMatchHint" style="font-size:11px;margin-top:3px;padding-left:2px;display:none;"></div>
                            </div>
                        </div>
                        <!-- Password error indicators moved outside grid -->
                        <div id="signupPasswordError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Password is required</div>
                        <div id="signupConfirmPasswordError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Please confirm your password</div>
                        <div id="passwordMismatchError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">Passwords do not match</div>
                        <!-- Password Requirements - moved outside bm-row to span full width -->
                        <div id="signupPasswordRequirements" style="margin-top:6px;padding:8px;background:#f8f9fa;border-radius:6px;border:1px solid #e9ecef;width:100%;box-sizing:border-box;">
                            <div style="font-size:10.5px;font-weight:600;color:#495057;margin-bottom:5px;">Password Requirements:</div>
                            <div id="signupReqUppercase" style="font-size:10px;color:#6c757d;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                                <i class="fas fa-times-circle" style="color:#dc3545;font-size:9px;"></i> Must include at least one uppercase letter
                            </div>
                            <div id="signupReqLowercase" style="font-size:10px;color:#6c757d;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                                <i class="fas fa-times-circle" style="color:#dc3545;font-size:9px;"></i> Must include at least one lowercase letter
                            </div>
                            <div id="signupReqNumber" style="font-size:10px;color:#6c757d;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                                <i class="fas fa-times-circle" style="color:#dc3545;font-size:9px;"></i> Must include at least one number
                            </div>
                            <div id="signupReqSpecial" style="font-size:10px;color:#6c757d;margin-bottom:2px;display:flex;align-items:center;gap:4px;">
                                <i class="fas fa-times-circle" style="color:#dc3545;font-size:9px;"></i> Must include at least one special character
                            </div>
                        </div>

                        <div class="bm-err" id="signupErrBox">
                            <i class="fas fa-exclamation-circle"></i>
                            <span id="signupErrTxt"></span>
                        </div>

                        <div class="bm-terms">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">
                                I agree to the <a href="terms-of-service.html">Terms of Service</a>
                                and <a href="privacy.html">Privacy Policy</a>
                            </label>
                        </div>
                        <div id="termsError" style="color:#e53935;font-size:10.5px;margin-top:3px;margin-bottom:8px;display:none;">You must agree to the terms and conditions</div>

                        <button type="submit" class="bm-btn" id="signupSubmitBtn">
                            <span class="bm-btn-label"><i class="fas fa-paper-plane"></i>&ensp;Send Verification Code</span>
                            <div class="bm-spinner"></div>
                        </button>

                        <!-- ── Password strength helper — injected via dynamic script so it
                             executes even when this HTML is loaded via insertAdjacentHTML() ── -->
                    </form>
                </div>

                <!-- Step 2: OTP -->
                <div id="otpFormContainer" style="display:none;">
                    <div class="bm-panel" style="text-align:center;">
                        <div class="bm-shield-wrap" style="background:linear-gradient(135deg,rgba(201,168,76,.12),rgba(82,183,136,.08));border-color:rgba(201,168,76,.28);">
                            <i class="fas fa-mobile-alt" style="color:var(--bunhs-gold);"></i>
                        </div>
                        <p style="font-family:var(--bunhs-display);font-size:17px;font-weight:700;color:var(--bunhs-forest);margin:0 0 5px;">Verify Your Identity</p>
                        <p style="font-size:12.5px;color:var(--bunhs-muted);margin:0 0 12px;" id="otpSubtitle">Enter the 6-digit code we sent to your contact.</p>
                        <div style="margin-bottom:16px;">
                            <span class="bm-timer" id="signupTimer"><i class="fas fa-clock"></i> Expires in <span id="otpCountdown">05:00</span></span>
                        </div>

                        <form id="otpForm" novalidate onsubmit="return false;">
                            
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                            <input type="hidden" name="action" value="verify_signup_otp">
                            <div class="bm-otp-row" id="signupOtpBoxes">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                                <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                            </div>
                            <input type="hidden" id="signupOtpHidden" name="otp">

                            <div class="bm-err" id="otpErrBox">
                                <i class="fas fa-exclamation-circle"></i>
                                <span id="otpErrTxt"></span>
                            </div>

                            <button type="submit" class="bm-btn" id="otpVerifyBtn">
                                <span class="bm-btn-label"><i class="fas fa-check-double"></i>&ensp;Verify &amp; Create Account</span>
                                <div class="bm-spinner"></div>
                            </button>

                            <div style="margin-top:14px;">
                                <button type="button" class="bm-ghost" id="resendOtpBtn" disabled>
                                    Didn't receive it? Resend · <span id="resendTimer">30</span>s
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>


<!-- JS is intentionally kept in index.php so it runs after
     insertAdjacentHTML() has finished injecting this markup.
     Scripts inside fetched HTML don't auto-execute. -->