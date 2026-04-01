<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>News — Buyoan National High School</title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Favicons -->
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,900;1,400;1,700&family=Source+Sans+3:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

    <style>
        /* ── Design Tokens ─────────────────────────────────── */
        :root {
            --ink: #0f1117;
            --ink-soft: #3d4150;
            --ink-muted: #6e7280;
            --rule: #e2e4ea;
            --surface: #f7f8fc;
            --white: #ffffff;
            --accent: #1a56db;
            --accent-2: #e63946;
            --gold: #f4a118;
            /* Moss green palette for page-title */
            --moss: #3b5e3f;
            --moss-dark: #243b27;
            --moss-mid: #4e7a53;
            --moss-light: #7aab80;
            --font-head: 'Playfair Display', Georgia, serif;
            --font-body: 'Source Sans 3', sans-serif;
            --font-mono: 'Roboto Mono', monospace;
            --radius-sm: 4px;
            --radius-md: 10px;
            --shadow-card: 0 2px 16px rgba(15, 17, 23, .07);
            --shadow-hover: 0 8px 32px rgba(15, 17, 23, .13);
            --transition: .22s cubic-bezier(.4, 0, .2, 1);
        }

        body.news-page {
            font-family: var(--font-body);
            color: var(--ink);
            background: var(--white);
        }

        /* ── Page Title / Hero Banner — Moss Green ────────── */
        .page-title {
            background: linear-gradient(135deg, var(--moss-dark) 0%, var(--moss) 55%, var(--moss-mid) 100%);
            border-bottom: 3px solid var(--moss-light);
            padding: 48px 0 0;
            position: relative;
            overflow: hidden;
        }

        .page-title::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(ellipse at 20% 50%, rgba(122, 171, 128, .18) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(255, 255, 255, .04) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .page-title::before {
            content: 'NEWS';
            position: absolute;
            right: -20px;
            top: 50%;
            transform: translateY(-50%);
            font-family: var(--font-head);
            font-size: clamp(80px, 14vw, 180px);
            font-weight: 900;
            color: rgba(255, 255, 255, .05);
            letter-spacing: -4px;
            pointer-events: none;
            line-height: 1;
            z-index: 0;
        }

        .page-title .heading,
        .page-title .breadcrumbs {
            position: relative;
            z-index: 1;
        }

        .page-title .heading {
            padding-bottom: 0;
        }

        .page-title .heading-title {
            font-family: var(--font-head);
            font-size: clamp(2rem, 5vw, 3.2rem);
            font-weight: 700;
            color: var(--white);
            letter-spacing: -.5px;
            margin-bottom: 10px;
        }

        .page-title p {
            color: rgba(255, 255, 255, .65);
            font-size: 1rem;
            font-weight: 300;
        }

        .page-title .breadcrumbs {
            background: transparent;
            padding: 14px 0;
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, .08);
        }

        .page-title .breadcrumbs ol {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .page-title .breadcrumbs li {
            font-size: .78rem;
            font-family: var(--font-mono);
            color: rgba(255, 255, 255, .45);
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .page-title .breadcrumbs li::after {
            content: '/';
            margin-left: 8px;
        }

        .page-title .breadcrumbs li:last-child::after {
            display: none;
        }

        .page-title .breadcrumbs a {
            color: rgba(255, 255, 255, .55);
            text-decoration: none;
        }

        .page-title .breadcrumbs a:hover {
            color: var(--moss-light);
        }

        .page-title .breadcrumbs .current {
            color: var(--moss-light);
        }

        /* ── Section Spacing ───────────────────────────────── */
        .news-hero.section {
            padding: 52px 0 0;
        }

        .news-posts.section {
            padding: 48px 0;
        }

        .pagination-2.section {
            padding: 24px 0 56px;
        }

        /* ── Section Divider Label ─────────────────────────── */
        .section-label {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }

        .section-label .label-text {
            font-family: var(--font-mono);
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: var(--ink-muted);
            white-space: nowrap;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--rule);
        }

        /* ══════════════════════════════════════════════════
           HERO — FEATURED + SECONDARY POSTS
        ══════════════════════════════════════════════════ */

        /* Featured Post */
        .featured-post {
            position: relative;
            border-radius: var(--radius-md);
            overflow: hidden;
            background: var(--ink);
            box-shadow: var(--shadow-card);
            transition: box-shadow var(--transition);
        }

        .featured-post:hover {
            box-shadow: var(--shadow-hover);
        }

        .featured-post .image-container {
            position: relative;
            height: clamp(300px, 45vw, 440px);
            overflow: hidden;
        }

        .featured-post .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .5s ease;
        }

        .featured-post:hover .image-container img {
            transform: scale(1.04);
        }

        .featured-post .post-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(10, 12, 20, .92) 0%, rgba(10, 12, 20, .4) 70%, transparent 100%);
            padding: 32px 28px 20px;
        }

        .featured-post .post-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .category-tag {
            display: inline-block;
            background: var(--accent);
            color: #fff;
            font-family: var(--font-mono);
            font-size: .65rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .1em;
            padding: 3px 10px;
            border-radius: 2px;
        }

        .date-tag {
            font-family: var(--font-mono);
            font-size: .68rem;
            color: rgba(255, 255, 255, .55);
            letter-spacing: .06em;
        }

        .featured-post .post-title a {
            font-family: var(--font-head);
            font-size: clamp(1.25rem, 2.8vw, 1.9rem);
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            line-height: 1.25;
            display: block;
            transition: color var(--transition);
        }

        .featured-post .post-title a:hover {
            color: var(--gold);
        }

        .featured-post .post-excerpt {
            color: rgba(255, 255, 255, .7);
            font-size: .9rem;
            line-height: 1.55;
            margin: 8px 0 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .post-author {
            font-size: .8rem;
            color: rgba(255, 255, 255, .5);
        }

        .post-author a {
            color: rgba(255, 255, 255, .75);
            text-decoration: none;
            font-weight: 600;
        }

        .post-author a:hover {
            color: var(--gold);
        }

        /* Secondary Posts */
        .secondary-post {
            border-radius: var(--radius-md);
            overflow: hidden;
            background: var(--white);
            border: 1px solid var(--rule);
            box-shadow: var(--shadow-card);
            transition: box-shadow var(--transition), transform var(--transition);
        }

        .secondary-post:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }

        .secondary-post .post-image {
            position: relative;
            height: 180px;
            overflow: hidden;
        }

        .secondary-post .post-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .45s ease;
        }

        .secondary-post:hover .post-image img {
            transform: scale(1.06);
        }

        .secondary-post .post-content {
            padding: 16px 18px 14px;
        }

        .secondary-post .post-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .secondary-post .post-title a {
            font-family: var(--font-head);
            font-size: 1rem;
            font-weight: 700;
            color: var(--ink);
            text-decoration: none;
            line-height: 1.3;
            display: block;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: color var(--transition);
        }

        .secondary-post .post-title a:hover {
            color: var(--accent);
        }

        .secondary-post .post-author {
            font-size: .78rem;
            color: var(--ink-muted);
            margin-top: 6px;
        }

        .secondary-post .post-author a {
            color: var(--ink-soft);
            text-decoration: none;
            font-weight: 600;
        }

        /* ── Interaction Bar ───────────────────────────────── */
        .interaction-bar {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 8px 14px;
            background: var(--surface);
            border-top: 1px solid var(--rule);
        }

        .featured-post .interaction-bar {
            background: rgba(255, 255, 255, .08);
            border-top: 1px solid rgba(255, 255, 255, .1);
        }

        .interaction-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: .8rem;
            color: var(--ink-muted);
            padding: 5px 10px;
            border-radius: 20px;
            transition: background var(--transition), color var(--transition);
            font-family: var(--font-body);
        }

        .featured-post .interaction-btn {
            color: rgba(255, 255, 255, .6);
        }

        .interaction-btn:hover {
            background: rgba(26, 86, 219, .08);
            color: var(--accent);
        }

        .featured-post .interaction-btn:hover {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        .like-btn.liked {
            color: var(--accent-2) !important;
        }

        .like-btn.liked i {
            animation: heartPop .3s ease;
        }

        @keyframes heartPop {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.45);
            }

            100% {
                transform: scale(1);
            }
        }

        .interaction-btn .count {
            font-family: var(--font-mono);
            font-size: .68rem;
            font-weight: 500;
        }

        /* ── Comment Section (inline) ──────────────────────── */
        .comment-section {
            padding: 12px 16px;
            background: var(--white);
            border-top: 1px solid var(--rule);
            animation: slideDown .2s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .comment-input {
            width: 100%;
            padding: 9px 14px;
            border: 1.5px solid var(--rule);
            border-radius: 24px;
            font-family: var(--font-body);
            font-size: .85rem;
            color: var(--ink);
            background: var(--surface);
            transition: border-color var(--transition), box-shadow var(--transition);
            outline: none;
        }

        .comment-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(26, 86, 219, .12);
            background: var(--white);
        }

        .comments-list {
            max-height: 140px;
            overflow-y: auto;
            margin-top: 8px;
        }

        .comment-item {
            padding: 6px 0;
            border-bottom: 1px solid var(--surface);
            font-size: .82rem;
            color: var(--ink-soft);
            line-height: 1.4;
        }

        .comment-item:last-child {
            border-bottom: none;
        }

        /* ══════════════════════════════════════════════════
           SIDEBAR TABS
        ══════════════════════════════════════════════════ */
        .news-tabs {
            background: var(--white);
            border: 1px solid var(--rule);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-card);
            height: 100%;
        }

        .news-tabs .nav-tabs {
            display: flex;
            border-bottom: 2px solid var(--rule);
            background: var(--surface);
            padding: 0 4px;
            gap: 0;
        }

        .news-tabs .nav-tabs .nav-link {
            font-family: var(--font-mono);
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            font-weight: 500;
            color: var(--ink-muted);
            padding: 12px 16px;
            border: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            border-radius: 0;
            background: transparent;
            transition: color var(--transition), border-color var(--transition);
        }

        .news-tabs .nav-tabs .nav-link.active,
        .news-tabs .nav-tabs .nav-link:hover {
            color: var(--accent);
            border-bottom-color: var(--accent);
            background: transparent;
        }

        .news-tabs .tab-content {
            padding: 8px 0;
            max-height: 440px;
            overflow-y: auto;
        }

        .tab-post {
            padding: 12px 16px;
            border-bottom: 1px solid var(--rule);
            transition: background var(--transition);
        }

        .tab-post:last-child {
            border-bottom: none;
        }

        .tab-post:hover {
            background: var(--surface);
        }

        .tab-post img {
            width: 100%;
            height: 72px;
            object-fit: cover;
            border-radius: var(--radius-sm);
        }

        .tab-post .post-content {
            padding-left: 12px;
        }

        .tab-post .category {
            font-family: var(--font-mono);
            font-size: .6rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--accent);
            font-weight: 500;
            display: block;
            margin-bottom: 4px;
        }

        .tab-post .post-title {
            font-family: var(--font-head);
            font-size: .88rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .tab-post .post-title a {
            color: var(--ink);
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: color var(--transition);
        }

        .tab-post .post-title a:hover {
            color: var(--accent);
        }

        .tab-post .post-author {
            font-size: .72rem;
            color: var(--ink-muted);
        }

        .tab-post .post-author a {
            color: var(--ink-soft);
            text-decoration: none;
            font-weight: 600;
        }

        /* ══════════════════════════════════════════════════
           NEWS POSTS GRID
        ══════════════════════════════════════════════════ */
        .post-box {
            background: var(--white);
            border: 1px solid var(--rule);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: box-shadow var(--transition), transform var(--transition);
        }

        .post-box:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-4px);
        }

        .post-img {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .post-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .45s ease;
        }

        .post-box:hover .post-img img {
            transform: scale(1.07);
        }

        /* Category ribbon on post card */
        .post-box .post-category-ribbon {
            position: absolute;
            top: 14px;
            left: 14px;
            background: var(--accent);
            color: #fff;
            font-family: var(--font-mono);
            font-size: .6rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            padding: 3px 9px;
            border-radius: 2px;
        }

        .post-box .meta {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 14px 16px 0;
            font-size: .73rem;
            color: var(--ink-muted);
            font-family: var(--font-mono);
        }

        .post-box .meta .post-date {
            font-weight: 500;
        }

        .post-box .meta .post-author {
            color: var(--ink-soft);
            font-weight: 500;
        }

        .post-box .post-title {
            font-family: var(--font-head);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ink);
            padding: 8px 16px 0;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .post-box .excerpt {
            font-size: .85rem;
            color: var(--ink-soft);
            line-height: 1.55;
            padding: 6px 16px 0;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .post-box .readmore {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .78rem;
            font-family: var(--font-mono);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--accent);
            text-decoration: none;
            padding: 12px 16px 16px;
            transition: gap var(--transition), color var(--transition);
        }

        .post-box .readmore:hover {
            gap: 10px;
            color: var(--ink);
        }

        .post-box .readmore i {
            font-size: .9rem;
        }

        /* ══════════════════════════════════════════════════
           NEWS MODAL (Read More)
        ══════════════════════════════════════════════════ */
        .news-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 12, 20, .72);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1050;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            animation: fadeIn .2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .news-modal {
            background: var(--white);
            border-radius: 12px;
            max-width: 640px;
            width: 92%;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 24px 72px rgba(10, 12, 20, .35);
            animation: modalUp .25s cubic-bezier(.34, 1.56, .64, 1);
        }

        @keyframes modalUp {
            from {
                opacity: 0;
                transform: translateY(28px) scale(.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .close-modal {
            position: sticky;
            top: 14px;
            float: right;
            margin: 14px 14px 0 0;
            background: rgba(15, 17, 23, .55);
            border: none;
            font-size: 20px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: background var(--transition);
            line-height: 1;
        }

        .close-modal:hover {
            background: rgba(15, 17, 23, .85);
        }

        .modal-image {
            margin-top: -50px;
        }

        .modal-image img {
            width: 100%;
            height: 260px;
            object-fit: cover;
            border-radius: 12px 12px 0 0;
        }

        .modal-body-content {
            padding: 24px 28px 20px;
        }

        .modal-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .modal-title {
            font-family: var(--font-head);
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.25;
            margin-bottom: 10px;
        }

        .modal-text {
            font-size: .92rem;
            color: var(--ink-soft);
            line-height: 1.7;
            margin-bottom: 0;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-start;
            gap: 6px;
            padding: 14px 28px;
            border-top: 1px solid var(--rule);
            border-bottom: 1px solid var(--rule);
            background: var(--surface);
        }

        .action-btn {
            background: var(--white);
            border: 1.5px solid var(--rule);
            cursor: pointer;
            font-size: .8rem;
            color: var(--ink-soft);
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 24px;
            font-family: var(--font-body);
            font-weight: 500;
            transition: all var(--transition);
        }

        .action-btn:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .action-btn.liked-action {
            color: var(--accent-2);
            border-color: var(--accent-2);
        }

        .comments-section {
            padding: 16px 28px 0;
        }

        .comments-section h6 {
            font-family: var(--font-mono);
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--ink-muted);
            margin-bottom: 14px;
        }

        .comment-item-modal {
            display: flex;
            gap: 12px;
            margin-bottom: 14px;
            align-items: flex-start;
        }

        .comment-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .8rem;
            font-weight: 700;
            font-family: var(--font-body);
        }

        .comment-user {
            font-weight: 700;
            font-size: .82rem;
            color: var(--ink);
        }

        .comment-text-modal {
            font-size: .83rem;
            color: var(--ink-soft);
            margin: 3px 0;
            line-height: 1.45;
        }

        .comment-meta-line {
            font-size: .72rem;
            color: var(--ink-muted);
            display: flex;
            gap: 10px;
            font-family: var(--font-mono);
        }

        .comment-meta-line a {
            color: var(--ink-muted);
            text-decoration: none;
        }

        .comment-meta-line a:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        .add-comment {
            display: flex;
            gap: 10px;
            padding: 16px 28px 24px;
            border-top: 1px solid var(--rule);
            align-items: center;
        }

        .add-comment input {
            flex: 1;
            padding: 9px 16px;
            border: 1.5px solid var(--rule);
            border-radius: 24px;
            font-family: var(--font-body);
            font-size: .85rem;
            color: var(--ink);
            background: var(--surface);
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        .add-comment input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(26, 86, 219, .1);
            background: var(--white);
        }

        .add-comment button {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 24px;
            font-family: var(--font-body);
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            transition: background var(--transition), transform var(--transition);
            white-space: nowrap;
        }

        .add-comment button:hover {
            background: #1246b5;
            transform: scale(1.03);
        }

        /* ══════════════════════════════════════════════════
           PAGINATION
        ══════════════════════════════════════════════════ */
        .pagination-2 nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pagination-2 nav ul li a {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 14px;
            border: 1.5px solid var(--rule);
            border-radius: var(--radius-sm);
            color: var(--ink-soft);
            text-decoration: none;
            font-size: .82rem;
            font-family: var(--font-mono);
            font-weight: 500;
            transition: all var(--transition);
            background: var(--white);
        }

        .pagination-2 nav ul li a:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(26, 86, 219, .05);
        }

        .pagination-2 nav ul li a.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .pagination-2 .ellipsis {
            color: var(--ink-muted);
            font-size: .9rem;
            padding: 0 4px;
            line-height: 38px;
        }

        /* ── Scrollbar Styling ─────────────────────────────── */
        .news-tabs .tab-content::-webkit-scrollbar,
        .comments-list::-webkit-scrollbar,
        .comments-section::-webkit-scrollbar {
            width: 4px;
        }

        .news-tabs .tab-content::-webkit-scrollbar-track,
        .comments-list::-webkit-scrollbar-track {
            background: var(--surface);
        }

        .news-tabs .tab-content::-webkit-scrollbar-thumb,
        .comments-list::-webkit-scrollbar-thumb {
            background: var(--rule);
            border-radius: 2px;
        }

        /* ── Empty state ────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--ink-muted);
            font-size: .9rem;
        }

        .empty-state i {
            font-size: 2rem;
            display: block;
            margin-bottom: 10px;
            opacity: .4;
        }

        /* Article Modal Styles */
        .article-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .article-modal-content {
            background: white;
            border-radius: var(--radius-lg);
            max-width: 800px;
            max-height: 90vh;
            width: 90%;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .article-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--rule);
        }

        .modal-article-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin: 0;
            line-height: 1.3;
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--ink-muted);
            cursor: pointer;
            padding: 8px;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .modal-close-btn:hover {
            background: var(--surface);
            color: var(--accent-2);
        }

        .article-modal-body {
            padding: 24px;
        }

        .modal-article-image {
            width: 100%;
            height: 300px;
            overflow: hidden;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }

        .modal-article-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-article-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .modal-category {
            background: var(--accent);
            color: white;
            padding: 4px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .modal-date, .modal-author {
            color: var(--ink-muted);
            font-size: 0.9rem;
        }

        .modal-article-content {
            line-height: 1.7;
            color: var(--ink);
            margin-bottom: 24px;
            font-size: 1.05rem;
        }

        .modal-article-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            padding-top: 20px;
            border-top: 1px solid var(--rule);
        }

        .modal-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 16px;
            border: 1px solid var(--rule);
            background: var(--surface);
            color: var(--ink);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
            min-width: 100px;
        }

        /* Messages Section */
        .modal-messages-section {
            border-top: 1px solid var(--rule);
            padding: 20px;
            background: var(--surface);
        }

        .modal-messages-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .modal-messages-header h4 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--ink);
        }

        .message-count {
            background: var(--accent);
            color: white;
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 500;
        }

        .modal-messages-container {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 15px;
            border: 1px solid var(--rule);
            border-radius: var(--radius-sm);
            background: white;
        }

        .message-item {
            padding: 12px;
            border-bottom: 1px solid var(--rule);
            display: flex;
            gap: 10px;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .message-content {
            flex: 1;
        }

        .message-author {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .message-text {
            color: var(--ink-soft);
            line-height: 1.4;
            margin-bottom: 4px;
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--ink-muted);
        }

        .no-messages {
            text-align: center;
            padding: 40px 20px;
            color: var(--ink-muted);
            font-style: italic;
        }

        .message-input-container {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }

        .message-input {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--rule);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
        }

        .send-message-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .send-message-btn:hover {
            background: var(--accent-2);
        }

        .see-more-container {
            text-align: center;
        }

        .see-more-btn {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .see-more-btn:hover {
            background: var(--accent);
            color: white;
        }

        .modal-action-btn:hover {
            background: var(--accent);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .modal-action-btn.like-btn:hover {
            background: #e91e63;
        }

        .modal-action-btn.comment-btn:hover {
            background: #2196f3;
        }

        .modal-action-btn.share-btn:hover {
            background: #4caf50;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Article Expand/Collapse Styles */
        .post-excerpt-container {
            position: relative;
            margin-bottom: 10px;
        }

        .post-full-content, .full-content {
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .read-more-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 5px;
        }

        .read-more-btn:hover {
            background: var(--accent-2);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .read-more-text, .show-less-text {
            transition: opacity 0.3s ease;
        }

        .show-less-text {
            color: #ff6b6b;
        }

        .expanded .post-excerpt-container {
            display: none;
        }

        .expanded .post-full-content, .expanded .full-content {
            display: block;
            animation: fadeIn 0.4s ease;
        }

        .expanded .read-more-text {
            display: none;
        }

        .expanded .show-less-text {
            display: inline;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── Responsive ─────────────────────────────────────── */
        @media (max-width: 768px) {
            .featured-post .image-container {
                height: 260px;
            }

            .news-modal {
                width: 96%;
                max-height: 92vh;
            }

            .modal-body-content {
                padding: 18px 20px 16px;
            }

            .add-comment {
                padding: 14px 20px 20px;
            }

            .comments-section {
                padding: 14px 20px 0;
            }

            .modal-actions {
                padding: 12px 20px;
                gap: 8px;
                flex-wrap: wrap;
            }

            .news-filter-bar {
                gap: 8px;
            }
        }

        /* ── Toast Notifications ───────────────────────────── */
        #toast-container {
            position: fixed;
            bottom: 28px;
            right: 28px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast-msg {
            background: var(--ink);
            color: #fff;
            font-family: var(--font-body);
            font-size: .83rem;
            font-weight: 500;
            padding: 11px 18px;
            border-radius: 8px;
            box-shadow: 0 6px 24px rgba(10, 12, 20, .28);
            display: flex;
            align-items: center;
            gap: 9px;
            animation: toastIn .28s cubic-bezier(.34, 1.56, .64, 1) forwards;
            pointer-events: auto;
            max-width: 300px;
        }

        .toast-msg.toast-success {
            border-left: 3px solid #22c55e;
        }

        .toast-msg.toast-info {
            border-left: 3px solid var(--accent);
        }

        .toast-msg.toast-heart {
            border-left: 3px solid var(--accent-2);
        }

        .toast-msg.hiding {
            animation: toastOut .2s ease forwards;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateX(20px) scale(.95);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        @keyframes toastOut {
            from {
                opacity: 1;
                transform: translateX(0) scale(1);
            }

            to {
                opacity: 0;
                transform: translateX(20px) scale(.95);
            }
        }

        /* ── News Search / Filter Bar ──────────────────────── */
        .news-filter-bar {
            background: var(--surface);
            border: 1px solid var(--rule);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .news-search-wrap {
            position: relative;
            flex: 1;
            min-width: 180px;
        }

        .news-search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ink-muted);
            font-size: .85rem;
        }

        .news-search-input {
            width: 100%;
            padding: 8px 12px 8px 34px;
            border: 1.5px solid var(--rule);
            border-radius: 24px;
            font-family: var(--font-body);
            font-size: .85rem;
            color: var(--ink);
            background: var(--white);
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        .news-search-input:focus {
            border-color: var(--moss);
            box-shadow: 0 0 0 3px rgba(59, 94, 63, .12);
        }

        .filter-label {
            font-family: var(--font-mono);
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--ink-muted);
            white-space: nowrap;
        }

        .sort-select {
            padding: 8px 14px;
            border: 1.5px solid var(--rule);
            border-radius: 24px;
            font-family: var(--font-body);
            font-size: .82rem;
            color: var(--ink-soft);
            background: var(--white);
            outline: none;
            cursor: pointer;
            transition: border-color var(--transition);
        }

        .sort-select:focus {
            border-color: var(--moss);
        }

        .results-count {
            font-family: var(--font-mono);
            font-size: .68rem;
            color: var(--ink-muted);
            white-space: nowrap;
            margin-left: auto;
        }

        /* ── Read-time Badge ───────────────────────────────── */
        .read-time-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: var(--font-mono);
            font-size: .6rem;
            color: var(--ink-muted);
            background: var(--surface);
            border: 1px solid var(--rule);
            padding: 2px 8px;
            border-radius: 12px;
        }

        /* ── No Results ────────────────────────────────────── */
        .no-results-msg {
            display: none;
            text-align: center;
            padding: 48px 20px;
            color: var(--ink-muted);
            font-size: .95rem;
            width: 100%;
        }

        .no-results-msg i {
            font-size: 2.2rem;
            display: block;
            margin-bottom: 10px;
            opacity: .35;
        }

        /* ── Comment reply indent ──────────────────────────── */
        .reply-thread {
            margin-top: 4px;
        }

        .reply-item {
            display: flex;
            gap: 8px;
            margin-left: 46px;
            padding: 5px 0;
            border-bottom: 1px solid var(--surface);
            font-size: .79rem;
            color: var(--ink-soft);
        }

        .reply-item:last-child {
            border-bottom: none;
        }

        .reply-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--moss) 0%, var(--moss-light) 100%);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
        }

        .reply-input-wrap {
            display: none;
            margin-top: 6px;
            margin-left: 46px;
            gap: 8px;
            align-items: center;
        }

        .reply-input-wrap.open {
            display: flex;
        }

        .reply-input-wrap input {
            flex: 1;
            padding: 6px 12px;
            border: 1.5px solid var(--rule);
            border-radius: 20px;
            font-size: .8rem;
            font-family: var(--font-body);
            outline: none;
            background: var(--surface);
            transition: border-color var(--transition);
        }

        .reply-input-wrap input:focus {
            border-color: var(--accent);
            background: var(--white);
        }

        .reply-input-wrap button {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: .75rem;
            font-family: var(--font-body);
            font-weight: 600;
            cursor: pointer;
        }

        /* ── Modal image skeleton loader ───────────────────── */
        .modal-img-skeleton {
            width: 100%;
            height: 260px;
            background: linear-gradient(90deg, #e2e4ea 25%, #f7f8fc 50%, #e2e4ea 75%);
            background-size: 200% 100%;
            animation: skeleton .9s ease infinite;
        }

        @keyframes skeleton {
            from {
                background-position: 200% 0;
            }

            to {
                background-position: -200% 0;
            }
        }
    </style>

    <!-- =======================================================
  * Template Name: MySchool
  * Template URL: https://bootstrapmade.com/myschool-bootstrap-school-template/
  * Updated: Jul 28 2025 with Bootstrap v5.3.7
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
</head>

<?php include 'db_connection.php'; ?>

<body class="news-page">

    <header id="header" class="header d-flex align-items-center sticky-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">

            <a href="index.html" class="logo d-flex align-items-center">
                <img src="assets/img/Bagong_Pilipinas_logo.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 20px;">
                <img src="assets/img/DepED logo circle.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 0px;">
                <img src="assets/img/logo.jpg" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 50px;">
                <h4 class="sitename mb-0">Buyoan National HighSchool</h4>
            </a>

            <div id="nav-placeholder"></div>

        </div>
    </header>

    <main class="main">

        <!-- Page Title -->
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">News &amp; Announcements</h1>
                            <p class="mb-0">
                                Latest news and announcements from Buyoan National High School.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="index.html">Home</a></li>
                        <li class="current">News</li>
                    </ol>
                </div>
            </nav>
        </div><!-- End Page Title -->

        <!-- News Hero Section -->
        <section id="news-hero" class="news-hero section">
            <div class="container">
                <div class="section-label">
                    <span class="label-text">Top Stories</span>
                </div>
                <div class="row g-4">
                    <!-- Main Content Area -->
                    <div class="col-lg-8">
                        <?php include 'admin_account/announcements/hero_dynamic.php'; ?>
                    </div>

                    <!-- Sidebar with Tabs -->
                    <div class="col-lg-4">
                        <div class="news-tabs">
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#top-stories" type="button">Latest</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#trending" type="button">Trending</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#latest" type="button">More</button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <?php include 'admin_account/announcements/sidebar_dynamic.php'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- News Posts Section -->
        <section id="news-posts" class="news-posts section">
            <div class="container">
                <div class="section-label">
                    <span class="label-text">More Stories</span>
                </div>

                <!-- Search & Filter Bar -->
                <div class="news-filter-bar">
                    <div class="news-search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" class="news-search-input" id="news-search"
                            placeholder="Search stories…" autocomplete="off">
                    </div>
                    <span class="filter-label">Sort:</span>
                    <select class="sort-select" id="news-sort">
                        <option value="default">Latest</option>
                        <option value="title">A → Z</option>
                        <option value="likes">Most Liked</option>
                    </select>
                    <span class="results-count" id="results-count"></span>
                </div>

                <div class="row gy-4 gx-3" id="posts-grid">
                    <?php include 'admin_account/announcements/news_posts_dynamic.php'; ?>
                </div>
                <div class="no-results-msg" id="no-results">
                    <i class="bi bi-newspaper"></i>
                    No stories match your search.
                </div>
            </div>
        </section>

        <!-- Pagination -->
        <section id="pagination-2" class="pagination-2 section">
            <div class="container">
                <nav aria-label="Page navigation">
                    <ul>
                        <li>
                            <a href="#" aria-label="Previous page">
                                <i class="bi bi-arrow-left"></i>
                                <span class="d-none d-sm-inline ms-1">Prev</span>
                            </a>
                        </li>
                        <li><a href="#" class="active">1</a></li>
                        <li><a href="#">2</a></li>
                        <li><a href="#">3</a></li>
                        <li class="ellipsis">…</li>
                        <li><a href="#">8</a></li>
                        <li><a href="#">9</a></li>
                        <li><a href="#">10</a></li>
                        <li>
                            <a href="#" aria-label="Next page">
                                <span class="d-none d-sm-inline me-1">Next</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </section>

    </main>

    <!-- Footer Placeholder -->
    <div id="footer-placeholder"></div>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <!-- Preloader -->
    <div id="preloader"></div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>

    <!-- Include Navigation -->
    <script>
        fetch('nav.php')
            .then(r => r.text())
            .then(d => document.getElementById('nav-placeholder').innerHTML = d)
            .catch(e => console.error('Nav error:', e));
    </script>

    <!-- Include Footer -->
    <script>
        fetch('footer.php')
            .then(r => r.text())
            .then(d => document.getElementById('footer-placeholder').innerHTML = d)
            .catch(e => console.error('Footer error:', e));
    </script>

    <!-- Include Modals -->
    <script>
        fetch('modals.php')
            .then(r => r.text())
            .then(d => {
                document.body.insertAdjacentHTML('beforeend', d);
                document.addEventListener('DOMContentLoaded', function() {
                    const loginBtn = document.querySelector('.btn-login');
                    const signupBtn = document.querySelector('.btn-signup');
                    if (loginBtn) {
                        loginBtn.addEventListener('click', e => {
                            e.preventDefault();
                            new bootstrap.Modal(document.getElementById('loginModal')).show();
                        });
                    }
                    if (signupBtn) {
                        signupBtn.addEventListener('click', e => {
                            e.preventDefault();
                            new bootstrap.Modal(document.getElementById('signupModal')).show();
                        });
                    }
                });
            })
            .catch(e => console.error('Modals error:', e));
    </script>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- ── News Read-More Modal ── -->
    <div id="news-modal-overlay" class="news-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-title-text">
        <div class="news-modal">
            <button class="close-modal" onclick="closeNewsModal()" aria-label="Close">&times;</button>
            <div class="modal-image">
                <div id="modal-img-skeleton" class="modal-img-skeleton"></div>
                <img id="modal-image" src="" alt="" style="display:none;">
            </div>
            <div class="modal-body-content">
                <div class="modal-meta">
                    <span class="category-tag" id="modal-category"></span>
                    <span class="date-tag" id="modal-date"></span>
                    <span class="read-time-badge ms-auto">
                        <i class="bi bi-clock"></i>
                        <span id="modal-read-time"></span>
                    </span>
                </div>
                <h2 class="modal-title" id="modal-title-text"></h2>
                <p class="modal-text" id="modal-text"></p>
            </div>
            <div class="modal-actions">
                <button class="action-btn" id="modal-like-btn" onclick="toggleLikeModal()">
                    <i class="far fa-heart"></i>
                    <span id="modal-like-label">Like</span>
                    <span id="modal-like-count" style="font-family:var(--font-mono);font-size:.72rem;opacity:.65;"></span>
                </button>
                <button class="action-btn" onclick="focusCommentInput()">
                    <i class="far fa-comment"></i> Comment
                </button>
                <button class="action-btn" onclick="shareModal()">
                    <i class="fas fa-share-alt"></i> Share
                </button>
            </div>
            <div class="comments-section">
                <h6>Comments</h6>
                <div id="comments-list"></div>
            </div>
            <div class="add-comment">
                <input type="text" id="comment-input" placeholder="Write a comment…" onkeypress="addCommentModal(event)">
                <button onclick="submitCommentModal()">Post</button>
            </div>
        </div>
    </div>

    <script>
        /* ══════════════════════════════════════════════════════
           STATE
        ══════════════════════════════════════════════════════ */
        let currentPostId = null;
        let commentsData = {}; // { postId: [ {user, text, timestamp, replies:[]} ] }
        let likesData = {}; // { postId: true/false }
        let activeReplyId = null; // comment index currently replying to

        /* ══════════════════════════════════════════════════════
           TOAST
        ══════════════════════════════════════════════════════ */
        function showToast(msg, type = 'info', icon = '') {
            const c = document.getElementById('toast-container');
            const el = document.createElement('div');
            el.className = `toast-msg toast-${type}`;
            el.innerHTML = icon ? `<i class="${icon}"></i> ${msg}` : msg;
            c.appendChild(el);
            setTimeout(() => {
                el.classList.add('hiding');
                el.addEventListener('animationend', () => el.remove(), {
                    once: true
                });
            }, 2800);
        }

        /* ══════════════════════════════════════════════════════
           READ-TIME HELPER
        ══════════════════════════════════════════════════════ */
        function calcReadTime(text) {
            const words = (text || '').split(/\s+/).filter(Boolean).length;
            return Math.max(1, Math.ceil(words / 200));
        }

        /* ══════════════════════════════════════════════════════
           OPEN / CLOSE MODAL
        ══════════════════════════════════════════════════════ */
        function openNewsModal(postId) {
            currentPostId = postId;
            const bar = document.querySelector(`.interaction-bar[data-post-id="${postId}"]`);
            const box = bar ? bar.closest('.post-box') : null;
            if (!box) return;

            const img = box.querySelector('.post-img img');
            const titleEl = box.querySelector('.post-title');
            const excerpt = box.querySelector('.excerpt');
            const catRib = box.querySelector('.post-category-ribbon');
            const metaDate = box.querySelector('.meta .post-date');
            const fullText = excerpt ? excerpt.textContent.replace(/…$/, '').trim() : '';
            const mins = calcReadTime(fullText);

            /* Image with skeleton */
            const imgEl = document.getElementById('modal-image');
            imgEl.style.display = 'none';
            document.getElementById('modal-img-skeleton').style.display = 'block';
            if (img && img.src) {
                const tmp = new Image();
                tmp.onload = () => {
                    imgEl.src = tmp.src;
                    imgEl.style.display = 'block';
                    document.getElementById('modal-img-skeleton').style.display = 'none';
                };
                tmp.src = img.src;
            }

            document.getElementById('modal-title-text').textContent = titleEl ? titleEl.textContent.trim() : '';
            document.getElementById('modal-text').textContent = fullText;
            document.getElementById('modal-category').textContent = catRib ? catRib.textContent.trim() : '';
            document.getElementById('modal-date').textContent = metaDate ? metaDate.textContent.trim() : '';
            document.getElementById('modal-read-time').textContent = `${mins} min read`;

            /* Sync like state */
            const likeBtn = document.getElementById('modal-like-btn');
            if (likesData[postId]) {
                likeBtn.classList.add('liked-action');
                document.getElementById('modal-like-label').textContent = 'Liked ♥';
            } else {
                likeBtn.classList.remove('liked-action');
                document.getElementById('modal-like-label').textContent = 'Like';
            }

            /* Like count display */
            const likeCount = document.getElementById(`like-count-${postId}`);
            document.getElementById('modal-like-count').textContent =
                likeCount ? `${likeCount.textContent}` : '0';

            loadCommentsModal();
            document.getElementById('news-modal-overlay').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('news-modal-overlay').focus?.(), 100);
        }

        function closeNewsModal() {
            const overlay = document.getElementById('news-modal-overlay');
            overlay.style.display = 'none';
            document.body.style.overflow = '';
            currentPostId = null;
            activeReplyId = null;
        }

        /* ══════════════════════════════════════════════════════
           COMMENTS
        ══════════════════════════════════════════════════════ */
        function loadCommentsModal() {
            const list = document.getElementById('comments-list');
            list.innerHTML = '';
            const pid = currentPostId;

            if (!commentsData[pid]) {
                commentsData[pid] = [{
                        user: 'John Doe',
                        text: 'Great post! Very informative.',
                        timestamp: '2 hours ago',
                        replies: []
                    },
                    {
                        user: 'Jane Smith',
                        text: 'Thanks for sharing this news.',
                        timestamp: '1 hour ago',
                        replies: []
                    }
                ];
            }

            const total = commentsData[pid].reduce((acc, c) => acc + 1 + (c.replies?.length || 0), 0);
            document.querySelector('.comments-section h6').textContent =
                `Comments (${total})`;

            commentsData[pid].forEach((c, idx) => {
                /* Main comment */
                const el = document.createElement('div');
                el.className = 'comment-item-modal';
                el.dataset.idx = idx;
                el.innerHTML = `
                    <div class="comment-avatar">${c.user.charAt(0).toUpperCase()}</div>
                    <div class="comment-content" style="flex:1;">
                        <div class="comment-user">${escHtml(c.user)}</div>
                        <div class="comment-text-modal">${escHtml(c.text)}</div>
                        <div class="comment-meta-line">
                            <a href="#" onclick="likeComment(event,${idx})">Like</a>
                            <a href="#" onclick="openReply(event,${idx})">Reply</a>
                            <span>${c.timestamp}</span>
                            ${c.likeCount ? `<span>♥ ${c.likeCount}</span>` : ''}
                        </div>
                        <div class="reply-input-wrap" id="reply-wrap-${idx}">
                            <input type="text" placeholder="Write a reply…"
                                   onkeypress="submitReply(event,${idx})">
                            <button onclick="submitReplyBtn(${idx})">Reply</button>
                        </div>
                        <div class="reply-thread" id="replies-${idx}"></div>
                    </div>`;
                list.appendChild(el);

                /* Replies */
                const replyContainer = el.querySelector(`#replies-${idx}`);
                (c.replies || []).forEach(r => {
                    const re = document.createElement('div');
                    re.className = 'reply-item';
                    re.innerHTML = `
                        <div class="reply-avatar">${r.user.charAt(0).toUpperCase()}</div>
                        <div><span style="font-weight:700;font-size:.78rem;">${escHtml(r.user)}</span>
                        <span style="margin-left:6px;">${escHtml(r.text)}</span>
                        <div style="font-size:.65rem;color:var(--ink-muted);font-family:var(--font-mono);margin-top:2px;">${r.timestamp}</div></div>`;
                    replyContainer.appendChild(re);
                });
            });
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function openReply(e, idx) {
            e.preventDefault();
            /* close any open reply box */
            document.querySelectorAll('.reply-input-wrap.open').forEach(w => w.classList.remove('open'));
            const wrap = document.getElementById(`reply-wrap-${idx}`);
            if (wrap) {
                wrap.classList.add('open');
                wrap.querySelector('input').focus();
            }
            activeReplyId = idx;
        }

        function submitReply(e, idx) {
            if (e.key === 'Enter') submitReplyBtn(idx);
        }

        function submitReplyBtn(idx) {
            const wrap = document.getElementById(`reply-wrap-${idx}`);
            const input = wrap?.querySelector('input');
            const text = input?.value.trim();
            if (!text || !currentPostId) return;
            if (!commentsData[currentPostId][idx].replies)
                commentsData[currentPostId][idx].replies = [];
            commentsData[currentPostId][idx].replies.push({
                user: 'You',
                text,
                timestamp: 'Just now'
            });
            input.value = '';
            wrap.classList.remove('open');
            loadCommentsModal();
            updateCommentCountCard(currentPostId);
        }

        function likeComment(e, idx) {
            e.preventDefault();
            if (!currentPostId) return;
            const c = commentsData[currentPostId][idx];
            c.likeCount = (c.likeCount || 0) + 1;
            loadCommentsModal();
        }

        function addCommentModal(e) {
            if (e.key === 'Enter') submitCommentModal();
        }

        function submitCommentModal() {
            const input = document.getElementById('comment-input');
            const text = input.value.trim();
            if (!text || !currentPostId) return;
            if (!commentsData[currentPostId]) commentsData[currentPostId] = [];
            commentsData[currentPostId].push({
                user: 'You',
                text,
                timestamp: 'Just now',
                replies: []
            });
            input.value = '';
            loadCommentsModal();
            updateCommentCountCard(currentPostId);
            showToast('Comment posted!', 'success', 'bi bi-chat-dots');
        }

        function focusCommentInput() {
            document.getElementById('comment-input').focus();
        }

        function updateCommentCountCard(postId) {
            const allCounts = document.querySelectorAll(`#comment-count-${postId}`);
            const total = commentsData[postId] ?
                commentsData[postId].reduce((a, c) => a + 1 + (c.replies?.length || 0), 0) :
                0;
            allCounts.forEach(el => el.textContent = total);
        }

        /* ══════════════════════════════════════════════════════
           LIKES
        ══════════════════════════════════════════════════════ */
        function toggleLike(postId) {
            const btns = document.querySelectorAll(`.interaction-bar[data-post-id="${postId}"] .like-btn`);
            const counts = document.querySelectorAll(`#like-count-${postId}`);
            const liked = !!likesData[postId];

            if (liked) {
                likesData[postId] = false;
                btns.forEach(b => b.classList.remove('liked'));
                counts.forEach(c => c.textContent = Math.max(0, parseInt(c.textContent) - 1));
                localStorage.removeItem(`liked-${postId}`);
                showToast('Like removed', 'info', 'bi bi-heart');
            } else {
                likesData[postId] = true;
                btns.forEach(b => b.classList.add('liked'));
                counts.forEach(c => c.textContent = parseInt(c.textContent) + 1);
                localStorage.setItem(`liked-${postId}`, 'true');
                showToast('You liked this post!', 'heart', 'bi bi-heart-fill');
            }
            /* Sync modal if open */
            if (currentPostId == postId) {
                const likeBtn = document.getElementById('modal-like-btn');
                const labelEl = document.getElementById('modal-like-label');
                if (likesData[postId]) {
                    likeBtn?.classList.add('liked-action');
                    if (labelEl) labelEl.textContent = 'Liked ♥';
                } else {
                    likeBtn?.classList.remove('liked-action');
                    if (labelEl) labelEl.textContent = 'Like';
                }
            }
        }

        function toggleLikeModal() {
            if (!currentPostId) return;
            toggleLike(currentPostId);
        }

        /* ══════════════════════════════════════════════════════
           SHARE
        ══════════════════════════════════════════════════════ */
        function sharePost(postId, title) {
            const url = location.href.split('?')[0] + `?post=${postId}`;
            if (navigator.share) {
                navigator.share({
                    title,
                    url
                });
            } else {
                navigator.clipboard.writeText(url).then(() =>
                    showToast('Link copied to clipboard!', 'success', 'bi bi-clipboard-check'));
            }
        }

        function shareModal() {
            const title = document.getElementById('modal-title-text').textContent;
            sharePost(currentPostId, title);
        }

        /* ══════════════════════════════════════════════════════
           SEARCH & FILTER
        ══════════════════════════════════════════════════════ */
        function initSearchFilter() {
            const searchInput = document.getElementById('news-search');
            const sortSelect = document.getElementById('news-sort');
            const grid = document.getElementById('posts-grid');
            const noResults = document.getElementById('no-results');
            const countEl = document.getElementById('results-count');
            if (!searchInput || !grid) return;

            const allCards = () => Array.from(grid.querySelectorAll('.col-xl-3, .col-md-6'));

            function applyFilter() {
                const query = searchInput.value.trim().toLowerCase();
                const sort = sortSelect?.value || 'default';
                let cards = allCards();
                let visible = 0;

                cards.forEach(col => {
                    const title = col.querySelector('.post-title')?.textContent.toLowerCase() || '';
                    const excerpt = col.querySelector('.excerpt')?.textContent.toLowerCase() || '';
                    const match = !query || title.includes(query) || excerpt.includes(query);
                    col.style.display = match ? '' : 'none';
                    if (match) visible++;
                });

                /* Sort visible cards */
                if (sort !== 'default') {
                    const visibleCols = cards.filter(c => c.style.display !== 'none');
                    visibleCols.sort((a, b) => {
                        if (sort === 'title') {
                            return (a.querySelector('.post-title')?.textContent || '')
                                .localeCompare(b.querySelector('.post-title')?.textContent || '');
                        }
                        if (sort === 'likes') {
                            const la = parseInt(a.querySelector('.like-count')?.textContent || 0);
                            const lb = parseInt(b.querySelector('.like-count')?.textContent || 0);
                            return lb - la;
                        }
                        return 0;
                    });
                    visibleCols.forEach(c => grid.appendChild(c));
                }

                noResults.style.display = visible === 0 ? 'block' : 'none';
                if (countEl) countEl.textContent = query ? `${visible} result${visible !== 1 ? 's' : ''}` : '';
            }
            sortSelect?.addEventListener('change', applyFilter);
        }

        // Article Modal Functionality
        function openArticleModal(articleId) {
            const article = document.querySelector(`[data-article-id="${articleId}"]`);
            if (!article) return;

            const title = article.querySelector('.post-title, .post-title a')?.textContent || '';
            const image = article.querySelector('img')?.src || '/assets/img/blog/default.webp';
            const category = article.querySelector('.category, .category-tag')?.textContent || '';
            const date = article.querySelector('.date-tag, .post-date')?.textContent || '';
            const author = article.querySelector('.post-author')?.textContent || '';
            const likes = article.querySelector('.like-count')?.textContent || '0';
            const comments = article.querySelector('.comment-count')?.textContent || '0';

            // Fetch complete article content from database
            fetch('get_article_content.php?id=' + articleId)
                .then(response => response.json())
                .then(data => {
                    const fullContent = data.content || data.short_description || 'No content available';
                    
                    const modalHTML = `
                        <div class="article-modal-overlay" id="article-modal">
                            <div class="article-modal-content">
                                <div class="article-modal-header">
                                    <h2 class="modal-article-title">${title}</h2>
                                    <button class="modal-close-btn" onclick="closeArticleModal()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="article-modal-body">
                                    <div class="modal-article-image">
                                        <img src="${image}" alt="${title}" />
                                    </div>
                                    <div class="modal-article-meta">
                                        <span class="modal-category">${category}</span>
                                        <span class="modal-date">${date}</span>
                                        <span class="modal-author">by ${author}</span>
                                    </div>
                                    <div class="modal-article-content">
                                        ${fullContent}
                                    </div>
                                    <div class="modal-article-actions">
                                        <button class="modal-action-btn like-btn" onclick="toggleLike(${articleId})">
                                            <i class="far fa-heart"></i> <span>${likes}</span>
                                        </button>
                                        <button class="modal-action-btn comment-btn" onclick="toggleComment(${articleId})">
                                            <i class="far fa-comment"></i> <span>${comments}</span>
                                        </button>
                                        <button class="modal-action-btn share-btn" onclick="sharePost(${articleId}, '${title.replace(/'/g, "\\'")}')">
                                            <i class="fas fa-share"></i>
                                        </button>
                                    </div>
                                    <div class="modal-messages-section">
                                        <div class="modal-messages-header">
                                            <h4>Comments</h4>
                                            <span class="message-count" id="message-count-${articleId}">0 messages</span>
                                        </div>
                                        <div class="modal-messages-container" id="messages-container-${articleId}">
                                            <div class="no-messages">No comments yet. Be the first to comment!</div>
                                        </div>
                                        <div class="message-input-container">
                                            <input type="text" class="message-input" id="message-input-${articleId}" 
                                                   placeholder="Write a comment..." maxlength="200">
                                            <button class="send-message-btn" onclick="sendMessage(${articleId})">
                                                <i class="fas fa-paper-plane"></i> Send
                                            </button>
                                        </div>
                                        <div class="see-more-container" id="see-more-${articleId}" style="display: none;">
                                            <button class="see-more-btn" onclick="loadMoreMessages(${articleId})">
                                                See More Messages
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    document.body.insertAdjacentHTML('beforeend', modalHTML);
                    document.body.style.overflow = 'hidden';
                    
                    // Add event listeners
                    document.getElementById('article-modal').addEventListener('click', function(e) {
                        if (e.target === this) closeArticleModal();
                    });
                })
                .catch(error => {
                    console.error('Error fetching article content:', error);
                    // Fallback to existing content if fetch fails
                    const fallbackContent = article.querySelector('.post-excerpt, .excerpt')?.textContent || 'Content not available';
                    
                    const modalHTML = `
                        <div class="article-modal-overlay" id="article-modal">
                            <div class="article-modal-content">
                                <div class="article-modal-header">
                                    <h2 class="modal-article-title">${title}</h2>
                                    <button class="modal-close-btn" onclick="closeArticleModal()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="article-modal-body">
                                    <div class="modal-article-image">
                                        <img src="${image}" alt="${title}" />
                                    </div>
                                    <div class="modal-article-meta">
                                        <span class="modal-category">${category}</span>
                                        <span class="modal-date">${date}</span>
                                        <span class="modal-author">by ${author}</span>
                                    </div>
                                    <div class="modal-article-content">
                                        ${fallbackContent}
                                    </div>
                                    <div class="modal-article-actions">
                                        <button class="modal-action-btn like-btn" onclick="toggleLike(${articleId})">
                                            <i class="far fa-heart"></i> <span>${likes}</span>
                                        </button>
                                        <button class="modal-action-btn comment-btn" onclick="toggleComment(${articleId})">
                                            <i class="far fa-comment"></i> <span>${comments}</span>
                                        </button>
                                        <button class="modal-action-btn share-btn" onclick="sharePost(${articleId}, '${title.replace(/'/g, "\\'")}')">
                                            <i class="fas fa-share"></i>
                                        </button>
                                    </div>
                                    <div class="modal-messages-section">
                                        <div class="modal-messages-header">
                                            <h4>Comments</h4>
                                            <span class="message-count" id="message-count-${articleId}">0 messages</span>
                                        </div>
                                        <div class="modal-messages-container" id="messages-container-${articleId}">
                                            <div class="no-messages">No comments yet. Be the first to comment!</div>
                                        </div>
                                        <div class="message-input-container">
                                            <input type="text" class="message-input" id="message-input-${articleId}" 
                                                   placeholder="Write a comment..." maxlength="200">
                                            <button class="send-message-btn" onclick="sendMessage(${articleId})">
                                                <i class="fas fa-paper-plane"></i> Send
                                            </button>
                                        </div>
                                        <div class="see-more-container" id="see-more-${articleId}" style="display: none;">
                                            <button class="see-more-btn" onclick="loadMoreMessages(${articleId})">
                                                See More Messages
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    document.body.insertAdjacentHTML('beforeend', modalHTML);
                    document.body.style.overflow = 'hidden';
                    
                    // Add event listeners
                    document.getElementById('article-modal').addEventListener('click', function(e) {
                        if (e.target === this) closeArticleModal();
                    });
                });
        }

        function closeArticleModal() {
            const modal = document.getElementById('article-modal');
            if (modal) {
                modal.remove();
                document.body.style.overflow = '';
            }
        }

        // Add click handlers to all articles
        document.addEventListener('click', function(e) {
            const article = e.target.closest('[data-article-id]');
            if (article && (e.target.closest('.read-more-btn') || e.target.closest('.post-title, .post-excerpt-container') || e.target.closest('.comment-btn') || e.target.closest('.interaction-btn.comment-btn'))) {
                e.preventDefault();
                const articleId = article.getAttribute('data-article-id');
                openArticleModal(articleId);
            }
        });

        // Message System
        let messageCounters = {};
        let displayedMessages = {};
        let allMessages = {};

        function sendMessage(articleId) {
            const input = document.getElementById(`message-input-${articleId}`);
            const text = input.value.trim();
            
            if (!text) return;
            
            // Initialize message storage for this article
            if (!allMessages[articleId]) {
                allMessages[articleId] = [];
            }
            
            const message = {
                id: Date.now(),
                text: text,
                author: `anonymous user#${allMessages[articleId].length + 1}`,
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            };
            
            allMessages[articleId].push(message);
            input.value = '';
            
            // Update display
            updateMessagesDisplay(articleId);
        }

        function updateMessagesDisplay(articleId) {
            const container = document.getElementById(`messages-container-${articleId}`);
            const countElement = document.getElementById(`message-count-${articleId}`);
            const seeMoreContainer = document.getElementById(`see-more-${articleId}`);
            
            const messages = allMessages[articleId] || [];
            const displayedCount = displayedMessages[articleId] || 0;
            
            // Update count
            countElement.textContent = `${messages.length} messages`;
            
            // Determine how many to show
            const showCount = Math.min(messages.length, displayedCount || 10);
            const messagesToShow = messages.slice(-showCount);
            
            if (messagesToShow.length === 0) {
                container.innerHTML = '<div class="no-messages">No comments yet. Be the first to comment!</div>';
            } else {
                container.innerHTML = messagesToShow.map(msg => `
                    <div class="message-item">
                        <div class="message-avatar">${msg.author.charAt(0).toUpperCase()}</div>
                        <div class="message-content">
                            <div class="message-author">${msg.author}</div>
                            <div class="message-text">${msg.text}</div>
                            <div class="message-time">${msg.time}</div>
                        </div>
                    </div>
                `).join('');
            }
            
            // Show/hide "See More" button
            if (messages.length > 10) {
                seeMoreContainer.style.display = 'block';
                displayedMessages[articleId] = Math.min(displayedCount + 10, messages.length);
            } else {
                seeMoreContainer.style.display = 'none';
                displayedMessages[articleId] = messages.length;
            }
        }

        function loadMoreMessages(articleId) {
            const messages = allMessages[articleId] || [];
            const currentDisplayed = displayedMessages[articleId] || 10;
            const newDisplayCount = Math.min(currentDisplayed + 10, messages.length);
            
            displayedMessages[articleId] = newDisplayCount;
            updateMessagesDisplay(articleId);
        }

        /* ══════════════════════════════════════════════════════
           READ-TIME ON CARDS
        ══════════════════════════════════════════════════════ */
        function injectReadTimes() {
            document.querySelectorAll('.post-box').forEach(box => {
                const excerpt = box.querySelector('.excerpt');
                const meta = box.querySelector('.meta');
                if (!excerpt || !meta) return;
                if (meta.querySelector('.read-time-badge')) return;
                const mins = calcReadTime(excerpt.textContent);
                const badge = document.createElement('span');
                badge.className = 'read-time-badge';
                badge.innerHTML = `<i class="bi bi-clock"></i> ${mins} min`;
                meta.appendChild(badge);
            });
        }

        /* ══════════════════════════════════════════════════════
           INIT
        ══════════════════════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', () => {
            /* Restore likes from localStorage */
            document.querySelectorAll('.interaction-bar').forEach(bar => {
                const id = bar.dataset.postId;
                if (localStorage.getItem(`liked-${id}`)) {
                    bar.querySelector('.like-btn')?.classList.add('liked');
                    const countEl = bar.querySelector('.like-count');
                    if (countEl) countEl.textContent = parseInt(countEl.textContent) + 1;
                    likesData[id] = true;
                }
            });

            /* Wire comment buttons → modal */
            document.querySelectorAll('.post-box .comment-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.closest('.interaction-bar')?.dataset.postId;
                    if (id) openNewsModal(id);
                });
            });

            /* Wire Read More → modal */
            document.querySelectorAll('.readmore').forEach(link => {
                link.addEventListener('click', e => {
                    e.preventDefault();
                    const id = link.closest('.post-box')
                        ?.querySelector('.interaction-bar')?.dataset.postId;
                    if (id) openNewsModal(id);
                });
            });

            injectReadTimes();
            initSearchFilter();

            /* Open post from URL param ?post=ID */
            const params = new URLSearchParams(location.search);
            const openId = params.get('post');
            if (openId) setTimeout(() => openNewsModal(openId), 400);
        });

        /* ── Keyboard & overlay close ── */
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeNewsModal();
        });
        document.getElementById('news-modal-overlay').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeNewsModal();
        });
    </script>

</body>

</html>