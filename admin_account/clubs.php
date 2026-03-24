<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Admin Dashboard – Clubs</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <style>
        /* =========================================================
           CSS VARIABLES & RESET
        ========================================================= */
        :root {
            --bg: #f0f2f7;
            --card: #ffffff;
            --card-hover: #fafbff;
            --border: #e4e8f0;
            --accent: #4f6ef7;
            --accent-soft: #eef1fe;
            --accent2: #f7774f;
            --accent2-soft: #fff0eb;
            --green: #3ecf8e;
            --green-soft: #e8faf3;
            --yellow: #f7c94f;
            --yellow-soft: #fef9e7;
            --red: #f75f5f;
            --red-soft: #fef0f0;
            --text: #1a1d2e;
            --muted: #7b8099;
            --shadow: 0 2px 12px rgba(79, 110, 247, .08), 0 1px 3px rgba(0, 0, 0, .06);
            --shadow-lg: 0 8px 32px rgba(79, 110, 247, .14), 0 2px 8px rgba(0, 0, 0, .08);
            --radius: 16px;
            --radius-sm: 10px;
            --font-head: 'Playfair Display', serif;
            --font-body: 'DM Sans', sans-serif;
            --transition: .22s cubic-bezier(.4, 0, .2, 1);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg);
            font-family: var(--font-body);
            color: var(--text);
            min-height: 100vh;
        }

        /* =========================================================
           PAGE TITLE (reuse existing pattern)
        ========================================================= */
        .page-title {
            margin-bottom: 0;
        }

        /* =========================================================
           PAGE CONTENT (matches forms.php layout)
        ========================================================= */
        .page-content {
            padding: 0 20px;
        }

        /* =========================================================
           CLUBS PAGE WRAPPER (matches forms.php .forms-page)
        ========================================================= */
        .clubs-page {
            padding: 24px 20px 24px 0;
            width: 100%;
            max-width: 100%;
            margin-left: 0;
            margin-right: 0;
            overflow-x: hidden;
        }

        /* =========================================================
           CLUBS WRAPPER
        ========================================================= */
        .clubs-wrapper {
            padding: 0 0 8px;
        }

        /* =========================================================
           BENTO GRID
        ========================================================= */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            grid-auto-rows: auto;
            gap: 18px;
        }

        /* Stats row */
        .bento-stats {
            grid-column: span 3;
        }

        .bento-featured {
            grid-column: span 5;
        }

        .bento-feed {
            grid-column: span 4;
        }

        /* Search / filter bar */
        .bento-filter {
            grid-column: span 12;
        }

        /* Clubs grid */
        .bento-clubs {
            grid-column: span 8;
        }

        /* Requests panel */
        .bento-requests {
            grid-column: span 4;
        }

        @media (max-width: 1200px) {
            .bento-stats {
                grid-column: span 6;
            }

            .bento-featured {
                grid-column: span 6;
            }

            .bento-feed {
                grid-column: span 12;
            }

            .bento-clubs {
                grid-column: span 12;
            }

            .bento-requests {
                grid-column: span 12;
            }
        }

        @media (max-width: 768px) {
            .bento-stats {
                grid-column: span 12;
            }

            .bento-featured {
                grid-column: span 12;
            }

            .bento-clubs {
                grid-column: span 12;
            }

            .bento-requests {
                grid-column: span 12;
            }

            .page-content {
                width: 100%;
                padding: 0 16px 36px;
            }

            .clubs-wrapper {
                padding: 16px 0 40px;
            }
        }

        /* =========================================================
           BENTO CARD BASE
        ========================================================= */
        .bento-card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
            transition: box-shadow var(--transition);
        }

        .bento-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .bento-card__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px 0;
            margin-bottom: 14px;
        }

        .bento-card__title {
            font-family: var(--font-head);
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bento-card__title i {
            color: var(--accent);
            font-size: .9rem;
        }

        /* =========================================================
           STAT CARDS (inside stats bento box)
        ========================================================= */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 0 16px 16px;
        }

        .stat-item {
            border-radius: var(--radius-sm);
            padding: 14px 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .stat-item.blue {
            background: var(--accent-soft);
        }

        .stat-item.orange {
            background: var(--accent2-soft);
        }

        .stat-item.green {
            background: var(--green-soft);
        }

        .stat-item.yellow {
            background: var(--yellow-soft);
        }

        .stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
        }

        .blue .stat-icon {
            background: var(--accent);
            color: #fff;
        }

        .orange .stat-icon {
            background: var(--accent2);
            color: #fff;
        }

        .green .stat-icon {
            background: var(--green);
            color: #fff;
        }

        .yellow .stat-icon {
            background: var(--yellow);
            color: #fff;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
            color: var(--text);
        }

        .stat-label {
            font-size: .72rem;
            font-weight: 500;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        /* =========================================================
           FEATURED CLUB BOX
        ========================================================= */
        .featured-inner {
            position: relative;
            padding: 0 20px 20px;
        }

        .featured-banner {
            width: 100%;
            height: 140px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            background: linear-gradient(135deg, #4f6ef7 0%, #8b5cf6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 14px;
        }

        .featured-banner-placeholder {
            width: 100%;
            height: 140px;
            border-radius: var(--radius-sm);
            background: linear-gradient(135deg, #4f6ef7 0%, #8b5cf6 50%, #f7774f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            position: relative;
            overflow: hidden;
        }

        .featured-banner-placeholder::after {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.08'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .featured-logo {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: #fff;
            border: 3px solid #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            position: absolute;
            top: 110px;
            left: 32px;
        }

        .featured-name {
            font-family: var(--font-head);
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 30px;
            margin-bottom: 4px;
        }

        .featured-desc {
            font-size: .82rem;
            color: var(--muted);
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .featured-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .featured-chip {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: .75rem;
            font-weight: 600;
        }

        /* =========================================================
           ACTIVITY FEED
        ========================================================= */
        .feed-list {
            padding: 0 16px 16px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            max-height: 280px;
            overflow-y: auto;
        }

        .feed-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 6px;
            border-radius: var(--radius-sm);
            transition: background var(--transition);
        }

        .feed-item:hover {
            background: var(--bg);
        }

        .feed-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            flex-shrink: 0;
        }

        .feed-dot.join {
            background: var(--green-soft);
            color: var(--green);
        }

        .feed-dot.new {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .feed-dot.event {
            background: var(--yellow-soft);
            color: #b58e00;
        }

        .feed-dot.promote {
            background: var(--accent2-soft);
            color: var(--accent2);
        }

        .feed-text {
            font-size: .8rem;
            line-height: 1.45;
        }

        .feed-text strong {
            font-weight: 600;
        }

        .feed-time {
            font-size: .7rem;
            color: var(--muted);
            margin-top: 2px;
        }

        /* =========================================================
           SEARCH & FILTER BAR
        ========================================================= */
        .filter-bar {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-search {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .filter-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: .85rem;
        }

        .filter-search input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--bg);
            font-family: var(--font-body);
            font-size: .85rem;
            color: var(--text);
            outline: none;
            transition: border var(--transition);
        }

        .filter-search input:focus {
            border-color: var(--accent);
            background: #fff;
        }

        .filter-select {
            padding: 9px 30px 9px 12px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%237b8099' d='M6 8L1 3h10z'/%3E%3C/svg%3E") no-repeat right 10px center;
            font-family: var(--font-body);
            font-size: .85rem;
            color: var(--text);
            outline: none;
            appearance: none;
            cursor: pointer;
            transition: border var(--transition);
        }

        .filter-select:focus {
            border-color: var(--accent);
            background-color: #fff;
        }

        .filter-btn-group {
            display: flex;
            gap: 6px;
        }

        .filter-btn {
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--bg);
            font-family: var(--font-body);
            font-size: .8rem;
            font-weight: 500;
            color: var(--muted);
            cursor: pointer;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .filter-btn i {
            font-size: .75rem;
        }

        /* =========================================================
           CLUBS GRID (inside bento-clubs)
        ========================================================= */
        .clubs-inner-header {
            padding: 18px 20px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .clubs-inner-header .bento-card__title {
            margin-bottom: 0;
        }

        .btn-add-club {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            background: var(--accent);
            color: #fff;
            font-family: var(--font-body);
            font-size: .82rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
        }

        .btn-add-club:hover {
            background: #3a5ae0;
            transform: translateY(-1px);
        }

        .clubs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
            padding: 0 18px 18px;
        }

        /* Individual Club Card */
        .club-card {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--card);
            padding: 16px;
            cursor: pointer;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .club-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform var(--transition);
        }

        .club-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 20px rgba(79, 110, 247, .14);
            transform: translateY(-3px);
            background: var(--card-hover);
        }

        .club-card:hover::before {
            transform: scaleX(1);
        }

        .club-card-logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--accent-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 12px;
        }

        .club-card-name {
            font-weight: 700;
            font-size: .9rem;
            margin-bottom: 4px;
            color: var(--text);
        }

        .club-card-desc {
            font-size: .75rem;
            color: var(--muted);
            line-height: 1.45;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .club-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .club-card-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .club-card-adviser {
            font-size: .7rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .club-card-adviser i {
            font-size: .65rem;
            color: var(--accent);
        }

        .club-card-members {
            font-size: .7rem;
            font-weight: 600;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .club-badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: .65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .badge-academic {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .badge-sports {
            background: var(--green-soft);
            color: #1e9966;
        }

        .badge-arts {
            background: var(--accent2-soft);
            color: var(--accent2);
        }

        .badge-technology {
            background: #f0eaff;
            color: #7c3aed;
        }

        .badge-science {
            background: var(--yellow-soft);
            color: #9a7000;
        }

        .badge-other {
            background: #f0f2f7;
            color: var(--muted);
        }

        .club-status-dot {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-active {
            background: var(--green);
            box-shadow: 0 0 0 2px var(--green-soft);
        }

        .status-pending {
            background: var(--yellow);
            box-shadow: 0 0 0 2px var(--yellow-soft);
        }

        .status-inactive {
            background: var(--muted);
        }

        /* =========================================================
           CLUB REQUESTS PANEL
        ========================================================= */
        .requests-list {
            padding: 0 14px 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 520px;
            overflow-y: auto;
        }

        .request-card {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px;
            transition: all var(--transition);
        }

        .request-card:hover {
            border-color: var(--accent);
        }

        .request-name {
            font-weight: 700;
            font-size: .9rem;
            margin-bottom: 2px;
        }

        .request-meta {
            font-size: .75rem;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .request-meta span {
            margin-right: 10px;
        }

        .request-meta i {
            color: var(--accent);
            margin-right: 3px;
            font-size: .7rem;
        }

        .request-desc {
            font-size: .78rem;
            color: var(--text);
            line-height: 1.45;
            margin-bottom: 10px;
            padding: 8px;
            background: var(--bg);
            border-radius: 8px;
        }

        .request-actions {
            display: flex;
            gap: 8px;
        }

        .btn-approve,
        .btn-reject {
            flex: 1;
            padding: 7px 10px;
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: .78rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all var(--transition);
        }

        .btn-approve {
            background: var(--green-soft);
            color: #1e9966;
        }

        .btn-approve:hover {
            background: var(--green);
            color: #fff;
        }

        .btn-reject {
            background: var(--red-soft);
            color: var(--red);
        }

        .btn-reject:hover {
            background: var(--red);
            color: #fff;
        }

        .requests-empty {
            text-align: center;
            padding: 30px 0;
            color: var(--muted);
            font-size: .85rem;
        }

        .requests-empty i {
            font-size: 2rem;
            display: block;
            margin-bottom: 8px;
            opacity: .4;
        }

        /* =========================================================
           MODAL
        ========================================================= */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(26, 29, 46, .45);
            backdrop-filter: blur(4px);
            z-index: 9000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s ease;
            padding: 16px;
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .modal-box {
            background: var(--card);
            border-radius: 20px;
            width: 100%;
            max-width: 640px;
            max-height: 88vh;
            overflow-y: auto;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .2);
            transform: scale(.94) translateY(20px);
            transition: transform .3s cubic-bezier(.4, 0, .2, 1), opacity .3s ease;
            opacity: 0;
        }

        .modal-overlay.open .modal-box {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .modal-header {
            padding: 0;
            position: relative;
        }

        .modal-banner {
            width: 100%;
            height: 130px;
            background: linear-gradient(135deg, #4f6ef7, #8b5cf6);
            border-radius: 20px 20px 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            position: relative;
        }

        .modal-logo {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: #fff;
            border: 3px solid #fff;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            position: absolute;
            bottom: -28px;
            left: 24px;
        }

        .modal-close {
            position: absolute;
            top: 12px;
            right: 14px;
            background: rgba(255, 255, 255, .25);
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .85rem;
            cursor: pointer;
            transition: background var(--transition);
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, .45);
        }

        .modal-body {
            padding: 40px 24px 24px;
        }

        .modal-club-name {
            font-family: var(--font-head);
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .modal-club-category {
            margin-bottom: 14px;
        }

        .modal-section-title {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin: 18px 0 10px;
        }

        .modal-leaders {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .modal-leader-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 10px;
            background: var(--bg);
            border: 1px solid var(--border);
        }

        .modal-leader-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .modal-leader-info {
            font-size: .78rem;
        }

        .modal-leader-name {
            font-weight: 600;
        }

        .modal-leader-role {
            color: var(--muted);
            font-size: .7rem;
        }

        .modal-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .modal-info-item {
            background: var(--bg);
            border-radius: 10px;
            padding: 10px 12px;
        }

        .modal-info-label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 3px;
        }

        .modal-info-value {
            font-size: .85rem;
            font-weight: 600;
        }

        .modal-info-value.status-active {
            color: var(--green);
        }

        .modal-info-value.status-pending {
            color: #b58e00;
        }

        .modal-desc {
            font-size: .84rem;
            color: var(--text);
            line-height: 1.6;
            background: var(--bg);
            border-radius: 10px;
            padding: 12px;
        }

        /* Members list in modal */
        .members-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-height: 220px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .members-list::-webkit-scrollbar {
            width: 4px;
        }

        .members-list::-webkit-scrollbar-track {
            background: var(--bg);
            border-radius: 10px;
        }

        .members-list::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        .member-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            transition: background var(--transition);
        }

        .member-row:hover {
            background: var(--bg);
        }

        .member-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }

        .member-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-size: .84rem;
            font-weight: 600;
        }

        .member-role {
            font-size: .7rem;
            color: var(--muted);
        }

        .member-badge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: .65rem;
            font-weight: 700;
        }

        /* ── Online status indicators ── */
        .member-status-col {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
            min-width: 72px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            border-radius: 20px;
            font-size: .62rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pill .status-dot-sm {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-pill.online {
            background: var(--green-soft);
            color: #1e9966;
        }

        .status-pill.online .status-dot-sm {
            background: var(--green);
        }

        .status-pill.active {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .status-pill.active .status-dot-sm {
            background: var(--accent);
        }

        .status-pill.offline {
            background: var(--bg);
            color: var(--muted);
            border: 1px solid var(--border);
        }

        .status-pill.offline .status-dot-sm {
            background: var(--muted);
        }

        .member-last-seen {
            font-size: .62rem;
            color: var(--muted);
        }

        .member-login {
            font-size: .7rem;
            color: var(--muted);
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .member-login i {
            font-size: .6rem;
            color: var(--accent);
            margin-right: 2px;
        }

        /* Members list extended max-height */
        .members-list {
            max-height: 300px;
        }

        .role-officer {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .role-member {
            background: var(--bg);
            color: var(--muted);
            border: 1px solid var(--border);
        }

        /* =========================================================
           TOAST NOTIFICATIONS
        ========================================================= */
        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast {
            padding: 12px 18px;
            border-radius: 12px;
            font-size: .84rem;
            font-weight: 600;
            font-family: var(--font-body);
            color: #fff;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .15);
            display: flex;
            align-items: center;
            gap: 8px;
            animation: toastIn .3s ease;
            max-width: 280px;
        }

        .toast.success {
            background: var(--green);
        }

        .toast.error {
            background: var(--red);
        }

        .toast.info {
            background: var(--accent);
        }

        @keyframes toastIn {
            from {
                transform: translateX(40px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* =========================================================
           SCROLLBAR GLOBAL
        ========================================================= */
        .feed-list::-webkit-scrollbar,
        .requests-list::-webkit-scrollbar {
            width: 4px;
        }

        .feed-list::-webkit-scrollbar-track,
        .requests-list::-webkit-scrollbar-track {
            background: var(--bg);
            border-radius: 10px;
        }

        .feed-list::-webkit-scrollbar-thumb,
        .requests-list::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        /* =========================================================
           LOADING SKELETON
        ========================================================= */
        .skeleton {
            background: linear-gradient(90deg, var(--border) 25%, #f5f5f5 50%, var(--border) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 6px;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        /* =========================================================
           NO CLUBS PLACEHOLDER
        ========================================================= */
        .no-clubs {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 0;
            color: var(--muted);
        }

        .no-clubs i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
            opacity: .35;
        }
    </style>
</head>

<body>
    <?php include 'admin_nav.php'; ?>
    <script>
        // Initialize navigation functionality after include
        if (typeof initializeNavigation === 'function') {
            initializeNavigation();
        }
    </script>
    <?php
    /* ================================================================
       DATABASE CONNECTION
       Change credentials to match your configuration.
    ================================================================ */
    $host     = 'localhost';
    $db_name  = 'school_db';
    $db_user  = 'root';
    $db_pass  = '';
    $charset  = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = null;
    $db_error = false;
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    } catch (\PDOException $e) {
        $db_error = true;
        // Uncomment for debugging: error_log($e->getMessage());
    }

    /* ================================================================
       CSRF TOKEN
    ================================================================ */
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf = $_SESSION['csrf_token'];

    /* ================================================================
       ROLE CHECK  (adjust to your auth system)
       Assumes $_SESSION['user_role'] = 'admin' | 'sub_admin' | 'teacher' | 'student'
    ================================================================ */
    $can_manage = isset($_SESSION['user_role'])
        && in_array($_SESSION['user_role'], ['admin', 'sub_admin']);

    /* ================================================================
       FETCH STATS
    ================================================================ */
    $stats = [
        'total_clubs'    => 0,
        'active_clubs'   => 0,
        'pending_req'    => 0,
        'total_members'  => 0,
    ];

    if ($pdo) {
        $r = $pdo->query("SELECT
              COUNT(*) AS total_clubs,
              SUM(status = 'Active') AS active_clubs
          FROM clubs")->fetch();
        $stats['total_clubs']   = $r['total_clubs'] ?? 0;
        $stats['active_clubs']  = $r['active_clubs'] ?? 0;

        $stats['pending_req'] = $pdo->query(
            "SELECT COUNT(*) FROM club_requests WHERE status = 'Pending'"
        )->fetchColumn();

        $stats['total_members'] = $pdo->query(
            "SELECT COUNT(*) FROM club_members"
        )->fetchColumn();
    }

    /* ================================================================
       FETCH CLUBS
    ================================================================ */
    $clubs = [];
    if ($pdo) {
        $stmt = $pdo->query("
            SELECT c.*,
                   u.full_name AS adviser_name,
                   (SELECT COUNT(*) FROM club_members cm WHERE cm.club_id = c.id) AS member_count
            FROM clubs c
            LEFT JOIN users u ON c.adviser_id = u.id
            WHERE c.status IN ('Active','Inactive')
            ORDER BY c.created_at DESC
        ");
        $clubs = $stmt->fetchAll();
    }

    /* ================================================================
       FETCH FEATURED CLUB (most members)
    ================================================================ */
    $featured = null;
    if ($pdo && count($clubs) > 0) {
        usort($clubs, fn($a, $b) => $b['member_count'] <=> $a['member_count']);
        $featured = $clubs[0];
    }

    /* ================================================================
       FETCH PENDING REQUESTS
    ================================================================ */
    $requests = [];
    if ($pdo) {
        $stmt = $pdo->query("
            SELECT cr.*,
                   s.full_name AS student_name,
                   t.full_name AS adviser_name
            FROM club_requests cr
            LEFT JOIN users s ON cr.student_id = s.id
            LEFT JOIN users t ON cr.proposed_adviser_id = t.id
            WHERE cr.status = 'Pending'
            ORDER BY cr.requested_at DESC
        ");
        $requests = $stmt->fetchAll();
    }

    /* ================================================================
       FETCH ACTIVITY FEED (last 20 actions)
    ================================================================ */
    $feed = [];
    if ($pdo) {
        $stmt = $pdo->query("
            SELECT al.*, u.full_name AS actor_name
            FROM club_activity_log al
            LEFT JOIN users u ON al.actor_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 20
        ");
        $feed = $stmt->fetchAll();
    }

    /* ================================================================
       EMOJI FALLBACK MAP FOR CATEGORIES
    ================================================================ */
    $cat_emoji = [
        'Academic'   => '📚',
        'Sports'     => '⚽',
        'Arts'       => '🎨',
        'Technology' => '💻',
        'Science'    => '🔬',
        'Music'      => '🎵',
        'Other'      => '🏫',
    ];
    function cat_emoji(string $cat, array $map): string
    {
        return $map[$cat] ?? '🏫';
    }

    /* ================================================================
       HELPER: Badge class
    ================================================================ */
    function badge_class(string $cat): string
    {
        $m = [
            'Academic'   => 'badge-academic',
            'Sports'     => 'badge-sports',
            'Arts'       => 'badge-arts',
            'Technology' => 'badge-technology',
            'Science'    => 'badge-science',
        ];
        return $m[$cat] ?? 'badge-other';
    }
    ?>

    <div id="navigation-container"></div>

    <!-- ============================================================
         MAIN PAGE CONTENT
    ============================================================ -->
    <main class="main page-content" id="main-content" style="display: none; margin-left: 0; width: calc(100vw - 260px); max-width: 100%; padding: 0 20px; overflow-x: hidden;">

        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">School Clubs</h1>
                            <p class="mb-0">Manage school clubs, memberships, and club requests</p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="admin_dashboard.php">Home</a></li>
                        <li class="current">Clubs</li>
                    </ol>
                </div>
            </nav>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                loadNavigation();
            });

            function loadNavigation() {
                const container = document.getElementById('navigation-container');
                fetch('./admin_nav.php')
                    .then(response => {
                        if (!response.ok) throw new Error('Failed to load navigation');
                        return response.text();
                    })
                    .then(data => {
                        container.innerHTML = data;
                        initializeNavigation();
                        document.getElementById('main-content').style.display = 'block';
                    })
                    .catch(error => {
                        container.innerHTML = '<div class="nav-error"><i class="fas fa-exclamation-triangle"></i><h3>Unable to Load Navigation</h3><p>There was a problem loading the navigation menu.</p><button class="btn-retry" onclick="loadNavigation()"><i class="fas fa-redo"></i> Try Again</button></div>';
                        document.getElementById('main-content').style.display = 'block';
                    });
            }

            function initializeNavigation() {
                const mainDiv = document.querySelector('.main');
                const pageContent = document.querySelector('.page-content');
        </script>

        <!-- ============================================================
             CLUBS CONTENT
        ============================================================ -->
        <div class="clubs-page">
            <div class="clubs-wrapper">
                <div class="bento-grid">

                    <!-- ================================================
                     1. STATS BOX
                ================================================ -->
                    <div class="bento-card bento-stats">
                        <div class="bento-card__header">
                            <span class="bento-card__title"><i class="fa-solid fa-chart-pie"></i> Overview</span>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-item blue">
                                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                                <div class="stat-value" id="stat-total"><?= htmlspecialchars($stats['total_clubs']) ?></div>
                                <div class="stat-label">Total Clubs</div>
                            </div>
                            <div class="stat-item green">
                                <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                                <div class="stat-value" id="stat-active"><?= htmlspecialchars($stats['active_clubs']) ?></div>
                                <div class="stat-label">Active</div>
                            </div>
                            <div class="stat-item yellow">
                                <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                                <div class="stat-value" id="stat-pending"><?= htmlspecialchars($stats['pending_req']) ?></div>
                                <div class="stat-label">Requests</div>
                            </div>
                            <div class="stat-item orange">
                                <div class="stat-icon"><i class="fa-solid fa-person"></i></div>
                                <div class="stat-value" id="stat-members"><?= htmlspecialchars($stats['total_members']) ?></div>
                                <div class="stat-label">Members</div>
                            </div>
                        </div>
                    </div>

                    <!-- ================================================
                     2. FEATURED CLUB
                ================================================ -->
                    <div class="bento-card bento-featured">
                        <div class="bento-card__header">
                            <span class="bento-card__title"><i class="fa-solid fa-star"></i> Featured Club</span>
                            <span style="font-size:.75rem;color:var(--muted);">Most Members</span>
                        </div>
                        <?php if ($featured): ?>
                            <div class="featured-inner">
                                <div class="featured-banner-placeholder">
                                    <span style="font-size:3.5rem;position:relative;z-index:1;">
                                        <?= cat_emoji($featured['category'] ?? 'Other', $cat_emoji) ?>
                                    </span>
                                </div>
                                <div class="featured-logo">
                                    <?= cat_emoji($featured['category'] ?? 'Other', $cat_emoji) ?>
                                </div>
                                <div class="featured-name"><?= htmlspecialchars($featured['name']) ?></div>
                                <div class="featured-desc"><?= htmlspecialchars(mb_strimwidth($featured['description'] ?? '', 0, 100, '…')) ?></div>
                                <div class="featured-meta">
                                    <span class="featured-chip"><i class="fa-solid fa-users"></i> <?= $featured['member_count'] ?> Members</span>
                                    <span class="featured-chip"><i class="fa-solid fa-chalkboard-teacher"></i> <?= htmlspecialchars($featured['adviser_name'] ?? '—') ?></span>
                                    <span class="featured-chip club-badge <?= badge_class($featured['category'] ?? 'Other') ?>"><?= htmlspecialchars($featured['category'] ?? 'Other') ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="featured-inner" style="padding:30px 20px;text-align:center;color:var(--muted);">
                                <i class="fa-solid fa-star" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>
                                No clubs yet. Create your first club!
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ================================================
                     3. ACTIVITY FEED
                ================================================ -->
                    <div class="bento-card bento-feed">
                        <div class="bento-card__header">
                            <span class="bento-card__title"><i class="fa-solid fa-bolt"></i> Activity Feed</span>
                        </div>
                        <div class="feed-list" id="activity-feed">
                            <?php if (empty($feed)): ?>
                                <!-- Demo feed items when DB is empty -->
                                <div class="feed-item">
                                    <div class="feed-dot new"><i class="fa-solid fa-plus"></i></div>
                                    <div>
                                        <div class="feed-text"><strong>Robotics Club</strong> was created</div>
                                        <div class="feed-time">Just now</div>
                                    </div>
                                </div>
                                <div class="feed-item">
                                    <div class="feed-dot join"><i class="fa-solid fa-user-plus"></i></div>
                                    <div>
                                        <div class="feed-text"><strong>Maria Santos</strong> joined Science Club</div>
                                        <div class="feed-time">2 min ago</div>
                                    </div>
                                </div>
                                <div class="feed-item">
                                    <div class="feed-dot event"><i class="fa-solid fa-calendar"></i></div>
                                    <div>
                                        <div class="feed-text">Drama Club posted a new <strong>event</strong></div>
                                        <div class="feed-time">15 min ago</div>
                                    </div>
                                </div>
                                <div class="feed-item">
                                    <div class="feed-dot promote"><i class="fa-solid fa-arrow-up"></i></div>
                                    <div>
                                        <div class="feed-text"><strong>Juan dela Cruz</strong> promoted to Officer</div>
                                        <div class="feed-time">1 hr ago</div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($feed as $item):
                                    $dot_class = match ($item['action_type'] ?? '') {
                                        'join'    => 'join',
                                        'new'     => 'new',
                                        'event'   => 'event',
                                        'promote' => 'promote',
                                        default   => 'new',
                                    };
                                    $dot_icon = match ($item['action_type'] ?? '') {
                                        'join'    => 'fa-user-plus',
                                        'new'     => 'fa-plus',
                                        'event'   => 'fa-calendar',
                                        'promote' => 'fa-arrow-up',
                                        default   => 'fa-bell',
                                    };
                                    $time_diff = time() - strtotime($item['created_at']);
                                    $time_str = $time_diff < 60 ? 'Just now'
                                        : ($time_diff < 3600 ? round($time_diff / 60) . ' min ago'
                                            : ($time_diff < 86400 ? round($time_diff / 3600) . ' hr ago'
                                                : date('M j', strtotime($item['created_at']))));
                                ?>
                                    <div class="feed-item">
                                        <div class="feed-dot <?= $dot_class ?>"><i class="fa-solid <?= $dot_icon ?>"></i></div>
                                        <div>
                                            <div class="feed-text"><?= htmlspecialchars($item['description']) ?></div>
                                            <div class="feed-time"><?= $time_str ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ================================================
                     4. SEARCH & FILTER BAR
                ================================================ -->
                    <div class="bento-card bento-filter">
                        <div class="filter-bar">
                            <div class="filter-search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="search-input" placeholder="Search clubs by name…">
                            </div>
                            <select class="filter-select" id="filter-category">
                                <option value="">All Categories</option>
                                <option value="Academic">Academic</option>
                                <option value="Sports">Sports</option>
                                <option value="Arts">Arts</option>
                                <option value="Technology">Technology</option>
                                <option value="Science">Science</option>
                                <option value="Music">Music</option>
                                <option value="Other">Other</option>
                            </select>
                            <select class="filter-select" id="filter-status">
                                <option value="">All Status</option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <div class="filter-btn-group">
                                <button class="filter-btn active" id="sort-newest" onclick="sortClubs('newest',this)">
                                    <i class="fa-solid fa-clock"></i> Newest
                                </button>
                                <button class="filter-btn" id="sort-members" onclick="sortClubs('members',this)">
                                    <i class="fa-solid fa-users"></i> Most Members
                                </button>
                                <button class="filter-btn" id="sort-az" onclick="sortClubs('az',this)">
                                    <i class="fa-solid fa-arrow-down-a-z"></i> A–Z
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ================================================
                     5. CLUBS GRID
                ================================================ -->
                    <div class="bento-card bento-clubs">
                        <div class="clubs-inner-header">
                            <span class="bento-card__title"><i class="fa-solid fa-layer-group"></i> All Clubs</span>
                            <?php if ($can_manage): ?>
                                <button class="btn-add-club" onclick="openCreateClubModal()">
                                    <i class="fa-solid fa-plus"></i> New Club
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="clubs-grid" id="clubs-grid">
                            <?php if (empty($clubs)): ?>
                                <!-- Demo cards when DB is empty -->
                                <?php
                                $demo_clubs = [
                                    ['id' => 1, 'name' => 'Robotics Club', 'description' => 'Build and program robots for competitions.', 'category' => 'Technology', 'status' => 'Active', 'adviser_name' => 'Mr. Reyes', 'member_count' => 18, 'emoji' => '🤖'],
                                    ['id' => 2, 'name' => 'Chess Club', 'description' => 'Sharpen strategic thinking through chess.', 'category' => 'Academic', 'status' => 'Active', 'adviser_name' => 'Ms. Garcia', 'member_count' => 12, 'emoji' => '♟️'],
                                    ['id' => 3, 'name' => 'Drama Society', 'description' => 'Perform, write and direct stage plays.', 'category' => 'Arts', 'status' => 'Active', 'adviser_name' => 'Mrs. Santos', 'member_count' => 24, 'emoji' => '🎭'],
                                    ['id' => 4, 'name' => 'Basketball Varsity', 'description' => 'Compete in interschool basketball tournaments.', 'category' => 'Sports', 'status' => 'Active', 'adviser_name' => 'Coach Lim', 'member_count' => 15, 'emoji' => '🏀'],
                                    ['id' => 5, 'name' => 'Science Explorers', 'description' => 'Conduct experiments and join science fairs.', 'category' => 'Science', 'status' => 'Pending', 'adviser_name' => 'Dr. Cruz', 'member_count' => 9, 'emoji' => '🔬'],
                                    ['id' => 6, 'name' => 'School Band', 'description' => 'Music performances and competitions.', 'category' => 'Music', 'status' => 'Active', 'adviser_name' => 'Mr. Torres', 'member_count' => 20, 'emoji' => '🎵'],
                                ];
                                foreach ($demo_clubs as $club):
                                ?>
                                    <div class="club-card" data-id="<?= $club['id'] ?>" data-name="<?= $club['name'] ?>"
                                        data-category="<?= $club['category'] ?>" data-status="<?= $club['status'] ?>"
                                        data-members="<?= $club['member_count'] ?>"
                                        onclick="openClubModal(<?= $club['id'] ?>)">
                                        <div class="club-status-dot status-<?= strtolower($club['status']) ?>"></div>
                                        <div class="club-card-logo"><?= $club['emoji'] ?></div>
                                        <div class="club-card-name"><?= htmlspecialchars($club['name']) ?></div>
                                        <div class="club-card-desc"><?= htmlspecialchars($club['description']) ?></div>
                                        <div class="club-card-footer">
                                            <div class="club-card-meta">
                                                <div class="club-card-adviser"><i class="fa-solid fa-chalkboard-teacher"></i><?= htmlspecialchars($club['adviser_name']) ?></div>
                                                <div class="club-card-members"><i class="fa-solid fa-users"></i><?= $club['member_count'] ?> members</div>
                                            </div>
                                            <span class="club-badge <?= badge_class($club['category']) ?>"><?= $club['category'] ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <?php foreach ($clubs as $club):
                                    $emoji = cat_emoji($club['category'] ?? 'Other', $cat_emoji);
                                ?>
                                    <div class="club-card"
                                        data-id="<?= $club['id'] ?>"
                                        data-name="<?= htmlspecialchars($club['name']) ?>"
                                        data-category="<?= htmlspecialchars($club['category'] ?? '') ?>"
                                        data-status="<?= htmlspecialchars($club['status']) ?>"
                                        data-members="<?= $club['member_count'] ?>"
                                        onclick="openClubModal(<?= $club['id'] ?>)">
                                        <div class="club-status-dot status-<?= strtolower($club['status']) ?>"></div>
                                        <div class="club-card-logo"><?= $emoji ?></div>
                                        <div class="club-card-name"><?= htmlspecialchars($club['name']) ?></div>
                                        <div class="club-card-desc"><?= htmlspecialchars($club['description'] ?? '') ?></div>
                                        <div class="club-card-footer">
                                            <div class="club-card-meta">
                                                <div class="club-card-adviser">
                                                    <i class="fa-solid fa-chalkboard-teacher"></i>
                                                    <?= htmlspecialchars($club['adviser_name'] ?? '—') ?>
                                                </div>
                                                <div class="club-card-members">
                                                    <i class="fa-solid fa-users"></i>
                                                    <?= $club['member_count'] ?> members
                                                </div>
                                            </div>
                                            <span class="club-badge <?= badge_class($club['category'] ?? '') ?>">
                                                <?= htmlspecialchars($club['category'] ?? 'Other') ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ================================================
                     6. CLUB REQUESTS PANEL
                ================================================ -->
                    <div class="bento-card bento-requests">
                        <div class="bento-card__header">
                            <span class="bento-card__title"><i class="fa-solid fa-inbox"></i> Club Requests</span>
                            <span style="background:var(--yellow-soft);color:#9a7000;font-size:.72rem;font-weight:700;padding:3px 8px;border-radius:20px;" id="req-count-badge">
                                <?= count($requests) ?> Pending
                            </span>
                        </div>
                        <div class="requests-list" id="requests-list">
                            <?php if (empty($requests)): ?>
                                <!-- Demo request when DB is empty -->
                                <div class="request-card" id="req-demo">
                                    <div class="request-name">Environmental Awareness Club</div>
                                    <div class="request-meta">
                                        <span><i class="fa-solid fa-user"></i>Ana Reyes</span>
                                        <span><i class="fa-solid fa-calendar"></i>Mar 7, 2025</span>
                                    </div>
                                    <div class="request-meta">
                                        <span><i class="fa-solid fa-chalkboard-teacher"></i>Proposed: Mrs. Lim</span>
                                    </div>
                                    <div class="request-desc">
                                        A club focused on environmental campaigns, tree planting, and eco-awareness within the school campus.
                                    </div>
                                    <?php if ($can_manage): ?>
                                        <div class="request-actions">
                                            <button class="btn-approve" onclick="handleRequest(0,'approve','req-demo')">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                            <button class="btn-reject" onclick="handleRequest(0,'reject','req-demo')">
                                                <i class="fa-solid fa-xmark"></i> Reject
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php foreach ($requests as $req): ?>
                                    <div class="request-card" id="req-<?= $req['id'] ?>">
                                        <div class="request-name"><?= htmlspecialchars($req['proposed_name']) ?></div>
                                        <div class="request-meta">
                                            <span><i class="fa-solid fa-user"></i><?= htmlspecialchars($req['student_name'] ?? '—') ?></span>
                                            <span><i class="fa-solid fa-calendar"></i><?= date('M j, Y', strtotime($req['requested_at'])) ?></span>
                                        </div>
                                        <div class="request-meta">
                                            <span><i class="fa-solid fa-chalkboard-teacher"></i>Proposed: <?= htmlspecialchars($req['adviser_name'] ?? '—') ?></span>
                                        </div>
                                        <div class="request-desc"><?= htmlspecialchars($req['description'] ?? '') ?></div>
                                        <?php if ($can_manage): ?>
                                            <div class="request-actions">
                                                <button class="btn-approve" onclick="handleRequest(<?= $req['id'] ?>,'approve','req-<?= $req['id'] ?>')">
                                                    <i class="fa-solid fa-check"></i> Approve
                                                </button>
                                                <button class="btn-reject" onclick="handleRequest(<?= $req['id'] ?>,'reject','req-<?= $req['id'] ?>')">
                                                    <i class="fa-solid fa-xmark"></i> Reject
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /.bento-grid -->
            </div><!-- /.clubs-wrapper -->
        </div><!-- /.clubs-page -->

    </main><!-- /main -->

    <!-- ================================================================
         CLUB DETAIL MODAL
    ================================================================ -->
    <div class="modal-overlay" id="club-modal" onclick="handleOverlayClick(event)">
        <div class="modal-box" id="modal-box">
            <div class="modal-header">
                <div class="modal-banner" id="modal-banner">
                    <span id="modal-emoji" style="position:relative;z-index:1;font-size:3rem;"></span>
                </div>
                <div class="modal-logo" id="modal-logo"></div>
                <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-club-name" id="modal-club-name">—</div>
                <div class="modal-club-category" id="modal-club-category"></div>

                <div class="modal-section-title">Club Leadership</div>
                <div class="modal-leaders" id="modal-leaders"></div>

                <div class="modal-section-title">Details</div>
                <div class="modal-info-grid" id="modal-info-grid"></div>

                <div class="modal-section-title">About</div>
                <div class="modal-desc" id="modal-desc">—</div>

                <div class="modal-section-title">Members</div>
                <div class="members-list" id="modal-members">
                    <div style="text-align:center;padding:20px 0;color:var(--muted);font-size:.82rem;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Loading members…
                    </div>
                </div>

                <?php if ($can_manage): ?>
                    <div style="margin-top:18px;display:flex;gap:8px;">
                        <button class="btn-add-club" id="modal-manage-btn" style="flex:1;justify-content:center;">
                            <i class="fa-solid fa-gear"></i> Manage Club
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toast container -->
    <div id="toast-container"></div>

    <!-- ================================================================
         JAVASCRIPT
    ================================================================ */
    <script>
    /* -----------------------------------------------------------------
       CSRF Token (passed from PHP)
    ----------------------------------------------------------------- */
    const CSRF_TOKEN = '<?= $csrf ?>';

    /* -----------------------------------------------------------------
       SEARCH & FILTER
    ----------------------------------------------------------------- */
    const searchInput    = document.getElementById('search-input');
    const filterCategory = document.getElementById('filter-category');
    const filterStatus   = document.getElementById('filter-status');

    function applyFilters() {
        const q    = searchInput.value.toLowerCase().trim();
        const cat  = filterCategory.value;
        const stat = filterStatus.value;

        document.querySelectorAll('#clubs-grid .club-card').forEach(card => {
            const name     = card.dataset.name.toLowerCase();
            const category = card.dataset.category;
            const status   = card.dataset.status;

            const matchQ   = !q   || name.includes(q);
            const matchCat = !cat || category === cat;
            const matchSt  = !stat || status === stat;

            card.style.display = (matchQ && matchCat && matchSt) ? '' : 'none';
        });

        // Show empty state if nothing visible
        const visible = [...document.querySelectorAll('#clubs-grid .club-card')]
            .filter(c => c.style.display !== 'none');
        const emptyEl = document.getElementById('no-clubs-msg');
        if (visible.length === 0) {
            if (!emptyEl) {
                const d = document.createElement('div');
                d.id = 'no-clubs-msg';
                d.className = 'no-clubs';
                d.innerHTML = `<i class="fa-solid fa-magnifying-glass"></i>No clubs match your search.`;
                document.getElementById('clubs-grid').appendChild(d);
            }
        } else {
            emptyEl && emptyEl.remove();
        }
    }

    searchInput.addEventListener('input', applyFilters);
    filterCategory.addEventListener('change', applyFilters);
    filterStatus.addEventListener('change', applyFilters);

    /* -----------------------------------------------------------------
       SORT
    ----------------------------------------------------------------- */
    function sortClubs(method, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const grid  = document.getElementById('clubs-grid');
        const cards = [...grid.querySelectorAll('.club-card')];

        cards.sort((a, b) => {
            if (method === 'members') return parseInt(b.dataset.members) - parseInt(a.dataset.members);
            if (method === 'az')      return a.dataset.name.localeCompare(b.dataset.name);
            return 0; // newest: keep DOM order (PHP already sorted by created_at DESC)
        });

        cards.forEach(c => grid.appendChild(c));
    }

    /* -----------------------------------------------------------------
       CLUB DETAIL MODAL
    ----------------------------------------------------------------- */
    function openClubModal(clubId) {
        const modal = document.getElementById('club-modal');

        // Reset members
        document.getElementById('modal-members').innerHTML =
            '<div style="text-align:center;padding:20px 0;color:var(--muted);font-size:.82rem;"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>';

        modal.classList.add('open');
        document.body.style.overflow = 'hidden';

        // Fetch club details
        fetch(`clubs_api.php?action=get_club&id=${clubId}&csrf=${CSRF_TOKEN}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { showToast('error', data.message || 'Failed to load club.'); return; }
                populateModal(data.club, data.members);
            })
            .catch(() => {
                // Fallback: populate with card data when API is not yet set up
                const card = document.querySelector(`.club-card[data-id="${clubId}"]`);
                if (card) {
                    populateModal({
                        id: clubId,
                        name: card.dataset.name,
                        category: card.dataset.category,
                        status: card.dataset.status,
                        member_count: card.dataset.members,
                        description: card.querySelector('.club-card-desc')?.textContent || '',
                        adviser_name: card.querySelector('.club-card-adviser')?.textContent?.trim() || '—',
                        leader_name: '—',
                        vice_leader_name: '',
                        created_at: '—',
                    }, []);
                }
            });
    }

    const CAT_EMOJI = {
        Academic:'📚', Sports:'⚽', Arts:'🎨',
        Technology:'💻', Science:'🔬', Music:'🎵', Other:'🏫'
    };
    const BADGE_CLASS = {
        Academic:'badge-academic', Sports:'badge-sports', Arts:'badge-arts',
        Technology:'badge-technology', Science:'badge-science'
    };

    function populateModal(club, members) {
        const emoji = CAT_EMOJI[club.category] || '🏫';

        document.getElementById('modal-emoji').textContent = emoji;
        document.getElementById('modal-logo').textContent  = emoji;
        document.getElementById('modal-club-name').textContent = club.name || '—';

        // Category badge
        const catBadge = document.getElementById('modal-club-category');
        const bc = BADGE_CLASS[club.category] || 'badge-other';
        catBadge.innerHTML = `<span class="club-badge ${bc}">${club.category || 'Other'}</span>`;

        // Leaders
        const leadersEl = document.getElementById('modal-leaders');
        leadersEl.innerHTML = '';
        const addLeader = (name, role) => {
            if (!name) return;
            const initials = name.split(' ').map(n=>n[0]).join('').toUpperCase().slice(0,2);
            leadersEl.innerHTML += `
                <div class="modal-leader-chip">
                    <div class="modal-leader-avatar">${initials}</div>
                    <div class="modal-leader-info">
                        <div class="modal-leader-name">${name}</div>
                        <div class="modal-leader-role">${role}</div>
                    </div>
                </div>`;
        };
        addLeader(club.leader_name, 'President');
        if (club.vice_leader_name) addLeader(club.vice_leader_name, 'Vice President');
        addLeader(club.adviser_name, 'Adviser');

        // Info grid
        const statusClass = club.status === 'Active' ? 'status-active' : 'status-pending';
        document.getElementById('modal-info-grid').innerHTML = `
            <div class="modal-info-item">
                <div class="modal-info-label">Status</div>
                <div class="modal-info-value ${statusClass}">${club.status || '—'}</div>
            </div>
            <div class="modal-info-item">
                <div class="modal-info-label">Members</div>
                <div class="modal-info-value">${club.member_count || 0}</div>
            </div>
            <div class="modal-info-item">
                <div class="modal-info-label">Category</div>
                <div class="modal-info-value">${club.category || '—'}</div>
            </div>
            <div class="modal-info-item">
                <div class="modal-info-label">Date Created</div>
                <div class="modal-info-value">${club.created_at || '—'}</div>
            </div>`;

        // Description
        document.getElementById('modal-desc').textContent = club.description || 'No description provided.';

        // Members list
        const membersEl = document.getElementById('modal-members');
        if (!members || members.length === 0) {
            membersEl.innerHTML = '<div style="text-align:center;padding:16px 0;color:var(--muted);font-size:.82rem;">No members yet.</div>';
        } else {
            membersEl.innerHTML = members.map(m => {
                const initials = (m.full_name||'?').split(' ').map(n=>n[0]).join('').slice(0,2).toUpperCase();
                const roleCls = m.role === 'Officer' || m.role === 'President' || m.role === 'Vice President'
                    ? 'role-officer' : 'role-member';
                const avatar = m.profile_pic
                    ? `<img src="${m.profile_pic}" alt="${m.full_name}">`
                    : initials;

                // Online status pill
                const statusCls  = m.status_class  || 'offline';
                const statusLbl  = m.status_label  || 'Offline';
                const statusPill = `<span class="status-pill ${statusCls}">
                    <span class="status-dot-sm"></span>${statusLbl}
                </span>`;

                // Login credential row
                const loginRow = m.login_display
                    ? `<div class="member-login">
                        <i class="fa-solid ${m.login_display.includes('@') ? 'fa-envelope' : 'fa-phone'}"></i>
                        ${m.login_display}
                      </div>`
                    : '';

                const lastSeenRow = m.last_seen && m.last_seen !== 'Unknown'
                    ? `<div class="member-last-seen">${m.last_seen}</div>`
                    : '';

                return `<div class="member-row">
                    <div class="member-avatar">${avatar}</div>
                    <div class="member-info">
                        <div class="member-name">${m.full_name || '—'}</div>
                        <div class="member-role">${m.grade_section || m.role || 'Member'}</div>
                        ${loginRow}
                    </div>
                    <div class="member-status-col">
                        ${statusPill}
                        ${lastSeenRow}
                        <span class="member-badge ${roleCls}" style="margin-top:2px;">${m.role || 'Member'}</span>
                    </div>
                </div>`;
            }).join('');
        }

        // Manage button
        const manageBtn = document.getElementById('modal-manage-btn');
        if (manageBtn) manageBtn.onclick = () => window.location.href = `club_manage.php?id=${club.id}`;
    }

    function closeModal() {
        document.getElementById('club-modal').classList.remove('open');
        document.body.style.overflow = '';
    }

    function handleOverlayClick(e) {
        if (e.target === document.getElementById('club-modal')) closeModal();
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    /* -----------------------------------------------------------------
       APPROVE / REJECT REQUESTS
    ----------------------------------------------------------------- */
    function handleRequest(reqId, action, cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;

        const btns = card.querySelectorAll('button');
        btns.forEach(b => { b.disabled = true; b.style.opacity = '.5'; });

        fetch('clubs_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action === 'approve' ? 'approve_request' : 'reject_request',
                                   request_id: reqId, csrf: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('success', action === 'approve'
                    ? '✅ Club approved and created!'
                    : '❌ Request rejected.');

                card.style.transition = 'all .4s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    card.remove();
                    updateRequestCount(-1);

                    if (action === 'approve' && data.club) {
                        appendNewClub(data.club);
                        updateStat('stat-total', 1);
                        updateStat('stat-active', 1);
                    }
                }, 400);
            } else {
                showToast('error', data.message || 'Action failed.');
                btns.forEach(b => { b.disabled = false; b.style.opacity = '1'; });
            }
        })
        .catch(() => {
            // Demo mode — just animate removal
            card.style.transition = 'all .4s ease';
            card.style.opacity = '0';
            card.style.transform = 'translateX(20px)';
            setTimeout(() => { card.remove(); updateRequestCount(-1); }, 400);
            showToast('success', action === 'approve'
                ? '✅ Club approved! (Demo mode)'
                : '❌ Request rejected. (Demo mode)');
        });
    }

    function updateRequestCount(delta) {
        const badge = document.getElementById('req-count-badge');
        const current = parseInt(badge.textContent) || 0;
        const next = Math.max(0, current + delta);
        badge.textContent = next + ' Pending';
        if (next === 0) {
            const list = document.getElementById('requests-list');
            if (!list.querySelector('.request-card')) {
                list.innerHTML = `<div class="requests-empty">
                    <i class="fa-solid fa-inbox"></i>
                    No pending requests at the moment.
                </div>`;
            }
        }
    }

    function updateStat(id, delta) {
        const el = document.getElementById(id);
        if (el) el.textContent = parseInt(el.textContent || 0) + delta;
    }

    /* -----------------------------------------------------------------
       APPEND NEW APPROVED CLUB CARD
    ----------------------------------------------------------------- */
    function appendNewClub(club) {
        const emoji = CAT_EMOJI[club.category] || '🏫';
        const bc = BADGE_CLASS[club.category] || 'badge-other';
        const card = document.createElement('div');
        card.className = 'club-card';
        card.dataset.id = club.id;
        card.dataset.name = club.name;
        card.dataset.category = club.category || 'Other';
        card.dataset.status = 'Active';
        card.dataset.members = '0';
        card.setAttribute('onclick', `openClubModal(${club.id})`);
        card.style.opacity = '0';
        card.style.transform = 'scale(.9)';
        card.innerHTML = `
            <div class="club-status-dot status-active"></div>
            <div class="club-card-logo">${emoji}</div>
            <div class="club-card-name">${club.name}</div>
            <div class="club-card-desc">${club.description || ''}</div>
            <div class="club-card-footer">
                <div class="club-card-meta">
                    <div class="club-card-adviser"><i class="fa-solid fa-chalkboard-teacher"></i>${club.adviser_name || '—'}</div>
                    <div class="club-card-members"><i class="fa-solid fa-users"></i>0 members</div>
                </div>
                <span class="club-badge ${bc}">${club.category || 'Other'}</span>
            </div>`;
        document.getElementById('clubs-grid').prepend(card);
        requestAnimationFrame(() => {
            card.style.transition = 'all .4s cubic-bezier(.4,0,.2,1)';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        });
    }

    /* -----------------------------------------------------------------
       OPEN CREATE CLUB MODAL (placeholder)
    ----------------------------------------------------------------- */
    function openCreateClubModal() {
        // You can expand this into a full create-club form modal.
        window.location.href = 'club_create.php';
    }

    /* -----------------------------------------------------------------
       TOAST NOTIFICATION
    ----------------------------------------------------------------- */
    function showToast(type, msg) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        const icon = type === 'success' ? 'fa-circle-check'
                   : type === 'error'   ? 'fa-circle-xmark'
                   : 'fa-circle-info';
        toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${msg}`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.transition = 'all .3s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    </script>

    <script src="admin_assets/js/admin_script.js"></script>
</body>
</html>