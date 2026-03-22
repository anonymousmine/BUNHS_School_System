<?php
// ═══════════════════════════════════════════════════════════════════════════
//  finance.php  —  Cash Disbursement Register  (CDR)
//  Buyoan National High School — DepEd Division of Legazpi City
//  Fund Cluster: 101101
//  Upgraded: monthly grouping, carry-forward balances, per-UACS cards,
//            full CDR column parity with the xlsx file.
// ═══════════════════════════════════════════════════════════════════════════
session_start();
include '../db_connection.php';
assert($conn instanceof mysqli);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Month ordering & labels (matching xlsx sheet order) ──────────────────
$MONTH_ORDER = [
    'MAY' => 1,
    'JUNE' => 2,
    'JULY' => 3,
    'AUGUST' => 4,
    'SEPTEMBER' => 5,
    'OCTOBER' => 6,
    'NOVEMBER' => 7,
    'DECEMBER' => 8,
    'JANUARY 2026' => 9,
    'FEBRUARY 2026' => 10,
    'SARP' => 11,
    'TEENHUB' => 12,
    'SNED' => 13,
    'WINS' => 14,
];

// ── AJAX / POST handlers ─────────────────────────────────────────────────
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {

    header('Content-Type: application/json');
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // ── DELETE ────────────────────────────────────────────────────────
        if ($action === 'delete') {
            $id = intval($_POST['id']);
            $st = $conn->prepare("SELECT proof_image FROM finance_records WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
                exit;
            }
            $st = $conn->prepare("DELETE FROM finance_records WHERE id = ?");
            $st->bind_param("i", $id);
            $ok = $st->execute();
            $st->close();
            if ($ok && $row['proof_image']) {
                $f = "admin_assets/finance_proofs/" . $row['proof_image'];
                if (file_exists($f)) unlink($f);
            }
            echo json_encode($ok
                ? ['status' => 'success', 'message' => 'Record deleted successfully!']
                : ['status' => 'error', 'message' => 'Error deleting record.']);
            exit;
        }

        // ── EDIT ─────────────────────────────────────────────────────────
        if ($action === 'edit') {
            $id = intval($_POST['id']);
            [
                $fund_title,
                $description,
                $transaction_date,
                $category,
                $dv_check_no,
                $month_label,
                $cash_advance,
                $payments,
                $tax_withheld,
                $balance,
                $beginning_balance,
                $mooe_col,
                $electricity,
                $semi_expendable,
                $other_general,
                $training,
                $water,
                $other_supplies,
                $internet,
                $due_to_bir,
                $amount_other,
                $account_description,
                $uacs_code
            ]
                = _extract_post();

            $cdr_meta = _build_meta(compact(
                'dv_check_no',
                'cash_advance',
                'payments',
                'tax_withheld',
                'balance',
                'mooe_col',
                'electricity',
                'semi_expendable',
                'other_general',
                'training',
                'water',
                'other_supplies',
                'internet',
                'due_to_bir',
                'amount_other',
                'account_description',
                'uacs_code',
                'description'
            ));

            // handle optional image replacement
            $proof_image = null;
            $update_image = false;
            $img = _handle_upload();
            if ($img !== null) {
                $proof_image = $img;
                $update_image = true;
                // delete old
                $s2 = $conn->prepare("SELECT proof_image FROM finance_records WHERE id=?");
                $s2->bind_param("i", $id);
                $s2->execute();
                $old = $s2->get_result()->fetch_assoc();
                $s2->close();
                if ($old && $old['proof_image']) {
                    $of = "admin_assets/finance_proofs/" . $old['proof_image'];
                    if (file_exists($of)) unlink($of);
                }
            }

            if ($update_image) {
                $st = $conn->prepare("UPDATE finance_records SET fund_title=?,description=?,amount=?,transaction_date=?,category=?,month_label=?,beginning_balance=?,proof_image=?,updated_at=NOW() WHERE id=?");
                $st->bind_param("ssdsssdsi", $fund_title, $cdr_meta, $payments, $transaction_date, $category, $month_label, $beginning_balance, $proof_image, $id);
            } else {
                $st = $conn->prepare("UPDATE finance_records SET fund_title=?,description=?,amount=?,transaction_date=?,category=?,month_label=?,beginning_balance=?,updated_at=NOW() WHERE id=?");
                $st->bind_param("ssdsssdi", $fund_title, $cdr_meta, $payments, $transaction_date, $category, $month_label, $beginning_balance, $id);
            }
            $ok = $st->execute();
            $st->close();
            echo json_encode($ok
                ? ['status' => 'success', 'message' => 'Finance record updated successfully!']
                : ['status' => 'error', 'message' => 'Error updating record.']);
            exit;
        }

        // ── ADD ───────────────────────────────────────────────────────────
        [
            $fund_title,
            $description,
            $transaction_date,
            $category,
            $dv_check_no,
            $month_label,
            $cash_advance,
            $payments,
            $tax_withheld,
            $balance,
            $beginning_balance,
            $mooe_col,
            $electricity,
            $semi_expendable,
            $other_general,
            $training,
            $water,
            $other_supplies,
            $internet,
            $due_to_bir,
            $amount_other,
            $account_description,
            $uacs_code
        ]
            = _extract_post();

        $cdr_meta = _build_meta(compact(
            'dv_check_no',
            'cash_advance',
            'payments',
            'tax_withheld',
            'balance',
            'mooe_col',
            'electricity',
            'semi_expendable',
            'other_general',
            'training',
            'water',
            'other_supplies',
            'internet',
            'due_to_bir',
            'amount_other',
            'account_description',
            'uacs_code',
            'description'
        ));
        $proof_image = _handle_upload() ?? '';

        $st = $conn->prepare("INSERT INTO finance_records
            (fund_title,description,amount,transaction_date,category,month_label,beginning_balance,proof_image,created_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())");
        $st->bind_param("ssdsssds", $fund_title, $cdr_meta, $payments, $transaction_date, $category, $month_label, $beginning_balance, $proof_image);
        $ok = $st->execute();
        $st->close();
        echo json_encode($ok
            ? ['status' => 'success', 'message' => 'Finance record added successfully!']
            : ['status' => 'error', 'message' => 'Error adding finance record.']);
        exit;
    }

    // ── CSV EXPORT ────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['export'] ?? '') === 'csv') {
        $res = $conn->query("SELECT * FROM finance_records ORDER BY month_label,transaction_date,created_at");
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="CDR_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'ID',
            'Month',
            'Date',
            'DV/Check No',
            'Particulars/Supplier',
            'Purpose',
            'Cash Advance',
            'Payments',
            'Tax Withheld',
            'Balance',
            'Beginning Balance',
            'Electricity',
            'Training',
            'Semi-Exp ICT',
            'Other Gen Svc',
            'Other Supplies',
            'Water',
            'Semi-Exp Other',
            'Internet',
            'Office Supplies',
            'Other Gen Svc 2',
            'Account Desc',
            'UACS Code',
            'Amount',
            'Category',
            'Created At'
        ]);
        while ($row = $res->fetch_assoc()) {
            $m = json_decode($row['description'] ?? '{}', true) ?: [];
            fputcsv($out, [
                $row['id'],
                $row['month_label'] ?? '',
                $row['transaction_date'],
                $m['dv_check_no'] ?? 'DV-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
                $row['fund_title'],
                $m['note'] ?? '',
                $m['cash_advance'] ?? 0,
                $m['payments'] ?? $row['amount'],
                $m['tax_withheld'] ?? 0,
                $m['balance'] ?? 0,
                $row['beginning_balance'] ?? 0,
                $m['electricity'] ?? 0,
                $m['training'] ?? 0,
                $m['semi_expendable'] ?? 0,
                $m['other_general'] ?? 0,
                $m['other_supplies'] ?? 0,
                $m['water'] ?? 0,
                0,
                $m['internet'] ?? 0,
                $m['due_to_bir'] ?? 0,
                0,
                $m['account_description'] ?? '',
                $m['uacs_code'] ?? '',
                $m['amount_other'] ?? 0,
                $row['category'],
                $row['created_at']
            ]);
        }
        fclose($out);
        exit;
    }
    exit;
}

// ── Helper functions ──────────────────────────────────────────────────────
function _extract_post(): array
{
    $p = static fn(string $k, $def = '') => isset($_POST[$k]) ? trim($_POST[$k]) : $def;
    $f = static fn(string $k) => floatval($_POST[$k] ?? 0);
    return [
        $p('fund_title'),
        $p('description'),
        $p('transaction_date') ?: date('Y-m-d'),
        $p('category'),
        $p('dv_check_no'),
        strtoupper($p('month_label', 'MAY')),
        $f('cash_advance'),
        $f('payments'),
        $f('tax_withheld'),
        $f('balance'),
        $f('beginning_balance'),
        $p('mooe_col'),
        $f('electricity'),
        $f('semi_expendable'),
        $f('other_general'),
        $f('training'),
        $f('water'),
        $f('other_supplies'),
        $f('internet'),
        $f('due_to_bir'),
        $f('amount_other'),
        $p('account_description'),
        $p('uacs_code'),
    ];
}

function _build_meta(array $d): string
{
    return json_encode([
        'dv_check_no' => $d['dv_check_no'],
        'cash_advance' => $d['cash_advance'],
        'payments' => $d['payments'],
        'tax_withheld' => $d['tax_withheld'],
        'balance' => $d['balance'],
        'mooe_col' => $d['mooe_col'],
        'electricity' => $d['electricity'],
        'semi_expendable' => $d['semi_expendable'],
        'other_general' => $d['other_general'],
        'training' => $d['training'],
        'water' => $d['water'],
        'other_supplies' => $d['other_supplies'],
        'internet' => $d['internet'],
        'due_to_bir' => $d['due_to_bir'],
        'amount_other' => $d['amount_other'],
        'account_description' => $d['account_description'],
        'uacs_code' => $d['uacs_code'],
        'note' => $d['description'],
    ]);
}

function _handle_upload(): ?string
{
    if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== 0) return null;
    $dir = "admin_assets/finance_proofs/";
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return null;
    if ($_FILES['proof_image']['size'] > 5 * 1024 * 1024) return null;
    $name = uniqid() . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $dir . $name)) return $name;
    return null;
}

// ── Pagination & filtering ────────────────────────────────────────────────
$records_per_page = 25;
$page             = max(1, intval($_GET['page'] ?? 1));
$search           = trim($_GET['search'] ?? '');
$month_filter     = trim($_GET['month'] ?? '');
$offset           = ($page - 1) * $records_per_page;

$where_parts = [];
$params = [];
$ptypes = '';
if ($search) {
    $where_parts[] = "(fund_title LIKE ? OR description LIKE ?)";
    $sp = "%$search%";
    $params[] = &$sp;
    $params[] = &$sp;
    $ptypes .= 'ss';
}
if ($month_filter) {
    $where_parts[] = "month_label = ?";
    $params[] = &$month_filter;
    $ptypes .= 's';
}
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// total count
$total_records = 0;
if ($params) {
    $cs = $conn->prepare("SELECT COUNT(*) AS t FROM finance_records $where");
    $cs->bind_param($ptypes, ...$params);
    $cs->execute();
    $total_records = (int)($cs->get_result()->fetch_assoc()['t'] ?? 0);
    $cs->close();
} else {
    $cr = $conn->query("SELECT COUNT(*) AS t FROM finance_records $where");
    $total_records = (int)($cr->fetch_assoc()['t'] ?? 0);
}
$total_pages = max(1, ceil($total_records / $records_per_page));

// paginated records
$finance_records = [];
$lp = $records_per_page;
$lo = $offset;
$params[] = &$lp;
$params[] = &$lo;
$ptypes .= 'ii';
$rs = $conn->prepare("SELECT * FROM finance_records $where ORDER BY month_label,transaction_date,created_at LIMIT ? OFFSET ?");
if ($rs) {
    $rs->bind_param($ptypes, ...$params);
    $rs->execute();
    $res = $rs->get_result();
    while ($r = $res->fetch_assoc()) $finance_records[] = $r;
    $rs->close();
}

// ALL records for stats & monthly grouping
$all_records = [];
$ar = $conn->query("SELECT * FROM finance_records ORDER BY month_label,transaction_date,created_at");
if ($ar) while ($r = $ar->fetch_assoc()) $all_records[] = $r;

// ── Build monthly groups from ALL records ─────────────────────────────────
$month_groups = [];
$col_map = [
    'Utilities' => 'electricity',
    'Equipment' => 'semi_expendable',
    'Salaries' => 'other_general',
    'Other' => 'other_general',
    'Events' => 'training',
    'Sports' => 'training',
    'Transportation' => 'training',
    'Maintenance' => 'water',
    'Supplies' => 'other_supplies',
    'Books' => 'other_supplies',
];

foreach ($all_records as $rec) {
    $ml = strtoupper(trim($rec['month_label'] ?? 'UNCATEGORISED'));
    if (!isset($month_groups[$ml])) {
        $month_groups[$ml] = [
            'label' => $ml,
            'beginning_balance' => floatval($rec['beginning_balance'] ?? 0),
            'records' => [],
            'cash_advance_total' => 0,
            'payments_total' => 0,
            'tax_total' => 0,
            'col_totals' => array_fill_keys(['electricity', 'semi_expendable', 'other_general', 'training', 'water', 'other_supplies', 'internet', 'due_to_bir', 'amount_other'], 0),
        ];
    }
    $cdr = json_decode($rec['description'] ?? '{}', true) ?: [];
    $ca  = floatval($cdr['cash_advance'] ?? 0);
    $pay = floatval($cdr['payments']     ?? $rec['amount']);
    $tax = floatval($cdr['tax_withheld'] ?? 0);
    $month_groups[$ml]['cash_advance_total'] += $ca;
    $month_groups[$ml]['payments_total']     += $pay;
    $month_groups[$ml]['tax_total']          += $tax;
    // MOOE cols
    $mooe_fields = ['electricity', 'semi_expendable', 'other_general', 'training', 'water', 'other_supplies', 'internet', 'due_to_bir', 'amount_other'];
    $any = false;
    foreach ($mooe_fields as $mf) {
        $v = floatval($cdr[$mf] ?? 0);
        if ($v > 0) {
            $month_groups[$ml]['col_totals'][$mf] += $v;
            $any = true;
        }
    }
    if (!$any) {
        $col = (!empty($cdr['mooe_col']) ? $cdr['mooe_col'] : null) ?? ($col_map[$rec['category']] ?? 'other_general');
        $month_groups[$ml]['col_totals'][$col] += floatval($rec['amount']);
    }
    $month_groups[$ml]['records'][] = $rec;
}

// Sort month groups
uksort($month_groups, fn($a, $b) => ($MONTH_ORDER[$a] ?? 99) <=> ($MONTH_ORDER[$b] ?? 99));

// ── Global stats ──────────────────────────────────────────────────────────
$global_cash_advance = 0;
$global_payments = 0;
$global_tax = 0;
$global_beginning    = 0; // from first month
$uacs_totals = array_fill_keys(['electricity', 'semi_expendable', 'other_general', 'training', 'water', 'other_supplies', 'internet', 'due_to_bir'], 0);
$current_month_label = strtoupper(date('F')); // e.g. MARCH
$prev_month_label    = strtoupper(date('F', strtotime('-1 month')));
$current_month_total = 0;
$prev_month_total = 0;
$highest = ['amount' => 0, 'title' => ''];

$first_month = true;
foreach ($month_groups as $mg) {
    if ($first_month) {
        $global_beginning = $mg['beginning_balance'];
        $first_month = false;
    }
    $global_cash_advance += $mg['cash_advance_total'];
    $global_payments     += $mg['payments_total'];
    $global_tax          += $mg['tax_total'];
    foreach ($uacs_totals as $k => $_) $uacs_totals[$k] += $mg['col_totals'][$k];
    if (stripos($mg['label'], $current_month_label) !== false) $current_month_total = $mg['payments_total'];
    if (stripos($mg['label'], $prev_month_label)    !== false) $prev_month_total    = $mg['payments_total'];
}
// Overall ending balance
$global_ending = $global_beginning + $global_cash_advance - $global_payments - $global_tax;

foreach ($all_records as $rec) {
    $cdr = json_decode($rec['description'] ?? '{}', true) ?: [];
    $pay = floatval($cdr['payments'] ?? $rec['amount']);
    if ($pay > $highest['amount']) $highest = ['amount' => $pay, 'title' => $rec['fund_title']];
}
$mom_change = $prev_month_total > 0 ? (($current_month_total - $prev_month_total) / $prev_month_total) * 100 : 0;

// ── Fetch signatories ────────────────────────────────────────────────────
$principal_name  = 'JOJO D. APULI';
$principal_title = 'School Principal I';
$ar2 = $conn->query("SELECT full_name,title FROM admin ORDER BY id ASC LIMIT 1");
if ($ar2 && $ar2->num_rows > 0) {
    $r2 = $ar2->fetch_assoc();
    if (!empty($r2['full_name'])) $principal_name  = strtoupper($r2['full_name']);
    if (!empty($r2['title']))     $principal_title = $r2['title'];
}
$bookkeeper_name  = 'SHANE V. BOLAÑOS';
$bookkeeper_title = 'Senior Bookkeeper-Designate';
$bk = $conn->query("SELECT CONCAT(first_name,' ',last_name) AS full_name FROM sub_admin WHERE FIND_IN_SET('senior_bookkeeper',role)>0 AND status='approved' ORDER BY id ASC LIMIT 1");
if ($bk && $bk->num_rows > 0) {
    $bkr = $bk->fetch_assoc();
    if (!empty($bkr['full_name'])) $bookkeeper_name = strtoupper(trim($bkr['full_name']));
}

// distinct month labels for filter dropdown
$distinct_months = array_keys($month_groups);

$conn->close();

// ── UACS labels for cards ─────────────────────────────────────────────────
$uacs_labels = [
    'electricity'    => ['label' => 'Electricity Expenses',        'code' => '5020402000', 'icon' => 'fa-bolt',          'color' => 'orange'],
    'training'       => ['label' => 'Training Expenses',           'code' => '5020201000', 'icon' => 'fa-chalkboard-teacher', 'color' => 'blue'],
    'semi_expendable' => ['label' => 'Semi-Expendable ICT',         'code' => '50203210',  'icon' => 'fa-laptop',        'color' => 'purple'],
    'other_general'  => ['label' => 'Other General Services',      'code' => '5021299000', 'icon' => 'fa-concierge-bell', 'color' => 'teal'],
    'other_supplies' => ['label' => 'Other Supplies & Materials',  'code' => '5020399000', 'icon' => 'fa-box-open',      'color' => 'green'],
    'water'          => ['label' => 'Water Expenses',              'code' => '5020401000', 'icon' => 'fa-tint',          'color' => 'cyan'],
    'internet'       => ['label' => 'Internet Subscription',       'code' => '5020503000', 'icon' => 'fa-wifi',          'color' => 'indigo'],
    'due_to_bir'     => ['label' => 'Office Supplies Expenses',    'code' => '5020301002', 'icon' => 'fa-file-invoice',  'color' => 'rose'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Finance — Cash Disbursement Register · Buyoan NHS</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap');

        :root {
            --primary-color: #5c7a3e;
            --primary-light: #7a9e56;
            --primary-dim: rgba(92, 122, 62, 0.08);
            --secondary-color: #1e6b53;
            --success-color: #16a34a;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --info-color: #2563eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border-color: #e5e7eb;
            --border-light: #f3f4f6;
            --light-color: #f8fafc;
            --bg-page: #f1f5f0;
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, .05);
            --shadow: 0 1px 4px rgba(0, 0, 0, .07), 0 4px 12px rgba(0, 0, 0, .04);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, .08), 0 1px 4px rgba(0, 0, 0, .05);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, .14), 0 2px 8px rgba(0, 0, 0, .06);
            --shadow-xl: 0 24px 64px rgba(0, 0, 0, .18);
            --radius-sm: 6px;
            --radius: 10px;
            --radius-lg: 14px;
            --radius-xl: 20px;
            --gradient-primary: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            --gradient-success: linear-gradient(135deg, #16a34a, #15803d);
            --gradient-danger: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-page);
            color: var(--text-primary);
        }

        /* ── PAGE HEADER ─────────────────────────────────────────────────────── */
        .page-title {
            margin-bottom: 0;
            padding: 28px 36px 20px;
            background: linear-gradient(160deg, #ecf2e8 0%, #e8f0f5 100%);
            border-bottom: 1px solid rgba(92, 122, 62, .15);
            width: 100%;
        }

        .page-title .heading {
            padding: 0;
            width: 100%;
        }

        .page-title .heading-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -.4px;
        }

        .page-title p {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        .breadcrumbs {
            padding: 12px 36px !important;
            background: rgba(255, 255, 255, .7) !important;
            border-bottom: 1px solid var(--border-color);
            width: 100%;
            font-size: 13px;
        }

        .finance-section {
            padding: 24px 36px 36px;
            width: 100%;
        }

        /* ── STAT CARDS ──────────────────────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: var(--shadow);
            transition: transform .22s ease, box-shadow .22s ease;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .stat-card.green::before {
            background: linear-gradient(90deg, #16a34a, #22c55e);
        }

        .stat-card.blue::before {
            background: linear-gradient(90deg, #2563eb, #60a5fa);
        }

        .stat-card.orange::before {
            background: linear-gradient(90deg, #d97706, #fbbf24);
        }

        .stat-card.purple::before {
            background: linear-gradient(90deg, #7c3aed, #a78bfa);
        }

        .stat-card.red::before {
            background: linear-gradient(90deg, #dc2626, #f87171);
        }

        .stat-card.teal::before {
            background: linear-gradient(90deg, #0d9488, #2dd4bf);
        }

        .stat-card.cyan::before {
            background: linear-gradient(90deg, #0891b2, #22d3ee);
        }

        .stat-card.indigo::before {
            background: linear-gradient(90deg, #4f46e5, #818cf8);
        }

        .stat-card.rose::before {
            background: linear-gradient(90deg, #e11d48, #fb7185);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .stat-icon.green {
            background: #dcfce7;
            color: #16a34a;
        }

        .stat-icon.blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .stat-icon.orange {
            background: #fef3c7;
            color: #d97706;
        }

        .stat-icon.purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .stat-icon.red {
            background: #fee2e2;
            color: #dc2626;
        }

        .stat-icon.teal {
            background: #ccfbf1;
            color: #0d9488;
        }

        .stat-icon.cyan {
            background: #cffafe;
            color: #0891b2;
        }

        .stat-icon.indigo {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .stat-icon.rose {
            background: #ffe4e6;
            color: #e11d48;
        }

        .stat-icon i {
            font-size: 18px;
        }

        .stat-content {
            flex: 1;
            min-width: 0;
        }

        .stat-label {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.1;
            letter-spacing: -.5px;
            font-feature-settings: "tnum";
        }

        .stat-change {
            font-size: 11.5px;
            margin-top: 5px;
            font-weight: 500;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        /* ── ENDING BALANCE HERO CARD ────────────────────────────────────────── */
        .balance-hero {
            background: var(--gradient-primary);
            border-radius: var(--radius-xl);
            padding: 28px 32px;
            color: #fff;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(92, 122, 62, .35);
        }

        .balance-hero::after {
            content: '';
            position: absolute;
            right: -40px;
            top: -40px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
        }

        .balance-hero .hero-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .8px;
            opacity: .8;
            margin-bottom: 6px;
        }

        .balance-hero .hero-amount {
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1.5px;
            font-feature-settings: "tnum";
        }

        .balance-hero .hero-sub {
            font-size: 13px;
            opacity: .75;
            margin-top: 8px;
        }

        .balance-hero .hero-pills {
            display: flex;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .balance-hero .hero-pill {
            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 30px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
        }

        .balance-hero .hero-pill span {
            opacity: .75;
            font-weight: 400;
        }

        /* ── MONTHLY SUMMARY CARDS ───────────────────────────────────────────── */
        .month-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .month-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            box-shadow: var(--shadow);
            transition: transform .18s;
        }

        .month-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .month-card .mc-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--primary-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .month-card .mc-label i {
            font-size: 10px;
        }

        .month-card .mc-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 3px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .month-card .mc-row:last-child {
            border-bottom: none;
        }

        .month-card .mc-key {
            color: var(--text-secondary);
        }

        .month-card .mc-val {
            font-weight: 600;
            font-feature-settings: "tnum";
        }

        .month-card .mc-ending {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 8px;
            padding-top: 6px;
            border-top: 2px solid var(--primary-dim);
            display: flex;
            justify-content: space-between;
        }

        /* ── UACS CARDS ──────────────────────────────────────────────────────── */
        .uacs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .uacs-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .uacs-card .uc-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 15px;
        }

        .uacs-card .uc-content .uc-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .uacs-card .uc-content .uc-code {
            font-size: 9.5px;
            color: var(--text-muted);
            font-family: 'DM Mono', monospace;
            margin-top: 1px;
        }

        .uacs-card .uc-content .uc-val {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            font-feature-settings: "tnum";
            margin-top: 3px;
        }

        /* ── CARD ─────────────────────────────────────────────────────────────── */
        .card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            background: #fff;
            overflow: hidden;
        }

        .card-header {
            border-bottom: 1px solid var(--border-light);
            background: #fff;
            padding: 16px 22px;
        }

        .card-header h5 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -.2px;
        }

        .card-body {
            padding: 20px 22px;
        }

        /* ── FILTER BAR ──────────────────────────────────────────────────────── */
        .filter-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 240px;
        }

        .search-box input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text-primary);
            background: var(--light-color);
            transition: all .2s;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(92, 122, 62, .1);
            outline: none;
            background: #fff;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
        }

        .filter-select {
            padding: 9px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            background: var(--light-color);
            min-width: 160px;
            cursor: pointer;
            color: var(--text-primary);
            transition: all .2s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
        }

        .filter-select:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .btn-export {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border: none;
            color: #fff;
            padding: 9px 16px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .btn-export:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(79, 70, 229, .35);
        }

        /* ── BUTTONS ──────────────────────────────────────────────────────────── */
        .btn {
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            border-radius: var(--radius);
            padding: 8px 16px;
            font-size: 13px;
            transition: all .18s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: .1px;
            border: none;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 11.5px;
            border-radius: var(--radius-sm);
        }

        .btn-outline-primary {
            border: 1.5px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary-dim);
        }

        .btn-outline-secondary {
            border: 1.5px solid var(--border-color);
            color: var(--text-secondary);
            background: transparent;
        }

        .btn-outline-secondary:hover {
            background: var(--border-light);
        }

        .btn-success {
            background: var(--gradient-success);
            color: #fff;
            box-shadow: 0 4px 12px rgba(22, 163, 74, .25);
        }

        .btn-success:hover {
            opacity: .9;
        }

        .btn-info {
            background: linear-gradient(135deg, #2d5c43, #3c785a);
            color: #fff;
        }

        .btn-info:hover {
            opacity: .9;
        }

        .btn-danger {
            background: var(--gradient-danger);
            color: #fff;
        }

        .btn-danger:hover {
            opacity: .9;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        /* ── SECTION DIVIDER (month group header) ─────────────────────────────── */
        .month-divider {
            background: linear-gradient(135deg, #e8f2e2, #dceae8);
            border-left: 4px solid var(--primary-color);
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: #1e3d2f;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .month-divider .md-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .month-divider .md-right {
            display: flex;
            gap: 20px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .month-divider .md-pill {
            background: rgba(255, 255, 255, .6);
            border: 1px solid rgba(92, 122, 62, .2);
            border-radius: 20px;
            padding: 2px 10px;
        }

        /* ══════════════════════════════════════════════════════════════════════
   CASH DISBURSEMENT REGISTER TABLE — exact xlsx column replica
   ══════════════════════════════════════════════════════════════════════ */
        .cdr-doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 18px 24px 12px;
            border-bottom: 1px solid #ccc;
            font-size: 11.5px;
            color: #000;
            line-height: 1.7;
        }

        .cdr-doc-header .left-info div,
        .cdr-doc-header .right-info div {
            white-space: nowrap;
        }

        .cdr-wrapper {
            width: 100%;
            overflow-x: auto;
            background: #fff;
        }

        .cdr-table {
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 11px;
            color: #000;
            font-family: Arial, sans-serif;
            min-width: 2260px;
            width: 100%;
        }

        .cdr-table th,
        .cdr-table td {
            border: 1px solid #999;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
            word-wrap: break-word;
            padding: 2px 3px;
            box-sizing: border-box;
            line-height: 1.3;
        }

        .cdr-table thead tr.cdr-r1 th {
            background: #f0f0f0;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .6px;
            height: 36px;
        }

        .cdr-table thead tr.cdr-r2 th {
            background: #e8e8e8;
            font-size: 11px;
            font-weight: 700;
            height: 42px;
            white-space: normal;
            word-break: break-word;
        }

        .cdr-table thead tr.cdr-r3 th {
            background: #f0f0f0;
            font-size: 10px;
            font-weight: 700;
            height: 22px;
        }

        .cdr-table thead tr.cdr-r3b th {
            background: #f0f0f0;
            font-size: 10px;
            font-weight: 700;
            height: 28px;
        }

        .cdr-table thead tr.cdr-r4 th {
            background: #f5f5f5;
            font-size: 10px;
            font-weight: 700;
            height: 93px;
            width: 93px;
            white-space: normal;
            word-break: break-word;
        }

        .cdr-table thead tr.cdr-r5 th {
            background: #f5f5f5;
            font-size: 9px;
            font-weight: 400;
            height: 28px;
            color: #333;
        }

        .cdr-table tbody td {
            height: 28px;
            font-size: 11px;
        }

        .cdr-table tbody td.cdr-part,
        .cdr-table tfoot td.cdr-part {
            text-align: left;
            padding-left: 5px;
        }

        .cdr-table tbody td.cdr-num,
        .cdr-table tfoot td.cdr-num {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            font-size: 10.5px;
        }

        .cdr-table tbody tr:hover td {
            background: #f8fcf5;
        }

        /* Month balance row */
        .cdr-table tbody tr.cdr-bal-row td {
            background: #fffce8;
            font-weight: 700;
            font-size: 10.5px;
        }

        .cdr-table tbody tr.cdr-adv-row td {
            background: #e8f5e9;
            font-weight: 700;
            font-size: 10.5px;
        }

        /* Totals footer */
        .cdr-table tfoot td {
            font-weight: 700;
            background: #efefef;
            font-size: 11px;
        }

        /* Signature footer */
        .cdr-footer {
            display: flex;
            justify-content: space-between;
            padding: 28px 40px 20px;
            font-size: 11.5px;
            color: #000;
        }

        .cdr-footer .sig-block {
            text-align: center;
            min-width: 220px;
        }

        .cdr-footer .sig-label {
            font-size: 10px;
            color: #555;
            margin-bottom: 28px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .cdr-footer .sig-name {
            font-weight: 700;
            font-size: 12px;
            border-top: 1px solid #000;
            padding-top: 4px;
        }

        .cdr-footer .sig-title {
            font-size: 10.5px;
            color: #333;
        }

        /* ── PAGINATION ───────────────────────────────────────────────────────── */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 22px;
            border-top: 1px solid var(--border-light);
            font-size: 13px;
        }

        .pagination-info {
            color: var(--text-secondary);
        }

        .pagination {
            display: flex;
            list-style: none;
            gap: 4px;
            margin: 0;
            padding: 0;
        }

        .pagination li a,
        .pagination li span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all .15s;
        }

        .pagination li.active span {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }

        .pagination li.disabled span {
            opacity: .4;
            cursor: default;
        }

        .pagination li a:hover {
            background: var(--primary-dim);
            border-color: var(--primary-color);
        }

        /* ── POPUP OVERLAY ────────────────────────────────────────────────────── */
        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            backdrop-filter: blur(3px);
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all .25s;
        }

        .popup-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .popup-card {
            background: #fff;
            border-radius: var(--radius-xl);
            width: min(720px, 95vw);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-xl);
            transform: scale(.96);
            transition: transform .25s;
        }

        .popup-overlay.active .popup-card {
            transform: scale(1);
        }

        .popup-card.wide {
            width: min(880px, 95vw);
        }

        .popup-card .card-header {
            background: linear-gradient(135deg, #1d3d28 0%, #2d5c43 50%, #3c785a 100%);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            padding: 22px 28px;
            position: relative;
            overflow: hidden;
            border-bottom: none;
            flex-shrink: 0;
        }

        .popup-card .card-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 80% 50%, rgba(120, 200, 160, .18) 0%, transparent 70%);
        }

        .popup-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 5;
        }

        .popup-header-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .2);
            color: rgba(255, 255, 255, .9);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 8px;
        }

        .popup-card .card-header h5 {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 4px;
            letter-spacing: -.3px;
        }

        .popup-header-sub {
            color: rgba(255, 255, 255, .65);
            font-size: 12.5px;
        }

        .popup-close-btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .2);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: background .15s;
            flex-shrink: 0;
        }

        .popup-close-btn:hover {
            background: rgba(255, 255, 255, .22);
        }

        .popup-card .card-body {
            overflow-y: auto;
            padding: 24px 28px;
            flex: 1;
        }

        .popup-card .card-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 24px;
            background: var(--light-color);
            border-top: 1px solid var(--border-light);
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
            flex-shrink: 0;
        }

        .popup-card .card-footer .btn {
            border-radius: 12px;
            padding: 10px 20px;
            font-size: .84rem;
            font-weight: 700;
        }

        /* Form styles */
        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
            display: block;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 9px 13px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text-primary);
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(92, 122, 62, .1);
            outline: none;
        }

        .form-section-title {
            font-size: .67rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: #2d5c43;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 0 10px;
            border-bottom: 2px solid #c5e8d5;
            width: 100%;
            margin-bottom: 4px;
        }

        .form-section-title i {
            width: 22px;
            height: 22px;
            background: #d4f0e2;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            color: #2d5c43;
        }

        /* Upload zone */
        .upload-zone {
            border: 2px dashed #d4d0ee;
            border-radius: 16px;
            padding: 24px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            background: #faf8ff;
            position: relative;
        }

        .upload-zone:hover {
            border-color: #3c785a;
            background: #edf8f3;
        }

        .upload-zone input[type=file] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .image-preview {
            display: none;
            margin-top: 14px;
            position: relative;
        }

        .image-preview.show {
            display: block;
        }

        .image-preview img {
            width: 100%;
            max-height: 180px;
            object-fit: cover;
            border-radius: var(--radius);
        }

        /* Delete popup */
        .popup-card.delete-popup .card-header {
            background: linear-gradient(135deg, #2b0d0d 0%, #471a1a 50%, #632a2a 100%);
        }

        .delete-icon-circle {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delete-icon-circle i {
            font-size: 28px;
            color: var(--danger-color);
        }

        /* Spinner / Toast */
        .spinner-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .75);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all .25s;
        }

        .spinner-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .spinner-border {
            width: 2.8rem;
            height: 2.8rem;
            border: 3px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin .85s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .toast-container {
            z-index: 10000;
        }

        .custom-toast {
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            font-family: 'DM Sans', sans-serif;
            min-width: 280px;
        }

        .toast.success {
            background: var(--gradient-success);
            color: #fff;
            border: none;
        }

        .toast.error {
            background: var(--gradient-danger);
            color: #fff;
            border: none;
        }

        .toast .toast-header {
            background: transparent;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, .2);
            font-weight: 600;
        }

        .toast .btn-close {
            filter: invert(1);
        }

        /* Responsive */
        @media(max-width:992px) {
            .finance-section {
                padding: 16px;
            }

            .page-title {
                padding: 18px 16px 14px;
            }

            .breadcrumbs {
                padding: 12px 16px !important;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-bar {
                flex-direction: column;
            }

            .search-box,
            .filter-select,
            .btn-export {
                width: 100%;
            }
        }

        @media(max-width:640px) {

            .stats-grid,
            .month-summary-grid,
            .uacs-grid {
                grid-template-columns: 1fr;
            }

            .popup-card {
                width: 95%;
            }
        }

        /* Section toggle */
        .section-toggle {
            cursor: pointer;
            user-select: none;
        }

        .section-toggle .toggle-icon {
            transition: transform .2s;
        }

        .section-collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .section-body-collapsible {
            overflow: hidden;
            transition: max-height .3s ease;
        }

        /* Table view switcher */
        .view-tabs {
            display: flex;
            gap: 0;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }

        .view-tab {
            padding: 7px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: #fff;
            border: none;
            transition: all .15s;
            color: var(--text-secondary);
        }

        .view-tab.active {
            background: var(--primary-color);
            color: #fff;
        }
    </style>
</head>

<body>
    <div id="navigation-container"></div>

    <div class="spinner-overlay" id="loadingOverlay">
        <div style="text-align:center;">
            <div class="spinner-border"></div>
            <div style="margin-top:12px;color:var(--text-secondary);font-size:13px;font-weight:500;">Processing…</div>
        </div>
    </div>

    <main class="main page-content" style="margin-left:0;width:100%;max-width:100%;">
        <!-- ── PAGE HEADER ── -->
        <div class="page-title">
            <div class="heading">
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="width:42px;height:42px;background:var(--gradient-primary);border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(92,122,62,.3);">
                        <i class="fas fa-wallet" style="color:#fff;font-size:17px;"></i>
                    </div>
                    <div>
                        <h1 class="heading-title" style="margin:0;">Finance Management</h1>
                        <p class="mb-0" style="font-size:13px;color:var(--text-secondary);">Cash Disbursement Register — Buyoan NHS · Fund Cluster 101101</p>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <ol style="max-width:100%;padding:0;margin:0;display:flex;gap:6px;align-items:center;list-style:none;">
                    <li><a href="admin_dashboard.php" style="color:var(--text-secondary);text-decoration:none;font-size:13px;">Home</a></li>
                    <li style="color:var(--text-muted);font-size:12px;"><i class="fas fa-chevron-right"></i></li>
                    <li aria-current="page" style="color:var(--primary-color);font-weight:600;font-size:13px;">Finance</li>
                </ol>
            </nav>
        </div>

        <section class="finance-section">

            <!-- ── ENDING BALANCE HERO ── -->
            <div class="balance-hero">
                <div class="hero-label"><i class="fas fa-coins me-1"></i> Overall Available Funds</div>
                <div class="hero-amount">₱<?php echo number_format(max(0, $global_ending), 2); ?></div>
                <div class="hero-sub">
                    Beginning Balance + Cash Advances − Payments − Tax Withheld
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><span>Beg. Balance: </span>₱<?php echo number_format($global_beginning, 2); ?></div>
                    <div class="hero-pill"><span>+ Cash Advances: </span>₱<?php echo number_format($global_cash_advance, 2); ?></div>
                    <div class="hero-pill"><span>− Payments: </span>₱<?php echo number_format($global_payments, 2); ?></div>
                    <div class="hero-pill"><span>− Tax: </span>₱<?php echo number_format($global_tax, 2); ?></div>
                </div>
            </div>

            <!-- ── STAT CARDS ── -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">This Month Payments</div>
                        <div class="stat-value">₱<?php echo number_format($current_month_total, 2); ?></div>
                        <?php if ($mom_change != 0): ?>
                            <div class="stat-change <?php echo $mom_change >= 0 ? 'negative' : 'positive'; ?>">
                                <i class="fas fa-arrow-<?php echo $mom_change >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo number_format(abs($mom_change), 1); ?>% vs last month
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon green"><i class="fas fa-arrow-circle-down"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Cash Advances</div>
                        <div class="stat-value">₱<?php echo number_format($global_cash_advance, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-icon orange"><i class="fas fa-arrow-circle-up"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Payments</div>
                        <div class="stat-value">₱<?php echo number_format($global_payments, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card red">
                    <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Tax Withheld</div>
                        <div class="stat-value">₱<?php echo number_format($global_tax, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon purple"><i class="fas fa-layer-group"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Transactions</div>
                        <div class="stat-value"><?php echo count($all_records); ?></div>
                        <div class="stat-change"><?php echo count($month_groups); ?> month groups</div>
                    </div>
                </div>
            </div>

            <!-- ── MONTHLY SUMMARY CARDS ── -->
            <?php if (!empty($month_groups)): ?>
                <div style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
                    <h6 style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.6px;margin:0;">
                        <i class="fas fa-calendar-week me-2" style="color:var(--primary-color);"></i>Monthly Summary
                    </h6>
                </div>
                <div class="month-summary-grid">
                    <?php
                    $carry = 0;
                    foreach ($month_groups as $mg):
                        $beg = $mg['beginning_balance'] ?: $carry;
                        $end = $beg + $mg['cash_advance_total'] - $mg['payments_total'] - $mg['tax_total'];
                        $carry = $end;
                    ?>
                        <div class="month-card">
                            <div class="mc-label"><i class="fas fa-calendar-day"></i><?php echo htmlspecialchars($mg['label']); ?></div>
                            <div class="mc-row"><span class="mc-key">Beg. Balance</span><span class="mc-val">₱<?php echo number_format($beg, 2); ?></span></div>
                            <div class="mc-row"><span class="mc-key">+ Cash Advances</span><span class="mc-val text-success">₱<?php echo number_format($mg['cash_advance_total'], 2); ?></span></div>
                            <div class="mc-row"><span class="mc-key">− Payments</span><span class="mc-val">₱<?php echo number_format($mg['payments_total'], 2); ?></span></div>
                            <div class="mc-row"><span class="mc-key">− Tax Withheld</span><span class="mc-val">₱<?php echo number_format($mg['tax_total'], 2); ?></span></div>
                            <div class="mc-row"><span class="mc-key">Transactions</span><span class="mc-val"><?php echo count($mg['records']); ?></span></div>
                            <div class="mc-ending"><span>Ending Balance</span><span>₱<?php echo number_format(max(0, $end), 2); ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ── UACS / ACCOUNT BREAKDOWN CARDS ── -->
            <div style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
                <h6 style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.6px;margin:0;">
                    <i class="fas fa-tags me-2" style="color:var(--primary-color);"></i>Expenditure by Account (UACS)
                </h6>
            </div>
            <div class="uacs-grid">
                <?php foreach ($uacs_labels as $key => $ul): ?>
                    <div class="uacs-card">
                        <div class="uc-icon stat-icon <?php echo $ul['color']; ?>"><i class="fas <?php echo $ul['icon']; ?>"></i></div>
                        <div class="uc-content">
                            <div class="uc-label"><?php echo htmlspecialchars($ul['label']); ?></div>
                            <div class="uc-code"><?php echo htmlspecialchars($ul['code']); ?></div>
                            <div class="uc-val">₱<?php echo number_format($uacs_totals[$key], 2); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ── MAIN CDR TABLE CARD ── -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h5 class="mb-0"><i class="fas fa-table-list me-2" style="color:var(--primary-color);"></i>Cash Disbursement Register</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-success" onclick="openAddModal()" aria-label="Add new finance record">
                            <i class="fas fa-plus"></i>Add Entry
                        </button>
                        <button class="btn-export" onclick="exportToCSV()"><i class="fas fa-download"></i> Export CSV</button>
                        <button class="btn btn-outline-secondary" onclick="refreshTable()"><i class="fas fa-rotate-right"></i> Refresh</button>
                    </div>
                </div>

                <div class="card-body pb-0">
                    <form method="GET" action="" class="filter-bar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search supplier or purpose…" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <select name="month" class="filter-select">
                            <option value="">All Months</option>
                            <?php foreach ($distinct_months as $dm): ?>
                                <option value="<?php echo htmlspecialchars($dm); ?>" <?php echo $month_filter === $dm ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dm); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                        <?php if ($search || $month_filter): ?>
                            <a href="finance.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card-body p-0">
                    <?php
                    // Group finance_records (paginated) by month for display
                    $pg_groups = [];
                    foreach ($finance_records as $r) {
                        $ml = strtoupper(trim($r['month_label'] ?? 'UNCATEGORISED'));
                        if (!isset($pg_groups[$ml])) $pg_groups[$ml] = ['records' => [], 'beginning_balance' => floatval($r['beginning_balance'] ?? 0)];
                        $pg_groups[$ml]['records'][] = $r;
                    }
                    ?>
                    <div class="cdr-wrapper">
                        <!-- ── DOC HEADER ── -->
                        <div class="cdr-doc-header">
                            <div class="left-info">
                                <div><strong>Entity Name:</strong> BUYOAN NATIONAL HIGH SCHOOL (JUNIOR HIGH SCHOOL)</div>
                                <div><strong>Sub-Office/District/Division:</strong> DepEd Division of Legazpi City</div>
                                <div><strong>Municipality/City/Province:</strong> Legazpi City</div>
                                <div><strong>Fund Cluster:</strong> 101101</div>
                            </div>
                            <div class="right-info">
                                <div><strong>Name of Accountable Officer:</strong> <?php echo htmlspecialchars($principal_name); ?></div>
                                <div><strong>Official Designation:</strong> <?php echo htmlspecialchars($principal_title); ?></div>
                                <div><strong>Station:</strong> BUYOAN NATIONAL HIGH SCHOOL</div>
                                <div><strong>Register No.:</strong> &nbsp;</div>
                                <div><strong>Sheet No.:</strong> &nbsp;</div>
                            </div>
                        </div>

                        <table class="cdr-table" role="table" aria-label="Cash Disbursement Register">
                            <colgroup>
                                <col style="width:76px"> <!-- A  Date -->
                                <col style="width:100px"> <!-- B  DV/Payroll -->
                                <col style="width:390px"> <!-- C  Particulars -->
                                <col style="width:108px"> <!-- D  Cash Advance -->
                                <col style="width:108px"> <!-- E  Payments -->
                                <col style="width:101px"> <!-- F  Tax Withheld -->
                                <col style="width:93px"> <!-- G  Balance -->
                                <col style="width:41px"> <!-- H  narrow -->
                                <col style="width:35px"> <!-- I  narrow -->
                                <col style="width:97px"> <!-- J  Electricity (5020402000) -->
                                <col style="width:72px"> <!-- K  Training (5020201000) -->
                                <col style="width:145px"> <!-- L  Semi-Exp ICT (50203210) -->
                                <col style="width:97px"> <!-- M  Other Gen Svc (5021299000) -->
                                <col style="width:104px"> <!-- N  Other Supplies (5020399000) -->
                                <col style="width:96px"> <!-- O  Water (5020401000) -->
                                <col style="width:98px"> <!-- P  Semi-Exp Other (5020321099) -->
                                <col style="width:95px"> <!-- Q  Internet (5020503000) -->
                                <col style="width:99px"> <!-- R  Office Supplies (5020301002) -->
                                <col style="width:87px"> <!-- S  Other Gen Svc 2 (5021299000) -->
                                <col style="width:44px"> <!-- T  Account Desc -->
                                <col style="width:42px"> <!-- U  UACS Code -->
                                <col style="width:42px"> <!-- V  Amount -->
                                <col style="width:90px"> <!-- Actions -->
                            </colgroup>
                            <thead>
                                <tr class="cdr-r1">
                                    <th colspan="23">CASH DISBURSEMENT REGISTER</th>
                                </tr>
                                <tr class="cdr-r2">
                                    <th colspan="4">Advances for<br>Operating Expenses</th>
                                    <th colspan="2" rowspan="2" style="font-size:9px;"></th>
                                    <th colspan="13" rowspan="2">BREAKDOWN OF WITHDRAWALS/PAYMENTS</th>
                                    <th rowspan="5">Actions</th>
                                </tr>
                                <tr class="cdr-r3">
                                    <th colspan="4">(19901010)</th>
                                </tr>
                                <tr class="cdr-r3b">
                                    <th colspan="4">Amount</th>
                                    <th colspan="2"></th>
                                    <th colspan="13">MAINTENANCE AND OTHER OPERATING EXPENSES</th>
                                </tr>
                                <tr class="cdr-r4">
                                    <th rowspan="2">date</th>
                                    <th rowspan="2">DV/Payroll<br>Check No.</th>
                                    <th rowspan="2">Particulars/Supplier</th>
                                    <th rowspan="2">Cash<br>Advance</th>
                                    <th rowspan="2">Payments</th>
                                    <th rowspan="2">Tax<br>Withheld</th>
                                    <th rowspan="2">Balance</th>
                                    <th>Electricity<br>Expenses</th>
                                    <th>Training<br>Expenses</th>
                                    <th>Semi-Expendable Information and Communications Technology Equipment</th>
                                    <th>Other General<br>Services</th>
                                    <th>Other Supplies &amp; Materials Expenses</th>
                                    <th>Water<br>Expenses</th>
                                    <th>Semi-Expendable-Other Equipment</th>
                                    <th>Internet Subscription Expenses</th>
                                    <th>Office Supplies<br>Expenses</th>
                                    <th>Other General<br>Services</th>
                                    <th rowspan="2">Account<br>Description</th>
                                    <th rowspan="2">UACS<br>Code</th>
                                    <th rowspan="2">Amount</th>
                                </tr>
                                <tr class="cdr-r5">
                                    <th></th>
                                    <th></th>
                                    <th>(5020402000)</th>
                                    <th>(5020201000)</th>
                                    <th>(50203210)</th>
                                    <th>(5021299000)</th>
                                    <th>(5020399000)</th>
                                    <th>(5020401000)</th>
                                    <th>(5020321099)</th>
                                    <th>(5020503000)</th>
                                    <th>(5020301002)</th>
                                    <th>(5021299000)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($finance_records)): ?>
                                    <tr>
                                        <td colspan="23" style="padding:20px;text-align:center;color:#999;font-size:12px;">No records found. Add the first entry.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    // per-group running balance computation
                                    foreach ($pg_groups as $mLabel => $pgGrp):
                                        $grp_recs = $pgGrp['records'];

                                        // Compute beg balance: use first record's beginning_balance
                                        // but also pull from month_groups for accuracy
                                        $mg_data = $month_groups[$mLabel] ?? null;
                                        $beg_bal_grp = $mg_data ? $mg_data['beginning_balance'] : floatval($grp_recs[0]['beginning_balance'] ?? 0);

                                        // compute totals for this group (may be partial page)
                                        $grp_ca = 0;
                                        $grp_pay = 0;
                                        $grp_tax = 0;
                                        $grp_col_totals = array_fill_keys(['electricity', 'semi_expendable', 'other_general', 'training', 'water', 'other_supplies', 'internet', 'due_to_bir', 'amount_other'], 0);
                                        foreach ($grp_recs as $rr) {
                                            $cc = json_decode($rr['description'] ?? '{}', true) ?: [];
                                            $grp_ca  += floatval($cc['cash_advance'] ?? 0);
                                            $grp_pay += floatval($cc['payments'] ?? $rr['amount']);
                                            $grp_tax += floatval($cc['tax_withheld'] ?? 0);
                                            $mooe_fs = ['electricity', 'semi_expendable', 'other_general', 'training', 'water', 'other_supplies', 'internet', 'due_to_bir', 'amount_other'];
                                            $any = false;
                                            foreach ($mooe_fs as $mf) {
                                                $v = floatval($cc[$mf] ?? 0);
                                                if ($v > 0) {
                                                    $grp_col_totals[$mf] += $v;
                                                    $any = true;
                                                }
                                            }
                                            if (!$any) {
                                                $col = (!empty($cc['mooe_col']) ? $cc['mooe_col'] : null) ?? ($col_map[$rr['category']] ?? 'other_general');
                                                $grp_col_totals[$col] += floatval($rr['amount']);
                                            }
                                        }
                                    ?>
                                        <!-- Month divider row -->
                                        <tr>
                                            <td colspan="23" style="padding:0;">
                                                <div class="month-divider">
                                                    <div class="md-left">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        <?php echo htmlspecialchars($mLabel); ?>
                                                    </div>
                                                    <div class="md-right">
                                                        <span class="md-pill">Beg. Bal: ₱<?php echo number_format($beg_bal_grp, 2); ?></span>
                                                        <span class="md-pill">+CA: ₱<?php echo number_format($grp_ca, 2); ?></span>
                                                        <span class="md-pill">Ending: ₱<?php echo number_format(max(0, $beg_bal_grp + $grp_ca - $grp_pay - $grp_tax), 2); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Balance forwarded row -->
                                        <tr class="cdr-bal-row">
                                            <td></td>
                                            <td></td>
                                            <td class="cdr-part" style="font-size:10px;text-align:left;">BALANCE FORWARDED</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td class="cdr-num">₱<?php echo number_format($beg_bal_grp, 2); ?></td>
                                            <td colspan="50"></td>
                                        </tr>
                                        <!-- Cash advance for month row -->
                                        <tr class="cdr-adv-row">
                                            <td></td>
                                            <td></td>
                                            <td class="cdr-part" style="font-size:10px;text-align:left;">CASH ADVANCE FOR THE MONTH OF <?php echo htmlspecialchars($mLabel); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_ca, 2); ?></td>
                                            <td></td>
                                            <td></td>
                                            <td class="cdr-num">₱<?php echo number_format($beg_bal_grp + $grp_ca, 2); ?></td>
                                            <td colspan="16"></td>
                                        </tr>

                                        <?php
                                        // Running balance starts at beg_bal + cash_advance_for_month
                                        $running = $beg_bal_grp + $grp_ca;
                                        foreach ($grp_recs as $record):
                                            $cdr = json_decode($record['description'] ?? '{}', true) ?: [];
                                            $dv_no        = !empty($cdr['dv_check_no']) ? $cdr['dv_check_no'] : 'DV-' . str_pad($record['id'], 4, '0', STR_PAD_LEFT);
                                            $rec_advance  = floatval($cdr['cash_advance'] ?? 0);
                                            $rec_payments = floatval($cdr['payments'] ?? $record['amount']);
                                            $rec_tax      = floatval($cdr['tax_withheld'] ?? 0);
                                            $note         = $cdr['note'] ?? '';
                                            $row_acct     = $cdr['account_description'] ?? '';
                                            $row_uacs     = $cdr['uacs_code'] ?? '';

                                            // MOOE display values
                                            $r_elec  = floatval($cdr['electricity'] ?? 0);
                                            $r_semi  = floatval($cdr['semi_expendable'] ?? 0);
                                            $r_ogen  = floatval($cdr['other_general'] ?? 0);
                                            $r_train = floatval($cdr['training'] ?? 0);
                                            $r_water = floatval($cdr['water'] ?? 0);
                                            $r_osup  = floatval($cdr['other_supplies'] ?? 0);
                                            $r_inet  = floatval($cdr['internet'] ?? 0);
                                            $r_bir   = floatval($cdr['due_to_bir'] ?? 0);
                                            $r_other = floatval($cdr['amount_other'] ?? 0);

                                            $any_expl = ($r_elec + $r_semi + $r_ogen + $r_train + $r_water + $r_osup + $r_inet + $r_bir + $r_other) > 0;
                                            if (!$any_expl) {
                                                $col = (!empty($cdr['mooe_col']) ? $cdr['mooe_col'] : null) ?? ($col_map[$record['category']] ?? 'other_general');
                                                $amt_r = floatval($record['amount']);
                                                if ($col === 'electricity')    $r_elec = $amt_r;
                                                elseif ($col === 'semi_expendable') $r_semi = $amt_r;
                                                elseif ($col === 'other_general')   $r_ogen = $amt_r;
                                                elseif ($col === 'training')        $r_train = $amt_r;
                                                elseif ($col === 'water')           $r_water = $amt_r;
                                                elseif ($col === 'other_supplies')  $r_osup = $amt_r;
                                                elseif ($col === 'internet')        $r_inet = $amt_r;
                                                elseif ($col === 'due_to_bir')      $r_bir = $amt_r;
                                            }

                                            // Update running balance: per xlsx logic
                                            // running = previous running + cash_advance_row - payments_row - tax_row
                                            $running = $running + $rec_advance - $rec_payments - $rec_tax;
                                        ?>
                                            <tr data-id="<?php echo $record['id']; ?>">
                                                <!-- A: Date -->
                                                <td style="white-space:nowrap;font-size:10.5px;"><?php echo date('m/d/Y', strtotime($record['transaction_date'])); ?></td>
                                                <!-- B: DV/Payroll Check No. -->
                                                <td style="font-size:10px;word-break:break-all;"><?php echo htmlspecialchars($dv_no); ?></td>
                                                <!-- C: Particulars/Supplier -->
                                                <td class="cdr-part">
                                                    <strong style="font-size:11px;"><?php echo htmlspecialchars($record['fund_title']); ?></strong>
                                                    <?php if ($note): ?>
                                                        <div style="font-size:9.5px;color:#555;"><?php echo htmlspecialchars(mb_substr($note, 0, 70)); ?><?php echo mb_strlen($note) > 70 ? '…' : ''; ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <!-- D: Cash Advance -->
                                                <td class="cdr-num"><?php echo $rec_advance > 0 ? '₱' . number_format($rec_advance, 2) : ''; ?></td>
                                                <!-- E: Payments -->
                                                <td class="cdr-num">
                                                    <?php if ($record['proof_image']): ?>
                                                        <span style="cursor:pointer;color:#1a4f12;" onclick="viewProof('<?php echo htmlspecialchars($record['proof_image']); ?>')" title="View proof">
                                                            ₱<?php echo number_format($rec_payments, 2); ?><i class="fas fa-eye" style="font-size:8px;margin-left:2px;"></i>
                                                        </span>
                                                    <?php else: ?>
                                                        ₱<?php echo number_format($rec_payments, 2); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <!-- F: Tax Withheld -->
                                                <td class="cdr-num"><?php echo $rec_tax > 0 ? '₱' . number_format($rec_tax, 2) : ''; ?></td>
                                                <!-- G: Running Balance -->
                                                <td class="cdr-num">₱<?php echo number_format(max(0, $running), 2); ?></td>
                                                <!-- H, I: narrow -->
                                                <td></td>
                                                <td></td>
                                                <!-- J: Electricity (5020402000) -->
                                                <td class="cdr-num"><?php echo $r_elec > 0 ? '₱' . number_format($r_elec, 2) : ''; ?></td>
                                                <!-- K: Training (5020201000) -->
                                                <td class="cdr-num"><?php echo $r_train > 0 ? '₱' . number_format($r_train, 2) : ''; ?></td>
                                                <!-- L: Semi-Exp ICT (50203210) -->
                                                <td class="cdr-num"><?php echo $r_semi > 0 ? '₱' . number_format($r_semi, 2) : ''; ?></td>
                                                <!-- M: Other Gen Svc (5021299000) -->
                                                <td class="cdr-num"><?php echo $r_ogen > 0 ? '₱' . number_format($r_ogen, 2) : ''; ?></td>
                                                <!-- N: Other Supplies (5020399000) -->
                                                <td class="cdr-num"><?php echo $r_osup > 0 ? '₱' . number_format($r_osup, 2) : ''; ?></td>
                                                <!-- O: Water (5020401000) -->
                                                <td class="cdr-num"><?php echo $r_water > 0 ? '₱' . number_format($r_water, 2) : ''; ?></td>
                                                <!-- P: Semi-Exp Other (5020321099) -->
                                                <td class="cdr-num"></td>
                                                <!-- Q: Internet (5020503000) -->
                                                <td class="cdr-num"><?php echo $r_inet > 0 ? '₱' . number_format($r_inet, 2) : ''; ?></td>
                                                <!-- R: Office Supplies (5020301002) -->
                                                <td class="cdr-num"><?php echo $r_bir > 0 ? '₱' . number_format($r_bir, 2) : ''; ?></td>
                                                <!-- S: Other Gen Svc 2 (5021299000) -->
                                                <td class="cdr-num"></td>
                                                <!-- T: Account Description -->
                                                <td style="font-size:9.5px;word-break:break-word;text-align:left;padding-left:4px;"><?php echo htmlspecialchars($row_acct); ?></td>
                                                <!-- U: UACS Code -->
                                                <td style="font-size:9.5px;white-space:nowrap;font-family:'DM Mono',monospace;"><?php echo htmlspecialchars($row_uacs); ?></td>
                                                <!-- V: Amount (extra/other) -->
                                                <td class="cdr-num"><?php echo $r_other > 0 ? '₱' . number_format($r_other, 2) : ''; ?></td>
                                                <!-- Actions -->
                                                <td>
                                                    <div class="action-buttons" style="justify-content:center;flex-wrap:nowrap;">
                                                        <button class="btn btn-sm btn-info edit-btn"
                                                            data-id="<?php echo $record['id']; ?>"
                                                            data-fund_title="<?php echo htmlspecialchars($record['fund_title']); ?>"
                                                            data-note="<?php echo htmlspecialchars($note); ?>"
                                                            data-dv_check_no="<?php echo htmlspecialchars($dv_no); ?>"
                                                            data-cash_advance="<?php echo $rec_advance; ?>"
                                                            data-payments="<?php echo $rec_payments; ?>"
                                                            data-tax_withheld="<?php echo $rec_tax; ?>"
                                                            data-balance="<?php echo max(0, $running); ?>"
                                                            data-beginning_balance="<?php echo $beg_bal_grp; ?>"
                                                            data-month_label="<?php echo htmlspecialchars($mLabel); ?>"
                                                            data-transaction_date="<?php echo $record['transaction_date']; ?>"
                                                            data-category="<?php echo htmlspecialchars($record['category']); ?>"
                                                            data-mooe_col="<?php echo htmlspecialchars($cdr['mooe_col'] ?? ''); ?>"
                                                            data-electricity="<?php echo $r_elec; ?>"
                                                            data-semi_expendable="<?php echo $r_semi; ?>"
                                                            data-other_general="<?php echo $r_ogen; ?>"
                                                            data-training="<?php echo $r_train; ?>"
                                                            data-water="<?php echo $r_water; ?>"
                                                            data-other_supplies="<?php echo $r_osup; ?>"
                                                            data-internet="<?php echo $r_inet; ?>"
                                                            data-due_to_bir="<?php echo $r_bir; ?>"
                                                            data-amount_other="<?php echo $r_other; ?>"
                                                            data-account_description="<?php echo htmlspecialchars($row_acct); ?>"
                                                            data-uacs_code="<?php echo htmlspecialchars($row_uacs); ?>"
                                                            title="Edit"><i class="fas fa-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $record['id']; ?>" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <!-- Per-month totals footer row -->
                                        <tr style="background:#e8f5e9;font-weight:700;font-size:10.5px;">
                                            <td colspan="3" class="cdr-part" style="font-weight:700;font-size:10.5px;">TOTALS — <?php echo htmlspecialchars($mLabel); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_ca, 2); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_pay, 2); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_tax, 2); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format(max(0, $beg_bal_grp + $grp_ca - $grp_pay - $grp_tax), 2); ?></td>
                                            <td></td>
                                            <td></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_col_totals['electricity'], 2); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_col_totals['training'], 2); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_col_totals['semi_expendable'], 2); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_col_totals['other_general'], 2); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_col_totals['other_supplies'], 2); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_col_totals['water'], 2); ?></td>
                                            <td></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_col_totals['internet'], 2); ?></td>
                                            <td class="cdr-num">₱<?php echo number_format($grp_col_totals['due_to_bir'], 2); ?></td>
                                            <td></td>
                                            <td colspan="3"></td>
                                            <td></td>
                                        </tr>
                                    <?php endforeach; // end month groups 
                                    ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Signature footer -->
                        <div class="cdr-footer">
                            <div class="sig-block">
                                <div class="sig-label">Prepared by:</div>
                                <div class="sig-name"><?php echo htmlspecialchars($bookkeeper_name); ?></div>
                                <div class="sig-title">Signature Over Printed Name</div>
                                <div class="sig-title"><?php echo htmlspecialchars($bookkeeper_title); ?></div>
                            </div>
                            <div class="sig-block">
                                <div class="sig-label">Certified Correct:</div>
                                <div class="sig-name"><?php echo htmlspecialchars($principal_name); ?></div>
                                <div class="sig-title">Signature Over Printed Name</div>
                                <div class="sig-title"><?php echo htmlspecialchars($principal_title); ?></div>
                            </div>
                        </div>
                    </div><!-- .cdr-wrapper -->

                    <!-- ── PAGINATION ── -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination-info">
                                Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
                            </div>
                            <nav>
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"><i class="fas fa-angle-double-left"></i></a></li>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"><i class="fas fa-angle-left"></i></a></li>
                                    <?php else: ?>
                                        <li class="disabled"><span><i class="fas fa-angle-double-left"></i></span></li>
                                        <li class="disabled"><span><i class="fas fa-angle-left"></i></span></li>
                                    <?php endif; ?>
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="<?php echo $i == $page ? 'active' : ''; ?>">
                                            <?php if ($i == $page): ?><span><?php echo $i; ?></span>
                                            <?php else: ?><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a><?php endif; ?>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"><i class="fas fa-angle-right"></i></a></li>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><i class="fas fa-angle-double-right"></i></a></li>
                                    <?php else: ?>
                                        <li class="disabled"><span><i class="fas fa-angle-right"></i></span></li>
                                        <li class="disabled"><span><i class="fas fa-angle-double-right"></i></span></li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                </div><!-- .card-body p-0 -->
            </div><!-- .card -->
        </section><!-- .finance-section -->

        <!-- ══════════════════════════════════════════════
         ADD RECORD POPUP
    ══════════════════════════════════════════════ -->
        <div class="popup-overlay" id="addRecordPopup" role="dialog" aria-modal="true">
            <div class="popup-card wide">
                <div class="card-header">
                    <div class="popup-header-top">
                        <div style="position:relative;z-index:5;">
                            <div class="popup-header-badge"><i class="fas fa-plus"></i> New Entry</div>
                            <h5>Add CDR Transaction</h5>
                            <div class="popup-header-sub">Enter all fields to match the spreadsheet register</div>
                        </div>
                        <button type="button" class="popup-close-btn" onclick="closeAddPopup()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="financeForm" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="row g-3">

                            <!-- ── Section 1: Document Reference ── -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-file-alt"></i>Document Reference</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Month *</label>
                                <select class="form-select" name="month_label" required>
                                    <option value="">Select Month</option>
                                    <?php foreach (array_keys($MONTH_ORDER) as $mo): ?>
                                        <option value="<?php echo $mo; ?>"><?php echo $mo; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" name="transaction_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">DV / Payroll Check No.</label>
                                <input type="text" class="form-control" name="dv_check_no" placeholder="e.g. 0002993401" maxlength="50">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Particulars / Supplier *</label>
                                <input type="text" class="form-control" name="fund_title" placeholder="e.g. ALBAY ELECTRIC COOPERATIVE, INC." maxlength="255" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purpose / Notes</label>
                                <input type="text" class="form-control" name="description" placeholder="Short description…" maxlength="255">
                            </div>

                            <!-- ── Section 2: Advances for Operating Expenses ── -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-money-bill-wave"></i>Advances for Operating Expenses (19901010)</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Cash Advance (₱)</label>
                                <input type="number" class="form-control" name="cash_advance" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Beginning Balance (₱)</label>
                                <input type="number" class="form-control" name="beginning_balance" placeholder="0.00" step="0.01" min="0" title="Balance forwarded from previous month or period">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Payments (₱) *</label>
                                <input type="number" class="form-control" name="payments" placeholder="0.00" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tax Withheld (₱)</label>
                                <input type="number" class="form-control" name="tax_withheld" placeholder="0.00" step="0.01" min="0">
                            </div>

                            <!-- ── Section 3: MOOE Breakdown ── -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-table-columns"></i>MOOE Breakdown — Maintenance and Other Operating Expenses</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category (auto-maps to MOOE column)</label>
                                <select class="form-select" name="category">
                                    <option value="">Select Category</option>
                                    <option value="Utilities">Utilities → Electricity Expenses (5020402000)</option>
                                    <option value="Equipment">Equipment → Semi-Expendable ICT (50203210)</option>
                                    <option value="Events">Events → Training Expenses (5020201000)</option>
                                    <option value="Sports">Sports → Training Expenses (5020201000)</option>
                                    <option value="Transportation">Transportation → Training Expenses (5020201000)</option>
                                    <option value="Salaries">Salaries → Other General Services (5021299000)</option>
                                    <option value="Other">Other → Other General Services (5021299000)</option>
                                    <option value="Maintenance">Maintenance → Water Expenses (5020401000)</option>
                                    <option value="Supplies">Supplies → Other Supplies &amp; Materials (5020399000)</option>
                                    <option value="Books">Books → Other Supplies &amp; Materials (5020399000)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Override MOOE Column (optional)</label>
                                <select class="form-select" name="mooe_col">
                                    <option value="">— Use category mapping —</option>
                                    <option value="electricity">Electricity Expenses (5020402000)</option>
                                    <option value="training">Training Expenses (5020201000)</option>
                                    <option value="semi_expendable">Semi-Expendable ICT Equipment (50203210)</option>
                                    <option value="other_general">Other General Services (5021299000)</option>
                                    <option value="other_supplies">Other Supplies &amp; Materials (5020399000)</option>
                                    <option value="water">Water Expenses (5020401000)</option>
                                    <option value="internet">Internet Subscription (5020503000)</option>
                                    <option value="due_to_bir">Office Supplies Expenses (5020301002)</option>
                                </select>
                            </div>

                            <!-- Explicit MOOE amounts (optional override per-column) -->
                            <div class="col-md-3"><label class="form-label">Electricity (₱)</label><input type="number" class="form-control" name="electricity" placeholder="0.00" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Training Exp. (₱)</label><input type="number" class="form-control" name="training" placeholder="0.00" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Semi-Exp ICT (₱)</label><input type="number" class="form-control" name="semi_expendable" placeholder="0.00" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Other Gen Svc (₱)</label><input type="number" class="form-control" name="other_general" placeholder="0.00" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Other Supplies (₱)</label><input type="number" class="form-control" name="other_supplies" placeholder="0.00" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Water Exp. (₱)</label><input type="number" class="form-control" name="water" placeholder="0.00" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Internet Subs. (₱)</label><input type="number" class="form-control" name="internet" placeholder="0.00" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Office Supplies (₱)</label><input type="number" class="form-control" name="due_to_bir" placeholder="0.00" step="0.01" min="0"></div>

                            <!-- ── Section 4: UACS / Account Description ── -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-code"></i>Account Description &amp; UACS Code</div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Account Description</label>
                                <input type="text" class="form-control" name="account_description" placeholder="e.g. Electricity Expenses" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">UACS Code</label>
                                <select class="form-select" name="uacs_code">
                                    <option value="">Select UACS Code</option>
                                    <option value="5020402000">5020402000 — Electricity Expenses</option>
                                    <option value="5020201000">5020201000 — Training Expenses</option>
                                    <option value="50203210">50203210 — Semi-Expendable ICT</option>
                                    <option value="5021299000">5021299000 — Other General Services</option>
                                    <option value="5020399000">5020399000 — Other Supplies &amp; Materials</option>
                                    <option value="5020401000">5020401000 — Water Expenses</option>
                                    <option value="5020321099">5020321099 — Semi-Expendable Other Equip.</option>
                                    <option value="5020503000">5020503000 — Internet Subscription</option>
                                    <option value="5020301002">5020301002 — Office Supplies Expenses</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Amount (₱) — UACS col V</label>
                                <input type="number" class="form-control" name="amount_other" placeholder="0.00" step="0.01" min="0">
                            </div>

                            <!-- ── Section 5: Proof ── -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-paperclip"></i>Proof of Payment</div>
                            </div>
                            <div class="col-12">
                                <div class="upload-zone">
                                    <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:#c4c2ce;display:block;margin-bottom:8px;"></i>
                                    <div style="font-size:.84rem;font-weight:700;color:#3d3b52;">Click or drag to upload proof image</div>
                                    <div style="font-size:.74rem;color:#a8a6bc;margin-top:4px;">JPG, PNG, GIF, WebP (Max 5MB) — Optional</div>
                                    <input type="file" name="proof_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                </div>
                                <div class="image-preview" id="addImgPreview"><img id="addPreviewImg" src="" alt="Preview"></div>
                            </div>

                        </div><!-- .row -->
                    </form>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeAddPopup()">Cancel</button>
                    <button type="submit" form="financeForm" class="btn btn-success" id="submitBtn">
                        <i class="fas fa-save me-1"></i>Save Entry
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
         EDIT RECORD POPUP
    ══════════════════════════════════════════════ -->
        <div class="popup-overlay" id="editRecordPopup" role="dialog" aria-modal="true">
            <div class="popup-card wide">
                <div class="card-header">
                    <div class="popup-header-top">
                        <div style="position:relative;z-index:5;">
                            <div class="popup-header-badge"><i class="fas fa-edit"></i> Edit Entry</div>
                            <h5>Edit CDR Transaction</h5>
                            <div class="popup-header-sub">Update the transaction details below</div>
                        </div>
                        <button type="button" class="popup-close-btn" onclick="closeEditPopup()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="editFinanceForm" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-3">

                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-file-alt"></i>Document Reference</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Month *</label>
                                <select class="form-select" name="month_label" id="edit_month_label" required>
                                    <?php foreach (array_keys($MONTH_ORDER) as $mo): ?>
                                        <option value="<?php echo $mo; ?>"><?php echo $mo; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="transaction_date" id="edit_transaction_date">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">DV / Payroll Check No.</label>
                                <input type="text" class="form-control" name="dv_check_no" id="edit_dv_check_no" maxlength="50">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Particulars / Supplier</label>
                                <input type="text" class="form-control" name="fund_title" id="edit_fund_title" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purpose / Notes</label>
                                <input type="text" class="form-control" name="description" id="edit_description" maxlength="255">
                            </div>

                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-money-bill-wave"></i>Advances for Operating Expenses (19901010)</div>
                            </div>
                            <div class="col-md-3"><label class="form-label">Cash Advance (₱)</label><input type="number" class="form-control" name="cash_advance" id="edit_cash_advance" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Beginning Balance (₱)</label><input type="number" class="form-control" name="beginning_balance" id="edit_beginning_balance" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Payments (₱)</label><input type="number" class="form-control" name="payments" id="edit_payments" step="0.01" min="0"></div>
                            <div class="col-md-3"><label class="form-label">Tax Withheld (₱)</label><input type="number" class="form-control" name="tax_withheld" id="edit_tax_withheld" step="0.01" min="0"></div>

                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-table-columns"></i>MOOE Breakdown</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" id="edit_category">
                                    <option value="">Select Category</option>
                                    <option value="Utilities">Utilities → Electricity Expenses</option>
                                    <option value="Equipment">Equipment → Semi-Expendable ICT</option>
                                    <option value="Events">Events → Training Expenses</option>
                                    <option value="Sports">Sports → Training Expenses</option>
                                    <option value="Transportation">Transportation → Training Expenses</option>
                                    <option value="Salaries">Salaries → Other General Services</option>
                                    <option value="Other">Other → Other General Services</option>
                                    <option value="Maintenance">Maintenance → Water Expenses</option>
                                    <option value="Supplies">Supplies → Other Supplies &amp; Materials</option>
                                    <option value="Books">Books → Other Supplies &amp; Materials</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Override MOOE Column</label>
                                <select class="form-select" name="mooe_col" id="edit_mooe_col">
                                    <option value="">— Use category mapping —</option>
                                    <option value="electricity">Electricity Expenses</option>
                                    <option value="training">Training Expenses</option>
                                    <option value="semi_expendable">Semi-Expendable ICT</option>
                                    <option value="other_general">Other General Services</option>
                                    <option value="other_supplies">Other Supplies &amp; Materials</option>
                                    <option value="water">Water Expenses</option>
                                    <option value="internet">Internet Subscription</option>
                                    <option value="due_to_bir">Office Supplies Expenses</option>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Electricity (₱)</label><input type="number" class="form-control" name="electricity" id="edit_electricity" step="0.01" min="0" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Training Exp. (₱)</label><input type="number" class="form-control" name="training" id="edit_training" step="0.01" min="0" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Semi-Exp ICT (₱)</label><input type="number" class="form-control" name="semi_expendable" id="edit_semi_expendable" step="0.01" min="0" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Other Gen Svc (₱)</label><input type="number" class="form-control" name="other_general" id="edit_other_general" step="0.01" min="0" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Other Supplies (₱)</label><input type="number" class="form-control" name="other_supplies" id="edit_other_supplies" step="0.01" min="0" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Water Exp. (₱)</label><input type="number" class="form-control" name="water" id="edit_water" step="0.01" min="0" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Internet Subs. (₱)</label><input type="number" class="form-control" name="internet" id="edit_internet" step="0.01" min="0" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Office Supplies (₱)</label><input type="number" class="form-control" name="due_to_bir" id="edit_due_to_bir" step="0.01" min="0" value="0"></div>

                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-code"></i>Account Description &amp; UACS Code</div>
                            </div>
                            <div class="col-md-5"><label class="form-label">Account Description</label><input type="text" class="form-control" name="account_description" id="edit_account_description" maxlength="255"></div>
                            <div class="col-md-4">
                                <label class="form-label">UACS Code</label>
                                <select class="form-select" name="uacs_code" id="edit_uacs_code">
                                    <option value="">Select UACS Code</option>
                                    <option value="5020402000">5020402000 — Electricity Expenses</option>
                                    <option value="5020201000">5020201000 — Training Expenses</option>
                                    <option value="50203210">50203210 — Semi-Expendable ICT</option>
                                    <option value="5021299000">5021299000 — Other General Services</option>
                                    <option value="5020399000">5020399000 — Other Supplies &amp; Materials</option>
                                    <option value="5020401000">5020401000 — Water Expenses</option>
                                    <option value="5020321099">5020321099 — Semi-Expendable Other Equip.</option>
                                    <option value="5020503000">5020503000 — Internet Subscription</option>
                                    <option value="5020301002">5020301002 — Office Supplies Expenses</option>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Amount (₱) — UACS col V</label><input type="number" class="form-control" name="amount_other" id="edit_amount_other" step="0.01" min="0" value="0"></div>

                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-paperclip"></i>Proof of Payment (optional)</div>
                            </div>
                            <div class="col-12">
                                <div class="upload-zone">
                                    <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:#c4c2ce;display:block;margin-bottom:8px;"></i>
                                    <div style="font-size:.84rem;font-weight:700;color:#3d3b52;">Click or drag to replace proof image</div>
                                    <div style="font-size:.74rem;color:#a8a6bc;margin-top:4px;">JPG, PNG, GIF, WebP (Max 5MB)</div>
                                    <input type="file" name="proof_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeEditPopup()">Cancel</button>
                    <button type="submit" form="editFinanceForm" class="btn btn-info" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Entry
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
         DELETE POPUP
    ══════════════════════════════════════════════ -->
        <div class="popup-overlay" id="deleteRecordPopup" role="dialog" aria-modal="true">
            <div class="popup-card delete-popup" style="max-width:420px;">
                <div class="card-header">
                    <div class="popup-header-top">
                        <div style="position:relative;z-index:5;">
                            <div class="popup-header-badge"><i class="fas fa-trash"></i> Delete</div>
                            <h5>Delete Transaction</h5>
                        </div>
                        <button type="button" class="popup-close-btn" onclick="closeDeletePopup()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="card-body" style="text-align:center;padding:32px 28px;">
                    <div class="delete-icon-circle"><i class="fas fa-triangle-exclamation"></i></div>
                    <div style="font-size:17px;font-weight:700;margin-bottom:10px;">Are you sure?</div>
                    <div style="color:var(--text-secondary);font-size:13.5px;line-height:1.6;">This transaction will be permanently removed from the register and cannot be undone.</div>
                </div>
                <div class="card-footer" style="justify-content:center;">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeDeletePopup()">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="fas fa-trash me-1"></i>Delete</button>
                </div>
            </div>
        </div>

        <!-- Proof image modal -->
        <div class="modal fade" id="proofModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="border:none;border-radius:20px;overflow:hidden;">
                    <div class="modal-header" style="background:linear-gradient(135deg,#f0f5eb,#ebf5f0);border-bottom:1px solid rgba(92,122,62,.12);">
                        <span style="font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px;"><i class="fas fa-image" style="color:var(--primary-color);"></i>Proof of Payment</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0"><img id="proofImage" src="" alt="Proof" style="width:100%;max-height:80vh;object-fit:contain;display:block;"></div>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="toast" class="toast custom-toast" role="alert" aria-live="assertive">
                <div class="toast-header">
                    <strong class="me-auto" id="toastTitle">Notification</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body" id="toastMessage"></div>
            </div>
        </div>
    </main>

    <script src="admin_assets/js/admin_script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const CSRF = '<?php echo $_SESSION['csrf_token']; ?>';
        let deleteRecordId = null;

        // ── Load navigation bar ───────────────────────────────────────────────────
        fetch('./admin_nav.php')
            .then(r => r.text())
            .then(html => {
                document.getElementById('navigation-container').innerHTML = html;
            })
            .catch(err => console.error('Error loading navigation:', err));

        const addPopup = document.getElementById('addRecordPopup');
        const editPopup = document.getElementById('editRecordPopup');
        const deletePopup = document.getElementById('deleteRecordPopup');
        const proofModal = new bootstrap.Modal(document.getElementById('proofModal'));

        // Escape key closes any open popup
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddPopup();
                closeEditPopup();
                closeDeletePopup();
            }
        });

        // ── Add popup ──────────────────────────────────────────────────────────────
        function openAddModal() {
            addPopup.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddPopup() {
            addPopup.classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('financeForm').reset();
            document.getElementById('addImgPreview').classList.remove('show');
        }

        // ── Delete popup ──────────────────────────────────────────────────────────
        function openDeletePopup() {
            deletePopup.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDeletePopup() {
            deletePopup.classList.remove('active');
            document.body.style.overflow = '';
            deleteRecordId = null;
        }

        // ── Edit popup ────────────────────────────────────────────────────────────
        function openEditPopup(btn) {
            const d = btn.dataset;
            document.getElementById('edit_id').value = d.id;
            document.getElementById('edit_month_label').value = d.month_label || 'MAY';
            document.getElementById('edit_transaction_date').value = d.transaction_date || '';
            document.getElementById('edit_dv_check_no').value = d.dv_check_no || '';
            document.getElementById('edit_fund_title').value = d.fund_title || '';
            document.getElementById('edit_description').value = d.note || '';
            document.getElementById('edit_cash_advance').value = d.cash_advance || '0';
            document.getElementById('edit_payments').value = d.payments || '0';
            document.getElementById('edit_tax_withheld').value = d.tax_withheld || '0';
            document.getElementById('edit_beginning_balance').value = d.beginning_balance || '0';
            document.getElementById('edit_category').value = d.category || '';
            document.getElementById('edit_mooe_col').value = d.mooe_col || '';
            document.getElementById('edit_electricity').value = d.electricity || '0';
            document.getElementById('edit_training').value = d.training || '0';
            document.getElementById('edit_semi_expendable').value = d.semi_expendable || '0';
            document.getElementById('edit_other_general').value = d.other_general || '0';
            document.getElementById('edit_other_supplies').value = d.other_supplies || '0';
            document.getElementById('edit_water').value = d.water || '0';
            document.getElementById('edit_internet').value = d.internet || '0';
            document.getElementById('edit_due_to_bir').value = d.due_to_bir || '0';
            document.getElementById('edit_amount_other').value = d.amount_other || '0';
            document.getElementById('edit_account_description').value = d.account_description || '';
            // UACS code — try to match option
            const us = document.getElementById('edit_uacs_code');
            const uv = d.uacs_code || '';
            [...us.options].forEach(o => o.selected = (o.value === uv));
            editPopup.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditPopup() {
            editPopup.classList.remove('active');
            document.body.style.overflow = '';
        }

        // ── Submit handlers ────────────────────────────────────────────────────────
        document.getElementById('financeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';
            try {
                const fd = new FormData(this);
                const r = await fetch('', {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const d = await r.json();
                if (d.status === 'success') {
                    showToast('success', d.message);
                    closeAddPopup();
                    setTimeout(() => location.reload(), 1200);
                } else showToast('error', d.message);
            } catch {
                showToast('error', 'An error occurred. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Entry';
            }
        });

        document.getElementById('editFinanceForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('editSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating…';
            try {
                const fd = new FormData(this);
                const r = await fetch('', {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const d = await r.json();
                if (d.status === 'success') {
                    showToast('success', d.message);
                    closeEditPopup();
                    setTimeout(() => location.reload(), 1200);
                } else showToast('error', d.message);
            } catch {
                showToast('error', 'An error occurred. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Update Entry';
            }
        });

        // ── Delete ─────────────────────────────────────────────────────────────────
        document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
            if (!deleteRecordId) return;
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting…';
            try {
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', deleteRecordId);
                fd.append('csrf_token', CSRF);
                const r = await fetch('', {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const d = await r.json();
                if (d.status === 'success') {
                    showToast('success', d.message);
                    closeDeletePopup();
                    setTimeout(() => location.reload(), 1200);
                } else showToast('error', d.message);
            } catch {
                showToast('error', 'An error occurred.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
            }
        });

        // ── Wire up row buttons ────────────────────────────────────────────────────
        document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => openEditPopup(btn)));
        document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function() {
            deleteRecordId = this.dataset.id;
            openDeletePopup();
        }));

        // Close overlays on backdrop click
        [addPopup, editPopup, deletePopup].forEach(el => el.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddPopup();
                closeEditPopup();
                closeDeletePopup();
            }
        }));

        // ── Proof image viewer ────────────────────────────────────────────────────
        function viewProof(fn) {
            document.getElementById('proofImage').src = 'admin_assets/finance_proofs/' + fn;
            proofModal.show();
        }

        // ── Image preview ────────────────────────────────────────────────────────
        document.querySelector('#financeForm input[type=file]').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const r = new FileReader();
                r.onload = e => {
                    const p = document.getElementById('addImgPreview');
                    document.getElementById('addPreviewImg').src = e.target.result;
                    p.classList.add('show');
                };
                r.readAsDataURL(this.files[0]);
            }
        });

        // ── Utilities ─────────────────────────────────────────────────────────────
        function exportToCSV() {
            window.location.href = '?export=csv';
        }

        function refreshTable() {
            location.reload();
        }

        function showToast(type, msg) {
            const t = document.getElementById('toast');
            t.classList.remove('success', 'error');
            t.classList.add(type);
            document.getElementById('toastTitle').textContent = type === 'success' ? 'Success' : 'Error';
            document.getElementById('toastMessage').textContent = msg;
            bootstrap.Toast.getOrCreateInstance(t).show();
        }

        // ── Live balance preview in Add form ─────────────────────────────────────
        ['cash_advance', 'payments', 'tax_withheld', 'beginning_balance'].forEach(id => {
            const el = document.querySelector(`[name="${id}"]`);
            if (el) el.addEventListener('input', updateLiveBalance);
        });

        function updateLiveBalance() {
            const form = document.getElementById('financeForm');
            const beg = parseFloat(form.querySelector('[name=beginning_balance]').value) || 0;
            const ca = parseFloat(form.querySelector('[name=cash_advance]').value) || 0;
            const pay = parseFloat(form.querySelector('[name=payments]').value) || 0;
            const tax = parseFloat(form.querySelector('[name=tax_withheld]').value) || 0;
            const end = beg + ca - pay - tax;
            let hint = document.getElementById('liveBalanceHint');
            if (!hint) {
                hint = document.createElement('div');
                hint.id = 'liveBalanceHint';
                hint.style.cssText = 'font-size:11.5px;font-weight:600;color:var(--primary-color);margin-top:6px;';
                form.querySelector('[name=tax_withheld]').closest('.col-md-3').appendChild(hint);
            }
            hint.textContent = `Projected Balance: ₱${end.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2})}`;
        }
    </script>
</body>

</html>