<?php
session_start();
include '../db_connection.php';

/*
 * ── ENCRYPTION KEY SETUP ─────────────────────────────────────────────────────
 * Set the environment variable TEACHERS_ENC_KEY on your server to a long
 * random secret string, e.g. in Apache .htaccess:
 *   SetEnv TEACHERS_ENC_KEY "your-very-long-random-secret-here"
 * Or in Nginx via fastcgi_param / php-fpm pool.conf:
 *   env[TEACHERS_ENC_KEY] = "your-very-long-random-secret-here"
 * If the variable is not set, a default fallback key is used (NOT safe for
 * production — please configure this before deploying).
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ══ AJAX: get_schedules endpoint (must be first so it exits early) ══
if (isset($_GET['get_schedules'])) {
    header('Content-Type: application/json');
    $tid   = mysqli_real_escape_string($conn, trim($_GET['tid']  ?? ''));
    $ttype = mysqli_real_escape_string($conn, trim($_GET['ttype'] ?? 'teacher'));
    $res   = $conn->query("SELECT * FROM teacher_schedules WHERE teacher_id='$tid' AND teacher_type='$ttype' ORDER BY sort_order ASC");
    $rows  = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode($rows);
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
//  AUTO-MIGRATE
// ══════════════════════════════════════════════════════════════════════════════
$migs = [
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS last_name         VARCHAR(100)  DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS first_name        VARCHAR(100)  DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS middle_name       VARCHAR(100)  DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS gender            VARCHAR(10)   DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS career_level      VARCHAR(80)   DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS employment_status VARCHAR(60)   DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS major             VARCHAR(150)  DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS subject_assigned  VARCHAR(200)  DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS grade_section     VARCHAR(100)  DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS room_assignment   VARCHAR(80)   DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS day_mon           TINYINT(1)    DEFAULT 0",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS day_tue           TINYINT(1)    DEFAULT 0",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS day_wed           TINYINT(1)    DEFAULT 0",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS day_thu           TINYINT(1)    DEFAULT 0",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS day_fri           TINYINT(1)    DEFAULT 0",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS time_start        VARCHAR(20)   DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS time_end          VARCHAR(20)   DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS club_role         VARCHAR(150)  DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS advisory_class    VARCHAR(100)  DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS nt_position       VARCHAR(100)  DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS nt_appt_type      VARCHAR(80)   DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS nt_fund_source    VARCHAR(80)   DEFAULT NULL",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS category          VARCHAR(20)   DEFAULT 'Teaching'",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS is_principal      TINYINT(1)    DEFAULT 0",
    "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS funding_source    VARCHAR(80)   DEFAULT 'NATIONAL'",

    "CREATE TABLE IF NOT EXISTS `teacher_schedules` (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id   VARCHAR(50)  NOT NULL,
        teacher_type VARCHAR(20)  DEFAULT 'teacher',
        subject      VARCHAR(200) DEFAULT NULL,
        grade        VARCHAR(50)  DEFAULT NULL,
        section      VARCHAR(80)  DEFAULT NULL,
        day_mon      TINYINT(1)   DEFAULT 0,
        day_tue      TINYINT(1)   DEFAULT 0,
        day_wed      TINYINT(1)   DEFAULT 0,
        day_thu      TINYINT(1)   DEFAULT 0,
        day_fri      TINYINT(1)   DEFAULT 0,
        day_sat      TINYINT(1)   DEFAULT 0,
        day_sun      TINYINT(1)   DEFAULT 0,
        time_start   VARCHAR(20)  DEFAULT NULL,
        time_end     VARCHAR(20)  DEFAULT NULL,
        minutes      INT          DEFAULT 0,
        sort_order   INT          DEFAULT 0,
        INDEX idx_tid (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "ALTER TABLE teacher_schedules ADD COLUMN IF NOT EXISTS day_sat TINYINT(1) DEFAULT 0",
    "ALTER TABLE teacher_schedules ADD COLUMN IF NOT EXISTS day_sun TINYINT(1) DEFAULT 0",

    "CREATE TABLE IF NOT EXISTS `principal` (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id            VARCHAR(50)  NOT NULL,
        teacher_name          VARCHAR(200) NOT NULL,
        last_name             VARCHAR(100) DEFAULT NULL,
        first_name            VARCHAR(100) DEFAULT NULL,
        middle_name           VARCHAR(100) DEFAULT NULL,
        gender                VARCHAR(10)  DEFAULT NULL,
        career_level          VARCHAR(80)  DEFAULT NULL,
        employment_status     VARCHAR(60)  DEFAULT NULL,
        teacher_qualification VARCHAR(80)  DEFAULT NULL,
        major                 VARCHAR(150) DEFAULT NULL,
        teacher_email         VARCHAR(150) DEFAULT NULL,
        teacher_contact       VARCHAR(50)  DEFAULT NULL,
        teacher_image         VARCHAR(255) DEFAULT NULL,
        advisory_class        VARCHAR(100) DEFAULT NULL,
        assigned_date         DATE         DEFAULT NULL,
        created_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `principal_history` (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id            VARCHAR(50)  NOT NULL,
        teacher_name          VARCHAR(200) NOT NULL,
        last_name             VARCHAR(100) DEFAULT NULL,
        first_name            VARCHAR(100) DEFAULT NULL,
        middle_name           VARCHAR(100) DEFAULT NULL,
        gender                VARCHAR(10)  DEFAULT NULL,
        career_level          VARCHAR(80)  DEFAULT NULL,
        employment_status     VARCHAR(60)  DEFAULT NULL,
        teacher_qualification VARCHAR(80)  DEFAULT NULL,
        major                 VARCHAR(150) DEFAULT NULL,
        teacher_email         VARCHAR(150) DEFAULT NULL,
        teacher_contact       VARCHAR(50)  DEFAULT NULL,
        teacher_image         VARCHAR(255) DEFAULT NULL,
        advisory_class        VARCHAR(100) DEFAULT NULL,
        assigned_date         DATE         DEFAULT NULL,
        replaced_date         DATE         DEFAULT NULL,
        created_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `teacher_history` (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        principal_hist_id     INT          NOT NULL,
        teacher_id            VARCHAR(50)  NOT NULL,
        teacher_name          VARCHAR(200) NOT NULL,
        last_name             VARCHAR(100) DEFAULT NULL,
        first_name            VARCHAR(100) DEFAULT NULL,
        middle_name           VARCHAR(100) DEFAULT NULL,
        gender                VARCHAR(10)  DEFAULT NULL,
        career_level          VARCHAR(80)  DEFAULT NULL,
        employment_status     VARCHAR(60)  DEFAULT NULL,
        teacher_qualification VARCHAR(80)  DEFAULT NULL,
        major                 VARCHAR(150) DEFAULT NULL,
        subject_assigned      VARCHAR(200) DEFAULT NULL,
        grade_section         VARCHAR(100) DEFAULT NULL,
        category              VARCHAR(20)  DEFAULT 'Teaching',
        funding_source        VARCHAR(80)  DEFAULT 'NATIONAL',
        teacher_email         VARCHAR(150) DEFAULT NULL,
        teacher_contact       VARCHAR(50)  DEFAULT NULL,
        snapshot_date         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ph (principal_hist_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($migs as $s) {
    $conn->query($s);
}

// ══════════════════════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════════════════════
function e($conn, $v)
{
    return mysqli_real_escape_string($conn, trim($v ?? ''));
}
function h($v)
{
    return htmlspecialchars($v ?? '');
}

// ══════════════════════════════════════════════════════════════════════════════
//  ENCRYPTION HELPERS  (AES-256-CBC)
//  Sensitive fields: teacher_id, teacher_email, teacher_contact
//  Key is derived from a secret stored outside webroot (or in config).
//  Store the key in an environment variable or a separate config file in
//  production — never hard-code in source control.
// ══════════════════════════════════════════════════════════════════════════════
define('ENC_KEY', hash('sha256', getenv('TEACHERS_ENC_KEY') ?: 'BUNHS_DEFAULT_SECRET_CHANGE_ME', true));
define('ENC_CIPHER', 'aes-256-cbc');
define('ENC_PREFIX', 'ENC::'); // marks encrypted values in the DB

function encField(string $value): string
{
    if ($value === '') return '';
    $iv         = random_bytes(openssl_cipher_iv_length(ENC_CIPHER));
    $encrypted  = openssl_encrypt($value, ENC_CIPHER, ENC_KEY, OPENSSL_RAW_DATA, $iv);
    return ENC_PREFIX . base64_encode($iv . $encrypted);
}

function decField(string $value): string
{
    if (!str_starts_with($value, ENC_PREFIX)) return $value; // plain legacy value
    $raw        = base64_decode(substr($value, strlen(ENC_PREFIX)));
    $ivLen      = openssl_cipher_iv_length(ENC_CIPHER);
    $iv         = substr($raw, 0, $ivLen);
    $ciphertext = substr($raw, $ivLen);
    $dec        = openssl_decrypt($ciphertext, ENC_CIPHER, ENC_KEY, OPENSSL_RAW_DATA, $iv);
    return ($dec === false) ? '[decrypt error]' : $dec;
}

// Decrypt a row's sensitive fields in-place
function decRow(array &$row): void
{
    foreach (
        [
            'teacher_id',
            'teacher_email',
            'teacher_contact',
            'teacher_name',
            'last_name',
            'first_name',
            'middle_name'
        ] as $col
    ) {
        if (isset($row[$col])) $row[$col] = decField($row[$col]);
    }
}

function saveImg()
{
    if (!isset($_FILES['teacher_image']) || $_FILES['teacher_image']['error'] != 0) return '';
    $dir = "../assets/img/person/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fn  = time() . '_' . basename($_FILES['teacher_image']['name']);
    if (getimagesize($_FILES['teacher_image']['tmp_name']) && move_uploaded_file($_FILES['teacher_image']['tmp_name'], $dir . $fn))
        return $dir . $fn;
    return '';
}

function fmtTime($t)
{
    if (!$t) return '';
    $d = DateTime::createFromFormat('H:i', $t);
    if ($d) return $d->format('g:i A');
    return $t;
}

function getScheds($conn, $tid, $type = 'teacher')
{
    $t   = e($conn, $tid);
    $tp  = e($conn, $type);
    $res = $conn->query("SELECT * FROM teacher_schedules WHERE teacher_id='$t' AND teacher_type='$tp' ORDER BY sort_order ASC");
    $r   = [];
    while ($row = $res->fetch_assoc()) $r[] = $row;
    return $r;
}

function saveScheds($conn, $tid, $type, $scheds)
{
    $t  = e($conn, $tid);
    $tp = e($conn, $type);
    $conn->query("DELETE FROM teacher_schedules WHERE teacher_id='$t' AND teacher_type='$tp'");
    foreach ($scheds as $i => $s) {
        $conn->query("INSERT INTO teacher_schedules
            (teacher_id,teacher_type,subject,grade,section,day_mon,day_tue,day_wed,day_thu,day_fri,day_sat,day_sun,time_start,time_end,minutes,sort_order)
            VALUES ('$t','$tp','" . e($conn, $s['subject']) . "','" . e($conn, $s['grade']) . "','" . e($conn, $s['section']) . "',
             " . ((int)$s['day_mon']) . "," . ((int)$s['day_tue']) . "," . ((int)$s['day_wed']) . "," . ((int)$s['day_thu']) . "," . ((int)$s['day_fri']) . ",
             " . ((int)($s['day_sat'] ?? 0)) . "," . ((int)($s['day_sun'] ?? 0)) . ",
             '" . e($conn, $s['time_start']) . "','" . e($conn, $s['time_end']) . "'," . ((int)$s['minutes']) . ",$i)");
    }
}

function parseScheds()
{
    $out  = [];
    $subj = $_POST['sched_subject'] ?? [];
    foreach ($subj as $i => $s) {
        if (trim($s) === '') continue;
        $out[] = [
            'subject'    => $s,
            'grade'      => $_POST['sched_grade'][$i]   ?? '',
            'section'    => $_POST['sched_section'][$i] ?? '',
            'day_mon'    => isset($_POST['sched_mon'][$i]) ? 1 : 0,
            'day_tue'    => isset($_POST['sched_tue'][$i]) ? 1 : 0,
            'day_wed'    => isset($_POST['sched_wed'][$i]) ? 1 : 0,
            'day_thu'    => isset($_POST['sched_thu'][$i]) ? 1 : 0,
            'day_fri'    => isset($_POST['sched_fri'][$i]) ? 1 : 0,
            'day_sat'    => isset($_POST['sched_sat'][$i]) ? 1 : 0,
            'day_sun'    => isset($_POST['sched_sun'][$i]) ? 1 : 0,
            'time_start' => $_POST['sched_tstart'][$i]  ?? '',
            'time_end'   => $_POST['sched_tend'][$i]    ?? '',
            'minutes'    => (int)($_POST['sched_mins'][$i] ?? 0),
        ];
    }
    return $out;
}

function snapshotTeachers($conn, $phId)
{
    $res = $conn->query("SELECT * FROM teachers WHERE is_principal=0");
    if (!$res) return;
    while ($t = $res->fetch_assoc()) {
        $conn->query("INSERT INTO teacher_history
            (principal_hist_id,teacher_id,teacher_name,last_name,first_name,middle_name,gender,
             career_level,employment_status,teacher_qualification,major,subject_assigned,
             grade_section,category,funding_source,teacher_email,teacher_contact)
            VALUES ($phId,
             '" . e($conn, $t['teacher_id']) . "','" . e($conn, $t['teacher_name']) . "',
             '" . e($conn, $t['last_name']) . "','" . e($conn, $t['first_name']) . "',
             '" . e($conn, $t['middle_name']) . "','" . e($conn, $t['gender']) . "',
             '" . e($conn, $t['career_level']) . "','" . e($conn, $t['employment_status']) . "',
             '" . e($conn, $t['teacher_qualification']) . "','" . e($conn, $t['major']) . "',
             '" . e($conn, $t['subject_assigned']) . "','" . e($conn, $t['grade_section']) . "',
             '" . e($conn, $t['category']) . "','" . e($conn, $t['funding_source']) . "',
             '" . e($conn, $t['teacher_email']) . "','" . e($conn, $t['teacher_contact']) . "')");
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLER
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act  = $_POST['action'];
    $scheds = parseScheds();

    // ── collect & encrypt sensitive fields ───────────────────────────────────
    // Plain fields (not PII — safe to store as-is)
    $plain = [
        'gender'                => trim($_POST['gender']              ?? ''),
        'career_level'          => trim($_POST['career_level']        ?? ''),
        'employment_status'     => trim($_POST['employment_status']   ?? ''),
        'teacher_qualification' => trim($_POST['teacher_qualification'] ?? ''),
        'major'                 => trim($_POST['major']               ?? ''),
        'subject_assigned'      => trim($_POST['subject_assigned']    ?? ''),
        'grade_section'         => trim($_POST['grade_section']       ?? ''),
        'room_assignment'       => trim($_POST['room_assignment']     ?? ''),
        'time_start'            => trim($_POST['time_start']          ?? ''),
        'time_end'              => trim($_POST['time_end']            ?? ''),
        'club_role'             => trim($_POST['club_role']           ?? ''),
        'advisory_class'        => trim($_POST['advisory_class']      ?? ''),
        'nt_position'           => trim($_POST['nt_position']         ?? ''),
        'nt_appt_type'          => trim($_POST['nt_appt_type']        ?? ''),
        'nt_fund_source'        => trim($_POST['nt_fund_source']      ?? ''),
        'category'              => trim($_POST['category']            ?? 'Teaching'),
        'funding_source'        => trim($_POST['funding_source']      ?? 'NATIONAL'),
        'teacher_grades'        => isset($_POST['teacher_grades'])  ? implode(', ', $_POST['teacher_grades'])  : '',
        'teacher_subjects'      => isset($_POST['teacher_subjects']) ? implode(', ', $_POST['teacher_subjects']) : '',
        'day_mon' => isset($_POST['day_mon']) ? 1 : 0,
        'day_tue' => isset($_POST['day_tue']) ? 1 : 0,
        'day_wed' => isset($_POST['day_wed']) ? 1 : 0,
        'day_thu' => isset($_POST['day_thu']) ? 1 : 0,
        'day_fri' => isset($_POST['day_fri']) ? 1 : 0,
    ];

    // PII fields — encrypt before storing
    $rawId      = trim($_POST['teacher_id']    ?? '');
    $rawLn      = trim($_POST['last_name']     ?? '');
    $rawFn      = trim($_POST['first_name']    ?? '');
    $rawMn      = trim($_POST['middle_name']   ?? '');
    $rawEmail   = trim($_POST['teacher_email'] ?? '');
    $rawContact = trim($_POST['teacher_contact'] ?? '');
    $rawName    = trim($_POST['teacher_name']  ?? '');

    $f = array_merge($plain, [
        'teacher_id'      => e($conn, encField($rawId)),
        'teacher_name'    => e($conn, encField($rawName)),
        'last_name'       => e($conn, encField($rawLn)),
        'first_name'      => e($conn, encField($rawFn)),
        'middle_name'     => e($conn, encField($rawMn)),
        'teacher_email'   => e($conn, encField($rawEmail)),
        'teacher_contact' => e($conn, encField($rawContact)),
        // plain fields (already set above, e() applied below)
        'gender'                => e($conn, $plain['gender']),
        'career_level'          => e($conn, $plain['career_level']),
        'employment_status'     => e($conn, $plain['employment_status']),
        'teacher_qualification' => e($conn, $plain['teacher_qualification']),
        'major'                 => e($conn, $plain['major']),
        'subject_assigned'      => e($conn, $plain['subject_assigned']),
        'grade_section'         => e($conn, $plain['grade_section']),
        'room_assignment'       => e($conn, $plain['room_assignment']),
        'time_start'            => e($conn, $plain['time_start']),
        'time_end'              => e($conn, $plain['time_end']),
        'club_role'             => e($conn, $plain['club_role']),
        'advisory_class'        => e($conn, $plain['advisory_class']),
        'nt_position'           => e($conn, $plain['nt_position']),
        'nt_appt_type'          => e($conn, $plain['nt_appt_type']),
        'nt_fund_source'        => e($conn, $plain['nt_fund_source']),
        'category'              => e($conn, $plain['category']),
        'funding_source'        => e($conn, $plain['funding_source']),
        'teacher_grades'        => e($conn, $plain['teacher_grades']),
        'teacher_subjects'      => e($conn, $plain['teacher_subjects']),
    ]);
    $img = saveImg();

    if ($act === 'add') {
        $isP = !empty($_POST['is_principal']);
        if ($isP) {
            // Archive current principal
            $old = $conn->query("SELECT * FROM `principal` LIMIT 1")->fetch_assoc();
            if ($old) {
                $conn->query("INSERT INTO principal_history
                    (teacher_id,teacher_name,last_name,first_name,middle_name,gender,career_level,
                     employment_status,teacher_qualification,major,teacher_email,teacher_contact,
                     teacher_image,advisory_class,assigned_date,replaced_date)
                    VALUES (
                     '" . e($conn, $old['teacher_id']) . "','" . e($conn, $old['teacher_name']) . "',
                     '" . e($conn, $old['last_name']) . "','" . e($conn, $old['first_name']) . "',
                     '" . e($conn, $old['middle_name']) . "','" . e($conn, $old['gender']) . "',
                     '" . e($conn, $old['career_level']) . "','" . e($conn, $old['employment_status']) . "',
                     '" . e($conn, $old['teacher_qualification']) . "','" . e($conn, $old['major']) . "',
                     '" . e($conn, $old['teacher_email']) . "','" . e($conn, $old['teacher_contact']) . "',
                     '" . e($conn, $old['teacher_image']) . "','" . e($conn, $old['advisory_class']) . "',
                     '" . e($conn, $old['assigned_date']) . "', CURDATE())");
                $phId = $conn->insert_id;
                snapshotTeachers($conn, $phId);
                $conn->query("DELETE FROM teacher_schedules WHERE teacher_id='" . e($conn, $old['teacher_id']) . "' AND teacher_type='principal'");
                $conn->query("DELETE FROM `principal`");
            }
            $conn->query("INSERT INTO `principal`
                (teacher_id,teacher_name,last_name,first_name,middle_name,gender,career_level,
                 employment_status,teacher_qualification,major,teacher_email,teacher_contact,
                 teacher_image,advisory_class,assigned_date)
                VALUES ('{$f['teacher_id']}','{$f['teacher_name']}','{$f['last_name']}',
                 '{$f['first_name']}','{$f['middle_name']}','{$f['gender']}','{$f['career_level']}',
                 '{$f['employment_status']}','{$f['teacher_qualification']}','{$f['major']}',
                 '{$f['teacher_email']}','{$f['teacher_contact']}','" . e($conn, $img) . "',
                 '{$f['advisory_class']}',CURDATE())");
            if (!empty($scheds)) saveScheds($conn, $f['teacher_id'], 'principal', $scheds);
            $_SESSION['success'] = "Principal appointed successfully.";
        } else {
            $conn->query("INSERT INTO teachers
                (teacher_id,teacher_name,last_name,first_name,middle_name,gender,career_level,
                 employment_status,teacher_qualification,major,subject_assigned,grade_section,
                 room_assignment,day_mon,day_tue,day_wed,day_thu,day_fri,time_start,time_end,
                 club_role,advisory_class,nt_position,nt_appt_type,nt_fund_source,category,
                 funding_source,teacher_email,teacher_contact,teacher_image,teacher_grades,
                 teacher_subjects,is_principal)
                VALUES ('{$f['teacher_id']}','{$f['teacher_name']}','{$f['last_name']}',
                 '{$f['first_name']}','{$f['middle_name']}','{$f['gender']}','{$f['career_level']}',
                 '{$f['employment_status']}','{$f['teacher_qualification']}','{$f['major']}',
                 '{$f['subject_assigned']}','{$f['grade_section']}','{$f['room_assignment']}',
                 {$f['day_mon']},{$f['day_tue']},{$f['day_wed']},{$f['day_thu']},{$f['day_fri']},
                 '{$f['time_start']}','{$f['time_end']}','{$f['club_role']}','{$f['advisory_class']}',
                 '{$f['nt_position']}','{$f['nt_appt_type']}','{$f['nt_fund_source']}',
                 '{$f['category']}','{$f['funding_source']}','{$f['teacher_email']}',
                 '{$f['teacher_contact']}','" . e($conn, $img) . "',
                 '{$f['teacher_grades']}','{$f['teacher_subjects']}',0)");
            if ($conn->affected_rows > 0) {
                if (!empty($scheds)) saveScheds($conn, $f['teacher_id'], 'teacher', $scheds);
                $_SESSION['success'] = "Personnel added successfully.";
            } else {
                $_SESSION['error'] = "Failed: " . $conn->error;
            }
        }
    } elseif ($act === 'edit') {
        $rid   = e($conn, $_POST['edit_record_id'] ?? '');
        $rtype = $_POST['edit_type'] ?? 'teacher';
        if ($rtype === 'principal') {
            $sql = "UPDATE `principal` SET
                teacher_id='{$f['teacher_id']}',teacher_name='{$f['teacher_name']}',
                last_name='{$f['last_name']}',first_name='{$f['first_name']}',
                middle_name='{$f['middle_name']}',gender='{$f['gender']}',
                career_level='{$f['career_level']}',employment_status='{$f['employment_status']}',
                teacher_qualification='{$f['teacher_qualification']}',major='{$f['major']}',
                teacher_email='{$f['teacher_email']}',teacher_contact='{$f['teacher_contact']}',
                advisory_class='{$f['advisory_class']}'";
            if ($img) $sql .= ",teacher_image='" . e($conn, $img) . "'";
            $sql .= " WHERE id='$rid'";
            $conn->query($sql);
            saveScheds($conn, $f['teacher_id'], 'principal', $scheds);
        } else {
            $sql = "UPDATE teachers SET
                teacher_id='{$f['teacher_id']}',teacher_name='{$f['teacher_name']}',
                last_name='{$f['last_name']}',first_name='{$f['first_name']}',
                middle_name='{$f['middle_name']}',gender='{$f['gender']}',
                career_level='{$f['career_level']}',employment_status='{$f['employment_status']}',
                teacher_qualification='{$f['teacher_qualification']}',major='{$f['major']}',
                subject_assigned='{$f['subject_assigned']}',grade_section='{$f['grade_section']}',
                room_assignment='{$f['room_assignment']}',
                day_mon={$f['day_mon']},day_tue={$f['day_tue']},day_wed={$f['day_wed']},
                day_thu={$f['day_thu']},day_fri={$f['day_fri']},
                time_start='{$f['time_start']}',time_end='{$f['time_end']}',
                club_role='{$f['club_role']}',advisory_class='{$f['advisory_class']}',
                nt_position='{$f['nt_position']}',nt_appt_type='{$f['nt_appt_type']}',
                nt_fund_source='{$f['nt_fund_source']}',category='{$f['category']}',
                funding_source='{$f['funding_source']}',teacher_email='{$f['teacher_email']}',
                teacher_contact='{$f['teacher_contact']}',
                teacher_grades='{$f['teacher_grades']}',teacher_subjects='{$f['teacher_subjects']}'";
            if ($img) $sql .= ",teacher_image='" . e($conn, $img) . "'";
            $sql .= " WHERE id='$rid'";
            $conn->query($sql);
            saveScheds($conn, $f['teacher_id'], 'teacher', $scheds);
        }
        $_SESSION['success'] = "Record updated successfully.";
    } elseif ($act === 'delete') {
        $rid   = e($conn, $_POST['delete_record_id'] ?? '');
        $rtype = $_POST['delete_type'] ?? 'teacher';
        if ($rtype === 'principal') {
            $p = $conn->query("SELECT teacher_id FROM `principal` WHERE id='$rid'")->fetch_assoc();
            if ($p) $conn->query("DELETE FROM teacher_schedules WHERE teacher_id='" . e($conn, $p['teacher_id']) . "' AND teacher_type='principal'");
            $conn->query("DELETE FROM `principal` WHERE id='$rid'");
        } else {
            $t = $conn->query("SELECT teacher_id FROM teachers WHERE id='$rid'")->fetch_assoc();
            if ($t) $conn->query("DELETE FROM teacher_schedules WHERE teacher_id='" . e($conn, $t['teacher_id']) . "' AND teacher_type='teacher'");
            $conn->query("DELETE FROM teachers WHERE id='$rid'");
        }
        $_SESSION['success'] = "Deleted successfully.";
    }

    header("Location: teachers.php");
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH DATA
// ══════════════════════════════════════════════════════════════════════════════
$principal = $conn->query("SELECT * FROM `principal` LIMIT 1")->fetch_assoc();
if ($principal) decRow($principal);

$rankOrder = [
    'School Principal I' => 0,
    'School Principal II' => 0,
    'School Principal III' => 0,
    'Principal I' => 0,
    'Principal II' => 0,
    'Principal III' => 0,
    'Master Teacher IV' => 1,
    'Master Teacher III' => 2,
    'Master Teacher II' => 3,
    'Master Teacher I' => 4,
    'Head Teacher III' => 5,
    'Head Teacher II' => 6,
    'Head Teacher I' => 7,
    'Teacher III' => 8,
    'Teacher II' => 9,
    'Teacher I' => 10,
];
$res = $conn->query("SELECT * FROM teachers WHERE is_principal=0");
$teachers = [];
while ($r = $res->fetch_assoc()) {
    decRow($r);
    $teachers[] = $r;
}
usort($teachers, function ($a, $b) use ($rankOrder) {
    $ra = $rankOrder[$a['career_level'] ?? ''] ?? 99;
    $rb = $rankOrder[$b['career_level'] ?? ''] ?? 99;
    return $ra !== $rb ? $ra - $rb : strcmp($a['teacher_name'], $b['teacher_name']);
});
$teaching    = array_values(array_filter($teachers, fn($t) => ($t['category'] ?? 'Teaching') === 'Teaching'));
$nonTeaching = array_values(array_filter($teachers, fn($t) => ($t['category'] ?? 'Teaching') === 'Non-Teaching'));

$phRes = $conn->query("SELECT * FROM principal_history ORDER BY replaced_date DESC");
$principalHistory = [];
while ($r = $phRes->fetch_assoc()) {
    decRow($r);
    $principalHistory[] = $r;
}

// Stats
$totalPers   = count($teachers) + ($principal ? 1 : 0);
$teachingCnt = count($teaching) + ($principal ? 1 : 0);
$ntCnt       = count($nonTeaching);
$pgCnt       = count(array_filter($teachers, fn($t) => in_array($t['teacher_qualification'] ?? '', ['post-graduate', 'masteral', 'doctoral'])));

// Table 1 position counts
$posCounts = [];
foreach ($teachers as $t) {
    $cl = $t['career_level'] ?? 'Unknown';
    $posCounts[$cl] = ($posCounts[$cl] ?? 0) + 1;
}
if ($principal) {
    $pcl = $principal['career_level'] ?? 'School Principal I';
    $posCounts[$pcl] = ($posCounts[$pcl] ?? 0) + 1;
}
$pRanks = ['School Principal I', 'School Principal II', 'School Principal III', 'Principal I', 'Principal II', 'Principal III'];
$tRanks = ['Master Teacher IV', 'Master Teacher III', 'Master Teacher II', 'Master Teacher I', 'Head Teacher III', 'Head Teacher II', 'Head Teacher I', 'Teacher III', 'Teacher II', 'Teacher I'];
$t1Pos  = [];
foreach (array_merge($pRanks, $tRanks) as $pos) {
    if (isset($posCounts[$pos])) $t1Pos[$pos] = $posCounts[$pos];
}
foreach ($posCounts as $pos => $cnt) {
    if (!isset($t1Pos[$pos])) $t1Pos[$pos] = $cnt;
}

$educMap = [
    'bachelor'        => "Bachelor's Degree",
    'bachelors-units' => "Bachelor's w/ PG Units",
    'post-graduate'   => "Masters (Unit)",
    'masteral'        => "Master's Degree",
    'doctoral'        => "Doctoral Degree",
    'lac'             => 'LAC',
    'k12'             => 'K-12',
    'content'         => 'Content',
    'others'          => 'Others',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Teacher Information | BUNHS Admin</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="../overall_body.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── Root ── */
        :root {
            --tp: #8a9a5b;
            --ts: #10b981;
            --tp-lt: rgba(138, 154, 91, .10);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light-color);
            margin: 0;
            padding: 0;
        }

        /* ── Layout: clear the fixed sidebar (260px) and fixed topbar (70px) ── */
        .page-content {
            margin-left: 0;
            /* sidebar width */
            margin-top: 70px;
            /* topbar height */
            padding: 24px 28px;
            min-height: calc(100vh - 70px);
            box-sizing: border-box;
        }

        /* Fallback for narrower viewports where sidebar collapses */
        @media (max-width: 900px) {
            .page-content {
                margin-left: 0;
                margin-top: 60px;
                padding: 16px;
            }
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
            /* stacking context so buttons are always on top */
            z-index: 10;
        }

        .page-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary, #111);
            margin: 0;
        }

        .page-header p {
            font-size: 14px;
            color: var(--text-secondary, #6b7280);
            margin: 4px 0 0;
        }

        .hdr-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .add-btn {
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all .2s;
        }

        .add-btn.g {
            background: linear-gradient(135deg, var(--tp), var(--ts));
            color: #fff;
            box-shadow: 0 2px 8px rgba(138, 154, 91, .30);
        }

        .add-btn.o {
            background: linear-gradient(135deg, #d97706, #fbbf24);
            color: #fff;
            box-shadow: 0 2px 8px rgba(217, 119, 6, .30);
        }

        .add-btn:hover {
            transform: translateY(-2px);
        }

        /* Alerts */
        .alert-c {
            padding: 0 0 16px;
        }

        .alert {
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-s {
            background: rgba(16, 185, 129, .10);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, .20);
        }

        .alert-d {
            background: rgba(239, 68, 68, .10);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, .20);
        }

        /* Stats */
        .sg {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .sc {
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .07);
            border: 1px solid #e5e7eb;
            position: relative;
            overflow: hidden;
            transition: transform .22s;
        }

        .sc:hover {
            transform: translateY(-3px);
        }

        .sc::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            border-radius: 14px 14px 0 0;
        }

        .sc.grn::before {
            background: linear-gradient(90deg, #16a34a, #22c55e);
        }

        .sc.blu::before {
            background: linear-gradient(90deg, #2563eb, #60a5fa);
        }

        .sc.org::before {
            background: linear-gradient(90deg, #d97706, #fbbf24);
        }

        .sc.pur::before {
            background: linear-gradient(90deg, #7c3aed, #a78bfa);
        }

        .si {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }

        .si.grn {
            background: #dcfce7;
            color: #16a34a;
        }

        .si.blu {
            background: #dbeafe;
            color: #2563eb;
        }

        .si.org {
            background: #fef3c7;
            color: #d97706;
        }

        .si.pur {
            background: #ede9fe;
            color: #7c3aed;
        }

        .slbl {
            font-size: 11.5px;
            color: #9ca3af;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 5px;
        }

        .sval {
            font-size: 26px;
            font-weight: 700;
            color: #111827;
            line-height: 1.1;
        }

        .ssub {
            font-size: 11.5px;
            color: #6b7280;
            margin-top: 4px;
        }

        /* SF7 Wrapper */
        .sf7w {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .07);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .sf7tb {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, rgba(138, 154, 91, .04), rgba(16, 185, 129, .04));
            flex-wrap: wrap;
            gap: 12px;
        }

        .sf7tb h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pbtn {
            background: linear-gradient(135deg, var(--tp), var(--ts));
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 10px 18px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .sscroll {
            overflow-x: auto;
            padding: 20px;
        }

        .sdlbl {
            background: linear-gradient(135deg, rgba(138, 154, 91, .07), rgba(16, 185, 129, .07));
            border-bottom: 2px solid #c5cfe0;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sdlbl h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #1e3a2f;
        }

        /* ══ TABLE 1 — SF7 Header ══ */

        .t1wrap {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
            min-width: 1100px;
            width: 100%;
        }

        /* Header outer table */
        .t1htable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
        }

        .t1htable td {
            border: 1px solid #111;
            vertical-align: middle;
            background: #fff;
            padding: 0;
        }

        /* Logo cell — spans all header rows */
        .t1logo {
            width: 90px;
            text-align: center;
            padding: 6px 4px;
            vertical-align: middle;
        }

        .t1logo img {
            width: 76px;
            height: 76px;
            object-fit: contain;
        }

        .t1lph {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 1.5px solid #aaa;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 600;
            color: #666;
            background: #f9f9f9;
        }

        /* Title cell */
        .t1title {
            text-align: center;
            font-size: 15px;
            font-weight: 800;
            padding: 8px 12px 2px;
            border-bottom: none !important;
            line-height: 1.3;
        }

        /* Subtitle cell */
        .t1sub {
            font-size: 10px;
            padding: 2px 12px 7px;
            color: #222;
            line-height: 1.4;
            border-top: none !important;
        }

        /* Field cells: label + boxed value */
        .t1field {
            padding: 4px 7px 4px 7px;
            vertical-align: middle;
        }

        .t1lbl {
            font-weight: 700;
            font-size: 10.5px;
            white-space: nowrap;
            display: block;
            line-height: 1.3;
            margin-bottom: 2px;
        }

        .t1val {
            font-size: 11px;
            font-weight: 400;
            border: 1px solid #555;
            padding: 1px 6px;
            line-height: 1.6;
            display: block;
            background: #fff;
        }

        .t1val-bold {
            font-weight: 700;
        }

        /* Label-only cell (School Name label on left) */
        .t1label-only {
            padding: 4px 7px;
            font-weight: 700;
            font-size: 10.5px;
            vertical-align: middle;
            white-space: nowrap;
            width: 90px;
        }

        /* ── Table 1 styling for multi-level headers ── */
        .t1 tr:first-child td {
            border-bottom: 2px solid #111 !important;
            vertical-align: middle !important;
            padding: 8px 6px !important;
            line-height: 1.4 !important;
        }

        .t1 tr:nth-child(2) td {
            border-bottom: 1px solid #111 !important;
        }

        .t1 tr:nth-child(3) td {
            border-top: 1px solid #111 !important;
            font-weight: 700;
            font-size: 10px;
            text-align: center;
            vertical-align: middle;
            padding: 6px 4px;
            line-height: 1.2;
            height: 35px;
        }

        /* ── Banner row (removed/deprecated) ── */
        .t1banrow {
            display: none;
        }

        .t1ban {
            display: none;
        }

        /* Data table */
        .t1 {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111;
            table-layout: fixed;
            background: #fff;
        }

        .t1 td {
            border: 1px solid #111;
            padding: 4px 6px;
            vertical-align: middle;
            line-height: 1.4;
            background: #fff;
        }

        /* Section separators — bold borders between A, B, C */
        .t1 tr:nth-child(n+1) td:nth-child(3),
        .t1 tr:nth-child(n+1) td:nth-child(5) {
            border-left: 2px solid #111 !important;
        }

        .t1 tr:first-child td:nth-child(3),
        .t1 tr:first-child td:nth-child(5) {
            border-left: 2px solid #111 !important;
            border-right: 1px solid #111 !important;
        }

        .t1ch {
            font-weight: 700;
            font-size: 10px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.3;
            padding: 8px 4px;
            min-height: auto;
            height: auto;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            overflow: hidden;
            display: table-cell;
            white-space: normal;
        }

        /* Tall header columns - 70px height with rowspan="2" */
        .t1ch-tall {
            min-height: 70px;
            height: 70px;
            padding: 12px 4px;
            vertical-align: middle;
        }

        /* Header cell for "Number of incumbent" */
        .t1ch-number-header {
            min-height: 35px;
            height: 35px;
            padding: 6px 4px;
            vertical-align: middle;
            font-weight: 700;
            font-size: 10px;
            text-align: center;
            line-height: 1.3;
            border-bottom: none !important;
        }

        /* Sub-header cells for "Teaching" and "Non-Teaching" */
        .t1ch-teaching {
            font-weight: 700;
            font-size: 10px;
            padding: 6px 4px;
            height: 35px;
            text-align: center;
            vertical-align: middle;
            border-top: 1px solid #111;
        }

        .t1chlt {
            font-weight: 400;
            font-size: 9px;
            display: block;
            margin-top: 2px;
            font-style: italic;
            line-height: 1.2;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .t1dr td {
            padding: 5px 6px;
            font-size: 11px;
            height: 28px;
        }

        .t1pos {
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            text-align: center;
        }

        .t1num {
            text-align: center;
            font-weight: 600;
            font-size: 11px;
        }

        .t1appt {
            font-size: 10.5px;
            text-transform: uppercase;
            text-align: center;
        }

        .t1fund {
            font-size: 10.5px;
            text-align: center;
        }

        .t1tot td {
            font-weight: 800;
            font-size: 11px;
            padding: 5px 6px;
        }

        /* ══ TABLE 2 — SF7 Personnel Assignment Layout ══
   Columns (23 total + actions):
   c1=EmpNo  c2=Name  c3=Sex  c4=Fund
   c5=Position  c6=ApptStatus
   c7=Educ  c8=Major  c9=Advisory
   c10=Subject  c11=Gr  c12=Section
   c13=M  c14=T  c15=W  c16=TH  c17=F  c18=SAT  c19=SUN
   c20=TimeStart  c21=TimeEnd  c22=Mins
   c23=Actions(no-print)
══ */
        /* ══ TABLE 2 — SF7 Personnel Assignment — PDF-matched compact layout ══ */
        .sf7t {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, Calibri, sans-serif;
            font-size: 6.8px;
            color: #000;
            background: #fff;
            table-layout: auto;
        }

        /* Define column widths for proper layout - EXACT SEQUENCE */
        .sf7t .c-id {
            width: 65px;
            min-width: 65px;
        }

        .sf7t .c-nm {
            width: 110px;
            min-width: 110px;
        }

        .sf7t .c-sx {
            width: 45px;
            min-width: 45px;
        }

        .sf7t .c-fd {
            width: 60px;
            min-width: 60px;
        }

        .sf7t .c-pos {
            width: 70px;
            min-width: 70px;
        }

        .sf7t .c-ap {
            width: 85px;
            min-width: 85px;
        }

        .sf7t .c-ed {
            width: 55px;
            min-width: 55px;
        }

        .sf7t .c-sb {
            width: 100px;
            min-width: 100px;
        }

        .sf7t .c-gr {
            width: 32px;
            min-width: 32px;
        }

        .sf7t .c-sec {
            width: 70px;
            min-width: 70px;
        }

        .sf7t .c-day {
            width: 28px;
            min-width: 28px;
        }

        .sf7t .c-tm {
            width: 70px;
            min-width: 70px;
        }

        .sf7t .c-mn {
            width: 50px;
            min-width: 50px;
        }

        .sf7t .c-act {
            width: 50px;
            min-width: 50px;
        }

        .sf7t td {
            border: 0.75px solid #333;
            padding: 2px 2px;
            vertical-align: middle;
            line-height: 1.25;
            background: #fff;
            height: 18px;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-word;
        }

        /* Header rows — match PDF blue-grey shading */
        .sf7t .r-h1 td {
            background: #b8c5d6;
            font-weight: 800;
            font-size: 6.8px;
            text-align: center;
            text-transform: uppercase;
            vertical-align: middle;
            padding: 3px 1px;
            letter-spacing: .15px;
            line-height: 1.3;
            border: 0.75px solid #333;
        }

        .sf7t .r-h2 td {
            background: #cdd8e6;
            font-weight: 700;
            font-size: 6.2px;
            text-align: center;
            text-transform: uppercase;
            vertical-align: middle;
            padding: 2px 1px;
            border: 0.75px solid #333;
            line-height: 1.2;
        }

        /* Personnel block cells */
        .sf7t .c-id {
            font-size: 6.8px;
            text-align: center;
            color: #000;
            white-space: nowrap;
            overflow: hidden;
            font-weight: 600;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-nm {
            font-weight: 700;
            font-size: 6.8px;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
            padding: 2px 2px !important;
            line-height: 1.25;
            word-break: break-word;
        }

        .sf7t .c-sx {
            text-align: center;
            font-size: 6.8px;
            text-transform: uppercase;
            font-weight: 600;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-fd {
            text-align: center;
            font-size: 6.5px;
            text-transform: uppercase;
            font-weight: 600;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-pos {
            font-weight: 700;
            font-size: 6.5px;
            text-transform: uppercase;
            text-align: center;
            line-height: 1.25;
            word-break: break-word;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-ap {
            font-size: 6.2px;
            text-transform: uppercase;
            text-align: center;
            line-height: 1.2;
            word-break: break-word;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-ed {
            font-size: 6.2px;
            text-align: center;
            line-height: 1.2;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-mj {
            font-weight: 600;
            font-size: 6.5px;
            text-transform: uppercase;
            text-align: center;
            line-height: 1.3;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-adv {
            text-align: center;
            font-size: 7px;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-sb {
            font-size: 7px;
            font-weight: 600;
            line-height: 1.25;
            text-transform: uppercase;
            text-align: center;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-gr {
            text-align: center;
            font-size: 7px;
            font-weight: 700;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-sec {
            font-size: 7px;
            text-align: center;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-day {
            text-align: center;
            font-size: 7px;
            font-weight: 700;
            padding: 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-tm {
            text-align: center;
            font-size: 7px;
            white-space: nowrap;
            font-weight: 600;
            padding: 2px 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-mn {
            text-align: center;
            font-size: 7px;
            font-weight: 700;
            padding-right: 2px !important;
            padding-left: 1px !important;
            vertical-align: middle;
        }

        .sf7t .c-act {
            text-align: center;
            white-space: nowrap;
            padding: 1px !important;
            vertical-align: middle;
        }

        /* Thick top border between persons — matches PDF group separator */
        .sf7t .pfirst td {
            border-top: 2px solid #000 !important;
            height: 26px;
            line-height: 1.3;
        }

        .sf7t .pfirst .c-id,
        .sf7t .pfirst .c-nm,
        .sf7t .pfirst .c-sx,
        .sf7t .pfirst .c-fd,
        .sf7t .pfirst .c-pos,
        .sf7t .pfirst .c-ap,
        .sf7t .pfirst .c-ed,
        .sf7t .pfirst .c-mj,
        .sf7t .pfirst .c-adv,
        .sf7t .pfirst .c-sb,
        .sf7t .pfirst .c-gr,
        .sf7t .pfirst .c-sec,
        .sf7t .pfirst .c-day,
        .sf7t .pfirst .c-tm,
        .sf7t .pfirst .c-mn,
        .sf7t .pfirst .c-act {
            padding: 3px 2px;
            vertical-align: middle;
        }

        /* Total row per person */
        .sf7t .ptot td {
            background: #f0f0f0;
            font-weight: 800;
            font-size: 7px;
            border-top: 1.5px solid #555;
            height: 16px;
        }

        /* Non-teaching section */
        .sf7t .nt-hdr td {
            background: #b8c5d6;
            font-weight: 800;
            font-size: 7px;
            text-align: left;
            padding: 3px 5px;
            text-transform: uppercase;
            border: 0.75px solid #333;
        }

        .sf7t .nt-ch td {
            background: #cdd8e6;
            font-weight: 700;
            font-size: 6.5px;
            text-align: center;
            text-transform: uppercase;
            padding: 2px 2px;
            border: 0.75px solid #333;
        }

        .sf7t .nt-r td {
            background: #fff;
            font-size: 7px;
            padding: 2px 3px;
            border: 0.75px solid #333;
            height: 20px;
        }

        /* Action buttons */
        .ab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            transition: all .2s;
            text-decoration: none;
            margin: 1px;
        }

        .ab.e {
            background: rgba(59, 130, 246, .12);
            color: #3b82f6;
        }

        .ab.e:hover {
            background: #3b82f6;
            color: #fff;
        }

        .ab.d {
            background: rgba(239, 68, 68, .12);
            color: #ef4444;
        }

        .ab.d:hover {
            background: #ef4444;
            color: #fff;
        }

        /* History */
        .hw {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .07);
            overflow: hidden;
            margin-bottom: 24px;
            border-left: 4px solid #d97706;
        }

        .hh {
            padding: 14px 20px;
            background: linear-gradient(135deg, rgba(217, 119, 6, .06), rgba(251, 191, 36, .06));
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .hh h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #92400e;
            flex: 1;
        }

        .hb {
            padding: 16px 20px;
            display: none;
        }

        .hb.open {
            display: block;
        }

        .htbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .htbl th {
            background: #fef3c7;
            padding: 7px 8px;
            font-weight: 700;
            border: 1px solid #e5e7eb;
        }

        .htbl td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
        }

        .htbl tr:nth-child(even) td {
            background: #fffbf0;
        }

        /* ══ MODAL — Redesigned ══════════════════════════════════════════════ */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            inset: 0;
            background: rgba(15, 23, 42, .60);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }

        .mc {
            background: #f8fafc;
            margin: 1.5vh auto;
            border-radius: 20px;
            box-shadow: 0 32px 80px rgba(0, 0, 0, .28), 0 0 0 1px rgba(255, 255, 255, .12);
            width: 96%;
            max-width: 980px;
            max-height: 97vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: modalIn .28s cubic-bezier(.34, 1.56, .64, 1);
        }

        @keyframes modalIn {
            from {
                transform: translateY(-24px) scale(.97);
                opacity: 0
            }

            to {
                transform: translateY(0) scale(1);
                opacity: 1
            }
        }

        /* ── Modal Header ── */
        .mhd {
            padding: 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .mhd-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px 14px;
        }

        .mhd-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mhd-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .mhd.grn .mhd-icon {
            background: linear-gradient(135deg, #4ade80, #16a34a);
            color: #fff;
        }

        .mhd.gld .mhd-icon {
            background: linear-gradient(135deg, #fbbf24, #d97706);
            color: #fff;
        }

        .mhd-title-wrap h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }

        .mhd-title-wrap p {
            margin: 2px 0 0;
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        .xbtn {
            width: 36px;
            height: 36px;
            border: none;
            background: #f1f5f9;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .18s;
            color: #64748b;
            flex-shrink: 0;
        }

        .xbtn:hover {
            background: #ef4444;
            color: #fff;
        }

        /* ── Step Progress Bar ── */
        .step-bar {
            display: flex;
            padding: 0 24px 16px;
            gap: 0;
            position: relative;
        }

        .step-bar::before {
            content: '';
            position: absolute;
            top: 16px;
            left: 40px;
            right: 40px;
            height: 2px;
            background: #e2e8f0;
            z-index: 0;
        }

        .step-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            position: relative;
            z-index: 1;
            cursor: pointer;
        }

        .step-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #94a3b8;
            transition: all .25s;
            border: 2px solid #e2e8f0;
        }

        .step-label {
            font-size: 10px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
            transition: color .25s;
        }

        .step-item.active .step-dot {
            background: var(--tp);
            border-color: var(--tp);
            color: #fff;
            box-shadow: 0 0 0 4px rgba(138, 154, 91, .18);
        }

        .step-item.active .step-label {
            color: var(--tp);
        }

        .step-item.done .step-dot {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }

        .step-item.done .step-label {
            color: #10b981;
        }

        .mhd.grn .step-bar::before {
            background: #e2e8f0;
        }

        /* ── Scrollable Body ── */
        .msc {
            overflow-y: auto;
            flex: 1;
            scroll-behavior: smooth;
        }

        .mb {
            padding: 20px 24px;
        }

        /* ── Step Panels ── */
        .step-panel {
            display: none;
        }

        .step-panel.active {
            display: block;
            animation: fadeStep .2s ease;
        }

        @keyframes fadeStep {
            from {
                opacity: 0;
                transform: translateX(10px)
            }

            to {
                opacity: 1;
                transform: translateX(0)
            }
        }

        /* ── Section Cards ── */
        .fsec {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 14px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
        }

        .fst {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--tp);
            padding-bottom: 12px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 7px;
            border-bottom: 2px solid var(--tp-lt);
        }

        .fst i {
            font-size: 12px;
        }

        .fr {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 12px;
        }

        .fr:last-child {
            margin-bottom: 0;
        }

        .fr.c3 {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .fr.c1 {
            grid-template-columns: 1fr;
        }

        /* ── Field Groups ── */
        .fg {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .fg label {
            font-weight: 700;
            font-size: 11.5px;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .fg label i {
            font-size: 10px;
            color: var(--tp);
            width: 14px;
            text-align: center;
        }

        .req {
            color: #ef4444;
            font-size: 13px;
            line-height: 1;
        }

        .fg input,
        .fg select,
        .fg textarea {
            padding: 9px 13px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: 13px;
            transition: all .2s;
            background: #f8fafc;
            font-family: inherit;
            color: #0f172a;
        }

        .fg input:focus,
        .fg select:focus,
        .fg textarea:focus {
            outline: none;
            border-color: var(--tp);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(138, 154, 91, .14);
        }

        .fg input::placeholder {
            color: #cbd5e1;
        }

        /* ── Principal Toggle ── */
        .ptog {
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1.5px solid #fde68a;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 16px;
        }

        /* ── Schedule Table ── */
        .stbl {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 11.5px;
            margin-top: 8px;
            border-radius: 10px;
            overflow: hidden;
            border: 1.5px solid #e2e8f0;
            table-layout: fixed;
        }

        .stbl th {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            padding: 8px 4px;
            text-align: center;
            font-size: 9.5px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .3px;
            border-bottom: 1.5px solid #e2e8f0;
            white-space: nowrap;
            overflow: hidden;
        }

        .stbl td {
            padding: 5px 3px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            background: #fff;
        }

        .stbl tbody tr:last-child td {
            border-bottom: none;
        }

        .stbl tbody tr:hover td {
            background: #f8fafc;
        }

        .stbl input[type=text],
        .stbl input[type=number] {
            padding: 5px 6px;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            font-size: 11px;
            width: 100%;
            box-sizing: border-box;
            background: #f8fafc;
            transition: all .15s;
            color: #0f172a;
        }

        /* Time inputs: give them enough room so clock icon + value are visible */
        .stbl input[type=time] {
            padding: 5px 3px;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            font-size: 10px;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
            background: #f8fafc;
            transition: all .15s;
            color: #0f172a;
        }

        .stbl input[type=text]:focus,
        .stbl input[type=number]:focus,
        .stbl input[type=time]:focus {
            outline: none;
            border-color: var(--tp);
            background: #fff;
            box-shadow: 0 0 0 2px rgba(138, 154, 91, .12);
        }

        .stbl input[type=checkbox] {
            width: 15px;
            height: 15px;
            cursor: pointer;
            margin: 0 auto;
            display: block;
            accent-color: var(--tp);
        }

        .sadd {
            background: linear-gradient(135deg, var(--tp), var(--ts));
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .18s;
            box-shadow: 0 2px 6px rgba(138, 154, 91, .25);
        }

        .sadd:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(138, 154, 91, .35);
        }

        .sdel {
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
            padding: 4px 9px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            transition: all .15s;
        }

        .sdel:hover {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
        }

        /* ── Modal Footer ── */
        .mft {
            padding: 14px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: #f8fafc;
            gap: 12px;
        }

        .mft-left {
            display: flex;
            gap: 8px;
        }

        .mft-right {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .step-indicator {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
        }

        .cbtn {
            padding: 9px 20px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            background: #fff;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .18s;
        }

        .cbtn:hover {
            border-color: #ef4444;
            color: #ef4444;
            background: #fef2f2;
        }

        .nbtn {
            padding: 9px 22px;
            border: none;
            border-radius: 9px;
            background: #f1f5f9;
            color: #475569;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .18s;
        }

        .nbtn:hover {
            background: #e2e8f0;
        }

        .sbtn {
            padding: 9px 24px;
            border: none;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: all .2s;
        }

        .sbtn.grn {
            background: linear-gradient(135deg, var(--tp), var(--ts));
            color: #fff;
            box-shadow: 0 3px 10px rgba(138, 154, 91, .30);
        }

        .sbtn.gld {
            background: linear-gradient(135deg, #d97706, #fbbf24);
            color: #fff;
            box-shadow: 0 3px 10px rgba(217, 119, 6, .30);
        }

        .sbtn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(138, 154, 91, .38);
        }

        .sbtn.gld:hover {
            box-shadow: 0 6px 18px rgba(217, 119, 6, .38);
        }

        /* Photo Upload Zone */
        .photo-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            background: #f8fafc;
            position: relative;
        }

        .photo-zone:hover {
            border-color: var(--tp);
            background: rgba(138, 154, 91, .04);
        }

        .photo-zone input[type=file] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .photo-zone i {
            font-size: 28px;
            color: #94a3b8;
            margin-bottom: 8px;
            display: block;
        }

        .photo-zone p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        .photo-zone span {
            font-size: 11px;
            color: #94a3b8;
        }

        /* Day pill checkboxes */
        .day-pills {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .day-pill {
            position: relative;
        }

        .day-pill input {
            position: absolute;
            opacity: 0;
        }

        .day-pill label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all .18s;
            text-transform: none;
            letter-spacing: 0;
        }

        .day-pill input:checked+label {
            background: var(--tp);
            border-color: var(--tp);
            color: #fff;
            box-shadow: 0 2px 6px rgba(138, 154, 91, .3);
        }

        .tg {
            position: relative;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .tg input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .tgsl {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #cbd5e1;
            border-radius: 24px;
            transition: .3s;
        }

        .tgsl:before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: .3s;
        }

        .tg input:checked+.tgsl {
            background: #d97706;
        }

        .tg input:checked+.tgsl:before {
            transform: translateX(20px);
        }



        #delf {
            display: none;
        }

        /* Tooltip */
        [data-tip] {
            position: relative;
        }

        [data-tip]::after {
            content: attr(data-tip);
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            padding: 4px 9px;
            background: #1f2937;
            color: #fff;
            font-size: 11px;
            border-radius: 5px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all .2s;
            z-index: 100;
        }

        [data-tip]:hover::after {
            opacity: 1;
            visibility: visible;
        }

        /* Print */
        @media print {
            body {
                background: #fff;
                margin: 0;
                padding: 0;
                font-size: 8pt;
            }

            .page-header,
            .sg,
            .sf7tb,
            .sdlbl,
            .topbar,
            .sidebar,
            .no-print,
            .hw {
                display: none !important;
            }

            .page-content {
                margin: 0;
                padding: 0;
                min-height: auto;
            }

            .sf7w {
                border-radius: 0;
                box-shadow: none;
                margin-bottom: 0;
                page-break-after: avoid;
            }

            .sscroll {
                padding: 0;
                overflow: visible !important;
            }

            .sf7t {
                width: 100%;
                font-size: 6pt;
                table-layout: fixed;
            }

            .sf7t td {
                padding: 1px 1px;
                height: auto;
                min-height: 14px;
                border: 0.5px solid #333;
            }

            .sf7t .r-h1 td,
            .sf7t .r-h2 td {
                font-size: 5.5pt;
                padding: 1px 0.5px;
            }

            .sf7t .c-nm {
                font-size: 6pt;
                padding-top: 1px !important;
            }

            .t1wrap {
                min-width: unset;
            }

            .t1htable {
                min-width: unset;
            }

            .t1 {
                min-width: unset;
            }
        }

        @media(max-width:768px) {

            .fr,
            .fr.c3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'admin_nav.php'; ?>
    <div class="page-content">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h2><i class="fas fa-chalkboard-teacher" style="color:var(--tp);margin-right:8px;"></i>Teacher Management</h2>
                <p>SF7 Personnel Assignment List &amp; Basic Profile</p>
            </div>
            <div class="hdr-actions">
                <button id="btn-principal" class="add-btn o"><i class="fas fa-crown"></i> Add Principal</button>
                <button id="btn-teacher" class="add-btn g"><i class="fas fa-plus"></i> Add Personnel</button>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-c">
                <div class="alert alert-s"><i class="fas fa-check-circle"></i> <?= h($_SESSION['success']);
                                                                                unset($_SESSION['success']); ?></div>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-c">
                <div class="alert alert-d"><i class="fas fa-exclamation-circle"></i> <?= h($_SESSION['error']);
                                                                                        unset($_SESSION['error']); ?></div>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="sg">
            <div class="sc grn">
                <div class="si grn"><i class="fas fa-users"></i></div>
                <div>
                    <div class="slbl">Total Personnel</div>
                    <div class="sval"><?= $totalPers ?></div>
                    <div class="ssub">All registered staff</div>
                </div>
            </div>
            <div class="sc blu">
                <div class="si blu"><i class="fas fa-chalkboard"></i></div>
                <div>
                    <div class="slbl">Teaching</div>
                    <div class="sval"><?= $teachingCnt ?></div>
                    <div class="ssub">Incl. principal</div>
                </div>
            </div>
            <div class="sc org">
                <div class="si org"><i class="fas fa-graduation-cap"></i></div>
                <div>
                    <div class="slbl">Post-Graduate</div>
                    <div class="sval"><?= $pgCnt ?></div>
                    <div class="ssub">Advanced degree</div>
                </div>
            </div>
            <div class="sc pur">
                <div class="si pur"><i class="fas fa-history"></i></div>
                <div>
                    <div class="slbl">Past Principals</div>
                    <div class="sval"><?= count($principalHistory) ?></div>
                    <div class="ssub">History records</div>
                </div>
            </div>
        </div>

        <!-- ══ SF7 ══ -->
        <div class="sf7w">

            <div class="sf7tb no-print">
                <h3><i class="fas fa-file-alt" style="color:var(--tp);"></i> School Form 7 (SF7) – School Personnel Assignment List &amp; Basic Profile</h3>
                <button class="pbtn" onclick="window.print()"><i class="fas fa-print"></i> Print SF7</button>
            </div>

            <!-- TABLE 1 -->
            <div class="sscroll" style="padding-bottom:0;">
                <div class="t1wrap">

                    <!-- ══ Header table ══ -->
                    <!--
                        Structure (matches reference image exactly):
                        Row1: [Logo rowspan=4] | [Title colspan=5 — no bottom border]
                        Row2:                  | [Subtitle colspan=5 — no top border]
                        Row3:                  | [SchoolID] | [Region] | [Division] | [District+SchoolYear rowspan=2 — inner table]
                        Row4:                  | [SchoolName label] | [SchoolName value colspan=3]
                    -->
                    <table class="t1htable" style="border-collapse:collapse; width:100%; table-layout:fixed;">
                        <colgroup>
                            <col style="width:90px"> <!-- logo -->
                            <col style="width:140px"> <!-- School ID / School Name label -->
                            <col style="width:90px"> <!-- Region / School Name value start -->
                            <col style="width:200px"> <!-- Division / School Name value cont -->
                            <col style="width:130px"> <!-- School Name value cont -->
                            <col style="width:150px"> <!-- District + School Year (right block) -->
                        </colgroup>

                        <!-- Row 1: Logo + Title -->
                        <tr>
                            <td class="t1logo" rowspan="4" style="border-right:1px solid #111; text-align:center; vertical-align:middle;">
                                <img src="../assets/img/DepED logo circle.png" alt="DepEd"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                <div class="t1lph" style="display:none;">DepEd<br>Logo</div>
                            </td>
                            <td colspan="5" class="t1title" style="border-bottom:none;">
                                School Form 7 (SF7) School Personnel Assignment List and Basic Profile
                            </td>
                        </tr>

                        <!-- Row 2: Subtitle -->
                        <tr>
                            <td colspan="5" class="t1sub" style="border-top:none;">
                                (This replaces Form 12-Monthly Status Report for Teachers, Form 19-Assignment List,
                                Form 29-Teacher Program and Form 31-Summary Information of Teachers)
                            </td>
                        </tr>

                        <!-- Row 3: School ID | Region | Division | [District + School Year right block rowspan=2] -->
                        <tr>
                            <td class="t1field">
                                <span class="t1lbl">School ID</span>
                                <span class="t1val">306332</span>
                            </td>
                            <td class="t1field" style="border-left:none;">
                                <span class="t1lbl">Region</span>
                                <span class="t1val">V</span>
                            </td>
                            <td class="t1field" colspan="2" style="border-left:none;">
                                <span class="t1lbl">Division</span>
                                <span class="t1val">LEGAZPI CITY</span>
                            </td>
                            <!-- Right block: District on top, School Year below, shares rowspan=2 with School Name row -->
                            <td rowspan="2" style="padding:0; vertical-align:top; border-left:none;">
                                <table style="width:100%; height:100%; border-collapse:collapse; table-layout:fixed;">
                                    <tr>
                                        <td class="t1field" style="border:none; border-bottom:1px solid #111; width:50%;">
                                            <span class="t1lbl">District</span>
                                            <span class="t1val">9A</span>
                                        </td>
                                        <td class="t1field" style="border:none; border-bottom:1px solid #111; border-left:1px solid #111;">
                                            <span class="t1lbl">School Year</span>
                                            <span class="t1val">2025-2026</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="border:none; height:34px;"></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Row 4: School Name label + value -->
                        <tr>
                            <td class="t1label-only" style="border-top:none; border-right:none;">
                                School Name
                            </td>
                            <td colspan="3" style="padding:4px 7px; border-top:none; border-left:none;">
                                <span class="t1val t1val-bold">BUYOAN NATIONAL HIGH SCHOOL</span>
                            </td>
                        </tr>

                    </table><!-- /.t1htable -->

                    <!-- ── Data table: column headers + data rows ── -->
                    <table class="t1">
                        <colgroup>
                            <col style="width:175px">
                            <col style="width:80px">
                            <col style="width:175px">
                            <col style="width:80px">
                            <col style="width:200px">
                            <col style="width:155px">
                            <col style="width:110px">
                            <col style="width:80px">
                            <col style="width:80px">
                            <col class="no-print" style="width:82px">
                        </colgroup>

                        <!-- Row 1: Main section headers (A), (B), (C) -->
                        <tr style="border-bottom: 2px solid #111;">
                            <td colspan="2" class="t1ch" style="border-right: 2px solid #111; background: #f0f0f0; font-weight: 800; font-size: 11px; padding: 8px 4px;">(A) Nationally-Funded Teaching &amp; Teaching Related Items</td>
                            <td colspan="2" class="t1ch" style="border-right: 2px solid #111; background: #f0f0f0; font-weight: 800; font-size: 11px; padding: 8px 4px;">(B) Nationally-Funded Non Teaching Items</td>
                            <td colspan="5" class="t1ch" style="background: #f0f0f0; font-weight: 800; font-size: 11px; padding: 8px 4px;">(C) Other Appointments and Funding Source</td>
                        </tr>

                        <!-- Row 2: All column headers at SAME HEIGHT (70px) -->
                        <tr>
                            <td class="t1ch t1ch-tall" rowspan="2">Title of Plantilla Position<span class="t1chlt">(as it appears in the appointment document/PSIPOP)</span></td>
                            <td class="t1ch t1ch-tall" rowspan="2">Number of<br>Incumbent</td>
                            <td class="t1ch t1ch-tall" rowspan="2">Title of Plantilla Position<span class="t1chlt">(as it appears in the appointment document/PSIPOP)</span></td>
                            <td class="t1ch t1ch-tall" rowspan="2">Number of<br>Incumbent</td>
                            <td class="t1ch t1ch-tall" rowspan="2">Title of Plantilla Position<span class="t1chlt">(as it appears in the contract/document Teacher, Clerk, Security, Guard, Driver, Etc.)</span></td>
                            <td class="t1ch t1ch-tall" rowspan="2">Appointment<span class="t1chlt">(Contractual, Substitute, Volunteer, others specify)</span></td>
                            <td class="t1ch t1ch-tall" rowspan="2">Fund Source<span class="t1chlt">(SEF, PTA, NGO's, etc.)</span></td>
                            <td class="t1ch-number-header" colspan="2" style="border-right: none;">Number of incumbent</td>
                            <td class="t1ch t1ch-tall no-print" rowspan="2" style="border-left: none;">Actions</td>
                        </tr>

                        <!-- Row 3: Only Teaching/Non-Teaching sub-headers (bottom section) -->
                        <tr>
                            <td class="t1ch-teaching">Teaching</td>
                            <td class="t1ch-teaching">Non-<br>Teaching</td>
                            <td class="no-print"></td>
                        </tr>

                        <?php
                        $ntList  = array_values($nonTeaching);
                        $aPosArr = array_keys($t1Pos);
                        $maxR    = max(count($t1Pos), count($ntList), 1);
                        for ($ri = 0; $ri < $maxR; $ri++):
                            $aT = $aPosArr[$ri] ?? '';
                            $aC = $aT ? $t1Pos[$aT] : '';
                            $nt = $ntList[$ri] ?? null;
                        ?>
                            <tr class="t1dr">
                                <td class="t1pos"><?= h($aT) ?></td>
                                <td class="t1num"><?= $aC ?></td>
                                <td class="t1pos"><?= $nt ? h($nt['nt_position'] ?: $nt['career_level']) : '' ?></td>
                                <td class="t1num"><?= $nt ? 1 : '' ?></td>
                                <td class="t1pos"></td>
                                <td class="t1appt"></td>
                                <td class="t1fund"></td>
                                <td class="t1num"><?= $aC && !in_array($aT, array_column($nonTeaching, 'career_level')) ? $aC : '' ?></td>
                                <td class="t1num"><?= $nt ? 1 : '' ?></td>
                                <td class="no-print"></td>
                            </tr>
                        <?php endfor; ?>
                        <?php for ($x = 0; $x < 3; $x++): ?>
                            <tr class="t1dr">
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="no-print"></td>
                            </tr>
                        <?php endfor; ?>
                        <tr class="t1tot">
                            <td style="text-align:center;">TOTAL</td>
                            <td class="t1num"><?= array_sum($t1Pos) ?></td>
                            <td></td>
                            <td class="t1num">0</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="t1num"><?= $teachingCnt ?></td>
                            <td class="t1num"><?= $ntCnt ?></td>
                            <td class="no-print"></td>
                        </tr>
                    </table>
                </div><!-- /.t1wrap -->
            </div>

            <!-- ══ TABLE 2 — SF7 Personnel Assignment List ══ -->
            <div class="sscroll" style="padding-top:8px;padding-bottom:20px;">
                <table class="sf7t">
                    <colgroup>
                        <!-- Identity (9 cols) -->
                        <col style="width:6.5%"> <!-- 1  EmpNo -->
                        <col style="width:9.5%"> <!-- 2  Name -->
                        <col style="width:3.5%"> <!-- 3  Sex -->
                        <col style="width:4%"> <!-- 4  Fund -->
                        <col style="width:8%"> <!-- 5  Position -->
                        <col style="width:6%"> <!-- 6  Appt -->
                        <col style="width:5%"> <!-- 7  Educ -->
                        <col style="width:6.5%"> <!-- 8  Major -->
                        <col style="width:4.5%"> <!-- 9  Advisory -->
                        <!-- Schedule (12 cols) -->
                        <col style="width:7%"> <!-- 10 Subject -->
                        <col style="width:3%"> <!-- 11 Gr -->
                        <col style="width:4.5%"> <!-- 12 Section -->
                        <col style="width:2.5%"> <!-- 13 M -->
                        <col style="width:2.5%"> <!-- 14 T -->
                        <col style="width:2.5%"> <!-- 15 W -->
                        <col style="width:2.5%"> <!-- 16 TH -->
                        <col style="width:2.5%"> <!-- 17 F -->
                        <col style="width:5%"> <!-- 18 TimeStart -->
                        <col style="width:5%"> <!-- 19 TimeEnd -->
                        <col style="width:4.5%"> <!-- 20 Mins -->
                        <col style="width:3.5%"> <!-- 21 ClassCount -->
                        <!-- Actions (1 col, screen only) -->
                        <col class="no-print" style="width:5%"> <!-- 22 -->
                    </colgroup>

                    <!-- ═══ HEADER ═══
                         Row 1 : 9 identity (rowspan=2) + "Daily Program" (colspan=12) + Actions (rowspan=2)
                         Row 2 : 12 schedule sub-headers
                         Total visible = 9 + 12 + 1 = 22 cols
                    ═══ -->

                    <?php
                    // ═══════════════════════════════════════════════════════════
                    //  SHARED HELPERS
                    //  All three rendering paths (principal, teaching, non-teaching)
                    //  call the same functions so column counts never drift.
                    // ═══════════════════════════════════════════════════════════

                    /**
                     * schedCells() — outputs exactly 12 <td> for one schedule row.
                     * Cols: subject, grade, section, M, T, W, TH, F,
                     *       timeStart, timeEnd, minutes, classCount(empty per row).
                     */
                    function schedCells(array $s): string
                    {
                        $day = function (string $k, string $lbl) use ($s): string {
                            return '<td class="c-day">'
                                . (!empty($s[$k]) ? '<strong>' . $lbl . '</strong>' : '')
                                . '</td>';
                        };
                        return
                            '<td class="c-sb">'  . h($s['subject'] ?? '')  . '</td>'
                            . '<td class="c-gr">'  . h($s['grade']   ?? '')  . '</td>'
                            . '<td class="c-sec">' . h($s['section'] ?? '')  . '</td>'
                            . $day('day_mon', 'M') . $day('day_tue', 'T') . $day('day_wed', 'W')
                            . $day('day_thu', 'TH') . $day('day_fri', 'F')
                            . '<td class="c-tm">' . fmtTime($s['time_start'] ?? '') . '</td>'
                            . '<td class="c-tm">' . fmtTime($s['time_end']   ?? '') . '</td>'
                            . '<td class="c-mn">' . ($s['minutes'] ? (int)$s['minutes'] : '') . '</td>'
                            . '<td class="c-mn"></td>';  // class-count blank per row; total in ptot
                    }

                    /** emptySchedCells() — 12 empty <td> when teacher has no schedules yet. */
                    function emptySchedCells(): string
                    {
                        return
                            '<td class="c-sb"></td><td class="c-gr"></td><td class="c-sec"></td>'
                            . '<td class="c-day"></td><td class="c-day"></td><td class="c-day"></td>'
                            . '<td class="c-day"></td><td class="c-day"></td>'
                            . '<td class="c-tm"></td><td class="c-tm"></td>'
                            . '<td class="c-mn"></td><td class="c-mn"></td>';
                    }

                    /**
                     * totalsRow() — exactly 22 <td>.
                     * Layout:
                     *   cols 1-9  : empty (identity covered by rowspan above)
                     *   cols 10-17: empty (subject→F)
                     *   col  18   : empty (timeStart)
                     *   col  19   : "Total" label (timeEnd position, matches DOCX col 20)
                     *   col  20   : total minutes
                     *   col  21   : class count = minutes ÷ 5  (DOCX: 1590→318, 1400→280)
                     *   col  22   : no-print actions placeholder
                     */
                    function totalsRow(int $mins): string
                    {
                        $cc = $mins ? (int)round($mins / 5) : '';
                        return
                            '<tr class="ptot">'
                            // 9 identity cols (empty)
                            . '<td class="c-id"></td><td class="c-nm"></td><td class="c-sx"></td>'
                            . '<td class="c-fd"></td><td class="c-pos"></td><td class="c-ap"></td>'
                            . '<td class="c-ed"></td><td class="c-mj"></td><td class="c-adv"></td>'
                            // subject, grade, section, M-F (8 empty)
                            . '<td class="c-sb"></td><td class="c-gr"></td><td class="c-sec"></td>'
                            . '<td class="c-day"></td><td class="c-day"></td><td class="c-day"></td>'
                            . '<td class="c-day"></td><td class="c-day"></td>'
                            // timeStart (empty)
                            . '<td class="c-tm"></td>'
                            // timeEnd → "Total" label
                            . '<td class="c-tm" style="text-align:right;font-weight:800;font-size:7px;padding-right:3px;">Total</td>'
                            // minutes + class count
                            . '<td class="c-mn" style="font-weight:800;">' . ($mins ?: '') . '</td>'
                            . '<td class="c-mn" style="font-weight:800;">' . $cc . '</td>'
                            // actions placeholder
                            . '<td class="no-print"></td>'
                            . '</tr>';
                    }

                    /**
                     * personBlock() — renders all <tr> rows for one person.
                     *
                     * @param string $idCells  9 identity <td rowspan="$cnt"> cells as HTML
                     * @param string $actCell  actions <td rowspan="$cnt"> as HTML
                     * @param array  $scheds   schedule rows from teacher_schedules
                     * @return string          complete HTML for this person's block
                     */
                    function personBlock(string $idCells, string $actCell, array $scheds): string
                    {
                        $cnt  = max(1, count($scheds));
                        $mins = (int)array_sum(array_column($scheds, 'minutes'));
                        $html = '';

                        // ── First row: identity (rowspan) + first schedule + actions (rowspan) ──
                        $html .= '<tr class="pfirst">';
                        $html .= $idCells;
                        $html .= !empty($scheds) ? schedCells($scheds[0]) : emptySchedCells();
                        $html .= $actCell;
                        $html .= '</tr>';

                        // ── Remaining schedule rows ──
                        for ($i = 1; $i < count($scheds); $i++) {
                            $html .= '<tr>' . schedCells($scheds[$i]) . '</tr>';
                        }

                        // ── Totals row ──
                        $html .= totalsRow($mins);

                        // ── Thin separator ──
                        $html .= '<tr style="height:3px;"><td colspan="22" style="border:none;background:#e5e7eb;padding:0;"></td></tr>';

                        return $html;
                    }
                    ?>

                    <?php
                    // ════ PRINCIPAL ════
                    if ($principal):
                        $p  = $principal;
                        $pn = $p['last_name']
                            ? strtoupper($p['last_name']) . ', ' . strtoupper($p['first_name'])
                            . ($p['middle_name'] ? ', ' . strtoupper($p['middle_name']) : '')
                            : strtoupper($p['teacher_name']);
                        $pEduc = $educMap[$p['teacher_qualification'] ?? ''] ?? strtoupper($p['teacher_qualification'] ?? '');
                        $pAppt = strtoupper($p['employment_status'] ?: 'REGULAR PERMANENT');
                        $pPos  = strtoupper($p['career_level']      ?: 'SCHOOL PRINCIPAL I');
                        $pMaj  = strtoupper($p['major'] ?: '');
                        $pAdv  = $p['advisory_class'] ?: 'N/A';
                        $pSch  = getScheds($conn, $p['teacher_id'], 'principal');
                        $pCnt  = max(1, count($pSch));

                        $pId =
                            '<td class="c-id"  rowspan="' . $pCnt . '">' . h($p['teacher_id']) . '</td>'
                            . '<td class="c-nm"  rowspan="' . $pCnt . '">' . h($pn)              . '</td>'
                            . '<td class="c-sx"  rowspan="' . $pCnt . '">' . h($p['gender'])     . '</td>'
                            . '<td class="c-fd"  rowspan="' . $pCnt . '">NATIONAL</td>'
                            . '<td class="c-pos" rowspan="' . $pCnt . '">' . h($pPos)            . '</td>'
                            . '<td class="c-ap"  rowspan="' . $pCnt . '">' . h($pAppt)           . '</td>'
                            . '<td class="c-ed"  rowspan="' . $pCnt . '">' . h($pEduc)           . '</td>'
                            . '<td class="c-mj"  rowspan="' . $pCnt . '">' . h($pMaj)            . '</td>'
                            . '<td class="c-adv" rowspan="' . $pCnt . '">' . h($pAdv)            . '</td>';

                        $pAct =
                            '<td class="c-act no-print" rowspan="' . $pCnt . '">'
                            . '<a href="#" class="ab e"'
                            . ' data-type="principal" data-rid="' . $p['id'] . '"'
                            . ' data-id="' . h($p['teacher_id']) . '"'
                            . ' data-lastname="' . h($p['last_name']) . '" data-firstname="' . h($p['first_name']) . '"'
                            . ' data-middlename="' . h($p['middle_name']) . '" data-gender="' . h($p['gender']) . '"'
                            . ' data-careerlevel="' . h($p['career_level']) . '" data-employment="' . h($p['employment_status']) . '"'
                            . ' data-qual="' . h($p['teacher_qualification']) . '" data-major="' . h($p['major']) . '"'
                            . ' data-email="' . h($p['teacher_email']) . '" data-contact="' . h($p['teacher_contact']) . '"'
                            . ' data-advisory="' . h($p['advisory_class']) . '"'
                            . ' data-tip="Edit Principal"><i class="fas fa-pen"></i></a>'
                            . '<a href="#" class="ab d"'
                            . ' data-type="principal" data-rid="' . $p['id'] . '"'
                            . ' data-tip="Remove"><i class="fas fa-trash"></i></a>'
                            . '</td>';

                        echo personBlock($pId, $pAct, $pSch);
                    endif;
                    ?>

                    <?php
                    // ════ TEACHING TEACHERS ════
                    foreach ($teaching as $t):
                        $tn = $t['last_name']
                            ? strtoupper($t['last_name']) . ', ' . strtoupper($t['first_name'])
                            . ($t['middle_name'] ? ', ' . strtoupper($t['middle_name']) : '')
                            : strtoupper($t['teacher_name']);
                        $tEduc = $educMap[$t['teacher_qualification'] ?? ''] ?? strtoupper($t['teacher_qualification'] ?? '');
                        $tAppt = strtoupper($t['employment_status'] ?: 'REGULAR PERMANENT');
                        $tPos  = strtoupper($t['career_level']      ?: 'TEACHER I');
                        $tMaj  = strtoupper($t['major'] ?: $t['teacher_subjects'] ?: '');
                        $tAdv  = $t['advisory_class'] ?: 'N/A';
                        $tFund = strtoupper($t['funding_source'] ?: 'NATIONAL');
                        $tSch  = getScheds($conn, $t['teacher_id'], 'teacher');
                        $tCnt  = max(1, count($tSch));

                        $tId =
                            '<td class="c-id"  rowspan="' . $tCnt . '">' . h($t['teacher_id']) . '</td>'
                            . '<td class="c-nm"  rowspan="' . $tCnt . '">' . h($tn)              . '</td>'
                            . '<td class="c-sx"  rowspan="' . $tCnt . '">' . h($t['gender'])     . '</td>'
                            . '<td class="c-fd"  rowspan="' . $tCnt . '">' . h($tFund)           . '</td>'
                            . '<td class="c-pos" rowspan="' . $tCnt . '">' . h($tPos)            . '</td>'
                            . '<td class="c-ap"  rowspan="' . $tCnt . '">' . h($tAppt)           . '</td>'
                            . '<td class="c-ed"  rowspan="' . $tCnt . '">' . h($tEduc)           . '</td>'
                            . '<td class="c-mj"  rowspan="' . $tCnt . '">' . h($tMaj)            . '</td>'
                            . '<td class="c-adv" rowspan="' . $tCnt . '">' . h($tAdv)            . '</td>';

                        $tAct =
                            '<td class="c-act no-print" rowspan="' . $tCnt . '">'
                            . '<a href="#" class="ab e"'
                            . ' data-type="teacher" data-rid="' . $t['id'] . '"'
                            . ' data-id="' . h($t['teacher_id']) . '"'
                            . ' data-lastname="' . h($t['last_name']) . '" data-firstname="' . h($t['first_name']) . '"'
                            . ' data-middlename="' . h($t['middle_name']) . '" data-gender="' . h($t['gender']) . '"'
                            . ' data-careerlevel="' . h($t['career_level']) . '" data-employment="' . h($t['employment_status']) . '"'
                            . ' data-qual="' . h($t['teacher_qualification']) . '" data-major="' . h($t['major']) . '"'
                            . ' data-subj="' . h($t['subject_assigned']) . '" data-gradesec="' . h($t['grade_section']) . '"'
                            . ' data-room="' . h($t['room_assignment']) . '"'
                            . ' data-club="' . h($t['club_role']) . '" data-advisory="' . h($t['advisory_class']) . '"'
                            . ' data-ntpos="' . h($t['nt_position']) . '" data-ntappt="' . h($t['nt_appt_type']) . '"'
                            . ' data-ntfund="' . h($t['nt_fund_source']) . '" data-category="' . h($t['category']) . '"'
                            . ' data-funding="' . h($t['funding_source']) . '"'
                            . ' data-email="' . h($t['teacher_email']) . '" data-contact="' . h($t['teacher_contact']) . '"'
                            . ' data-tip="Edit"><i class="fas fa-pen"></i></a>'
                            . '<a href="#" class="ab d"'
                            . ' data-type="teacher" data-rid="' . $t['id'] . '"'
                            . ' data-tip="Delete"><i class="fas fa-trash"></i></a>'
                            . '</td>';

                        echo personBlock($tId, $tAct, $tSch);
                    endforeach;
                    ?>

                    <?php if (!empty($nonTeaching)): ?>
                        <!-- ════ NON-TEACHING: ADMIN TASK ════ -->
                        <tr class="nt-hdr">
                            <td colspan="22" style="text-align:left;padding:3px 8px;font-size:7px;font-weight:800;text-transform:uppercase;background:#b8c5d6;border:0.75px solid #333;">
                                JOB ORDER / CONTRACT OF SERVICE PERSONNEL — ADMIN TASK
                            </td>
                        </tr>
                        <?php foreach ($nonTeaching as $nt):
                            $ntName = $nt['last_name']
                                ? strtoupper($nt['last_name']) . ', ' . strtoupper($nt['first_name'])
                                . ($nt['middle_name'] ? ', ' . strtoupper($nt['middle_name']) : '')
                                : strtoupper($nt['teacher_name']);
                            $ntSch = getScheds($conn, $nt['teacher_id'], 'teacher');
                            if (empty($ntSch)) {
                                $ntSch = [[
                                    'subject' => 'ADMIN TASK — General',
                                    'grade' => '',
                                    'section' => '',
                                    'day_mon' => 1,
                                    'day_tue' => 1,
                                    'day_wed' => 1,
                                    'day_thu' => 1,
                                    'day_fri' => 1,
                                    'time_start' => '',
                                    'time_end' => '',
                                    'minutes' => 0
                                ]];
                            }
                            $ntCnt = max(1, count($ntSch));

                            $ntId =
                                '<td class="c-id"  rowspan="' . $ntCnt . '">' . h($nt['teacher_id']) . '</td>'
                                . '<td class="c-nm"  rowspan="' . $ntCnt . '">' . h($ntName)           . '</td>'
                                . '<td class="c-sx"  rowspan="' . $ntCnt . '">' . h($nt['gender'])     . '</td>'
                                . '<td class="c-fd"  rowspan="' . $ntCnt . '">' . h($nt['nt_fund_source'] ?: $nt['funding_source']) . '</td>'
                                . '<td class="c-pos" rowspan="' . $ntCnt . '">' . h($nt['nt_position'] ?: $nt['career_level'])     . '</td>'
                                . '<td class="c-ap"  rowspan="' . $ntCnt . '">' . h($nt['nt_appt_type'] ?: $nt['employment_status']) . '</td>'
                                . '<td class="c-ed"  rowspan="' . $ntCnt . '">N/A</td>'
                                . '<td class="c-mj"  rowspan="' . $ntCnt . '">N/A</td>'
                                . '<td class="c-adv" rowspan="' . $ntCnt . '">N/A</td>';

                            $ntAct =
                                '<td class="c-act no-print" rowspan="' . $ntCnt . '">'
                                . '<a href="#" class="ab e"'
                                . ' data-type="teacher" data-rid="' . $nt['id'] . '"'
                                . ' data-id="' . h($nt['teacher_id']) . '"'
                                . ' data-lastname="' . h($nt['last_name']) . '" data-firstname="' . h($nt['first_name']) . '"'
                                . ' data-middlename="' . h($nt['middle_name']) . '" data-gender="' . h($nt['gender']) . '"'
                                . ' data-careerlevel="' . h($nt['career_level']) . '" data-employment="' . h($nt['employment_status']) . '"'
                                . ' data-qual="' . h($nt['teacher_qualification']) . '" data-major="' . h($nt['major']) . '"'
                                . ' data-ntpos="' . h($nt['nt_position']) . '" data-ntappt="' . h($nt['nt_appt_type']) . '"'
                                . ' data-ntfund="' . h($nt['nt_fund_source']) . '" data-category="Non-Teaching"'
                                . ' data-funding="' . h($nt['funding_source']) . '"'
                                . ' data-tip="Edit"><i class="fas fa-pen"></i></a>'
                                . '<a href="#" class="ab d"'
                                . ' data-type="teacher" data-rid="' . $nt['id'] . '"'
                                . ' data-tip="Delete"><i class="fas fa-trash"></i></a>'
                                . '</td>';

                            echo personBlock($ntId, $ntAct, $ntSch);
                        endforeach; ?>
                    <?php endif; ?>

                    <!-- ════ GUIDELINES & SIGNATURE ════ -->
                    <tr>
                        <td colspan="14" style="border:1px solid #555;vertical-align:top;padding:6px 8px;font-size:7.5px;color:#444;line-height:1.6;">
                            <strong>GUIDELINES:</strong><br>
                            1. This form shall be accomplished at the beginning of the school year by the school head. In case of movement of teachers and other personnel during the school year, an updated School Form 7 must be submitted to the Division Office.<br>
                            2. All school personnel, regardless of position/nature of appointment should be included in this form and should be listed from the highest rank down to the lowest.<br>
                            3. Please reflect subjects being taught and if teacher handling advisory class or Ancillary Assignment. Other administrative duties must also be reported.<br>
                            4. Daily Program Column is for teaching personnel only.<br>
                            <br><em>Updated as of: <?= date('m/d/Y') ?></em>
                        </td>
                        <td colspan="7" style="border:1px solid #555;text-align:center;vertical-align:bottom;padding:6px;">
                            <div style="border-top:1px solid #333;width:80%;margin:40px auto 0;padding-top:4px;font-weight:800;font-size:8.5px;">
                                <?php
                                if ($principal) {
                                    $sn = $principal['first_name']
                                        ? strtoupper($principal['first_name']) . ' ' . strtoupper($principal['last_name'])
                                        : strtoupper($principal['teacher_name']);
                                    echo h($sn);
                                } else {
                                    echo 'SCHOOL PRINCIPAL';
                                }
                                ?><br>
                                <span style="font-weight:400;font-size:7.5px;">(Signature of School Head over Printed Name)</span>
                            </div>
                        </td>
                        <td class="no-print" style="border:1px solid #555;"></td>
                    </tr>

                </table>
            </div>
        </div><!-- /.sf7w -->


        <!-- ══ PRINCIPAL HISTORY ══ -->
        <?php if (!empty($principalHistory)): ?>
            <div style="font-size:14px;font-weight:700;color:#92400e;display:flex;align-items:center;gap:8px;margin-bottom:8px;"><i class="fas fa-history"></i> Principal Tenure History</div>
            <?php foreach ($principalHistory as $ph): ?>
                <div class="hw">
                    <div class="hh" onclick="this.nextElementSibling.classList.toggle('open')">
                        <i class="fas fa-crown" style="color:#d97706;"></i>
                        <h4><?= h($ph['teacher_name']) ?> — <?= h($ph['career_level'] ?: 'Principal') ?></h4>
                        <span style="font-size:12px;color:#b45309;margin-right:10px;"><?= $ph['assigned_date'] ? 'Served: ' . date('M Y', strtotime($ph['assigned_date'])) . ' – ' . date('M Y', strtotime($ph['replaced_date'])) : '' ?></span>
                        <i class="fas fa-chevron-down" style="color:#d97706;"></i>
                    </div>
                    <div class="hb">
                        <div style="font-size:12px;color:#6b7280;margin-bottom:10px;">
                            <b>ID:</b> <?= h($ph['teacher_id']) ?> &nbsp;|&nbsp; <b>Gender:</b> <?= h($ph['gender']) ?> &nbsp;|&nbsp; <b>Replaced:</b> <?= $ph['replaced_date'] ? date('F j, Y', strtotime($ph['replaced_date'])) : '—' ?>
                        </div>
                        <?php
                        $th = $conn->query("SELECT * FROM teacher_history WHERE principal_hist_id=" . (int)$ph['id'] . " ORDER BY id ASC");
                        $thr = [];
                        while ($r = $th->fetch_assoc()) {
                            decRow($r);
                            $thr[] = $r;
                        }
                        if (!empty($thr)):
                        ?>
                            <div style="overflow-x:auto;">
                                <table class="htbl">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Gender</th>
                                            <th>Position</th>
                                            <th>Qualification</th>
                                            <th>Major</th>
                                            <th>Subject</th>
                                            <th>Grade/Sec</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($thr as $i => $row): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= h($row['teacher_id']) ?></td>
                                                <td><b><?= h($row['teacher_name']) ?></b></td>
                                                <td><?= h($row['gender']) ?></td>
                                                <td><?= h($row['career_level']) ?></td>
                                                <td><?= h($row['teacher_qualification']) ?></td>
                                                <td><?= h($row['major']) ?></td>
                                                <td><?= h($row['subject_assigned']) ?></td>
                                                <td><?= h($row['grade_section']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="font-size:12px;color:#9ca3af;font-style:italic;">No teacher records for this tenure.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /.page-content -->

    <!-- Delete form -->
    <form id="delf" method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" id="drid" name="delete_record_id">
        <input type="hidden" id="dtype" name="delete_type" value="teacher">
    </form>

    <!-- ══════════════════════════════════════════════════════════
     MODAL — Redesigned Step Wizard
     ══════════════════════════════════════════════════════════ -->
    <div id="pmod" class="modal">
        <div class="mc">
            <!-- Header -->
            <div class="mhd grn" id="mhd">
                <div class="mhd-top">
                    <div class="mhd-title-wrap">
                        <div class="mhd-icon"><i id="mhd-ico" class="fas fa-user-plus"></i></div>
                        <div>
                            <h3 id="mtit">Add Personnel</h3>
                            <p id="msubt">All fields are optional — fill in only what is available</p>
                        </div>
                    </div>
                    <button class="xbtn" id="xbtn">&times;</button>
                </div>
                <!-- Step Progress -->
                <div class="step-bar">
                    <div class="step-item active" data-step="1" onclick="goStep(1)">
                        <div class="step-dot">1</div>
                        <span class="step-label">Personal</span>
                    </div>
                    <div class="step-item" data-step="2" onclick="goStep(2)">
                        <div class="step-dot">2</div>
                        <span class="step-label">Position</span>
                    </div>
                    <div class="step-item" data-step="3" onclick="goStep(3)">
                        <div class="step-dot">3</div>
                        <span class="step-label">Schedule</span>
                    </div>
                    <div class="step-item" data-step="4" onclick="goStep(4)">
                        <div class="step-dot">4</div>
                        <span class="step-label">Review</span>
                    </div>
                </div>
            </div>

            <!-- Scrollable Body -->
            <div class="msc">
                <form id="pform" class="mb" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="fact" value="add">
                    <input type="hidden" name="edit_record_id" id="frid">
                    <input type="hidden" name="edit_type" id="frtype" value="teacher">
                    <input type="hidden" name="teacher_name" id="ffull">

                    <!-- Encryption notice -->
                    <div style="display:flex;align-items:center;gap:10px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:10px;padding:10px 16px;margin-bottom:14px;font-size:12px;color:#15803d;">
                        <i class="fas fa-lock" style="font-size:14px;flex-shrink:0;"></i>
                        <span><strong>End-to-end encrypted.</strong> Personal identifiers (name, ID, email, contact) are encrypted with AES-256-CBC before being stored in the database. All fields are optional.</span>
                    </div>

                    <!-- ── STEP 1: Personal Information ── -->
                    <div class="step-panel active" id="sp1">

                        <!-- Principal Toggle -->
                        <div id="ptw" class="ptog" style="display:none;">
                            <label class="tg"><input type="checkbox" id="fisp" name="is_principal"><span class="tgsl"></span></label>
                            <div>
                                <div style="font-size:13px;font-weight:700;color:#92400e;"><i class="fas fa-crown" style="color:#d97706;margin-right:5px;"></i>Designate as School Principal</div>
                                <div style="font-size:11px;color:#b45309;margin-top:2px;">This will archive the current principal and snapshot all teachers.</div>
                            </div>
                        </div>

                        <div class="fsec">
                            <div class="fst"><i class="fas fa-user-circle"></i> Full Name</div>
                            <div class="fr c3">
                                <div class="fg">
                                    <label><i class="fas fa-font"></i> Last Name</label>
                                    <input type="text" id="fln" name="last_name" placeholder="DELA CRUZ">
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-font"></i> First Name</label>
                                    <input type="text" id="ffn" name="first_name" placeholder="JUAN">
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-font"></i> Middle Name</label>
                                    <input type="text" id="fmn" name="middle_name" placeholder="SANTOS">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fg">
                                    <label><i class="fas fa-id-badge"></i> Employee Number</label>
                                    <input type="text" id="feid" name="teacher_id" placeholder="e.g. 306332001">
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-venus-mars"></i> Sex / Gender</label>
                                    <select id="fgnd" name="gender">
                                        <option value="">— Select Gender —</option>
                                        <option value="MALE">MALE</option>
                                        <option value="FEMALE">FEMALE</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="fsec">
                            <div class="fst"><i class="fas fa-address-book"></i> Contact Information</div>
                            <div class="fr">
                                <div class="fg">
                                    <label><i class="fas fa-envelope"></i> Email Address</label>
                                    <input type="email" id="femail" name="teacher_email" placeholder="teacher@deped.gov.ph">
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-phone"></i> Contact Number</label>
                                    <input type="tel" id="fcon" name="teacher_contact" placeholder="+63 9XX XXX XXXX">
                                </div>
                            </div>
                        </div>

                        <div class="fsec">
                            <div class="fst"><i class="fas fa-camera"></i> Profile Photo</div>
                            <div class="photo-zone" onclick="this.querySelector('input').click()">
                                <input type="file" name="teacher_image" accept="image/*" style="display:none;" onchange="updatePhotoLabel(this)">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p id="photo-lbl">Click or drag to upload a photo</p>
                                <span>JPG, PNG up to 5MB</span>
                            </div>
                        </div>
                    </div>

                    <!-- ── STEP 2: Position Details ── -->
                    <div class="step-panel" id="sp2">
                        <div class="fsec">
                            <div class="fst"><i class="fas fa-briefcase"></i> Plantilla Position</div>
                            <div class="fr">
                                <div class="fg">
                                    <label><i class="fas fa-medal"></i> Title of Plantilla Position</label>
                                    <select id="fcl" name="career_level">
                                        <option value="">— Select Position —</option>
                                        <optgroup label="School Head">
                                            <option value="School Principal I">School Principal I</option>
                                            <option value="School Principal II">School Principal II</option>
                                            <option value="School Principal III">School Principal III</option>
                                        </optgroup>
                                        <optgroup label="Master Teacher">
                                            <option value="Master Teacher IV">Master Teacher IV</option>
                                            <option value="Master Teacher III">Master Teacher III</option>
                                            <option value="Master Teacher II">Master Teacher II</option>
                                            <option value="Master Teacher I">Master Teacher I</option>
                                        </optgroup>
                                        <optgroup label="Head Teacher">
                                            <option value="Head Teacher III">Head Teacher III</option>
                                            <option value="Head Teacher II">Head Teacher II</option>
                                            <option value="Head Teacher I">Head Teacher I</option>
                                        </optgroup>
                                        <optgroup label="Teacher">
                                            <option value="Teacher III">Teacher III</option>
                                            <option value="Teacher II">Teacher II</option>
                                            <option value="Teacher I">Teacher I</option>
                                        </optgroup>
                                        <optgroup label="Non-Teaching">
                                            <option value="Watchman">Watchman</option>
                                            <option value="Utility Worker">Utility Worker</option>
                                            <option value="Driver">Driver</option>
                                            <option value="Clerk">Clerk</option>
                                            <option value="Security Guard">Security Guard</option>
                                            <option value="Other">Other Non-Teaching</option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-file-contract"></i> Appointment Status</label>
                                    <select id="fappt" name="employment_status">
                                        <option value="">— Select Status —</option>
                                        <option value="REGULAR PERMANENT">REGULAR PERMANENT</option>
                                        <option value="Contractual">Contractual</option>
                                        <option value="Substitute">Substitute</option>
                                        <option value="Volunteer">Volunteer</option>
                                        <option value="Job Order/Contract of Service">Job Order / Contract of Service</option>
                                    </select>
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fg">
                                    <label><i class="fas fa-graduation-cap"></i> Highest Educational Qualification</label>
                                    <select id="fqual" name="teacher_qualification">
                                        <option value="">— Select Qualification —</option>
                                        <option value="bachelor">Bachelor's Degree</option>
                                        <option value="bachelors-units">Bachelor's w/ Post-Grad Units</option>
                                        <option value="post-graduate">Master's (Unit)</option>
                                        <option value="masteral">Master's Degree</option>
                                        <option value="doctoral">Doctoral Degree</option>
                                        <option value="lac">LAC</option>
                                        <option value="k12">K-12</option>
                                        <option value="others">Others</option>
                                    </select>
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-book-open"></i> Major / Specialization</label>
                                    <input type="text" id="fmaj" name="major" placeholder="e.g. ENGLISH, MATH, TLE">
                                </div>
                            </div>
                            <div class="fr">
                                <div class="fg">
                                    <label><i class="fas fa-wallet"></i> Funding Source</label>
                                    <select id="ffund" name="funding_source">
                                        <option value="NATIONAL">NATIONAL</option>
                                        <option value="SEF">SEF</option>
                                        <option value="PTA">PTA</option>
                                        <option value="MOOE">MOOE</option>
                                        <option value="NGO">NGO</option>
                                    </select>
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-tag"></i> Category</label>
                                    <select id="fcat" name="category">
                                        <option value="Teaching">Teaching</option>
                                        <option value="Non-Teaching">Non-Teaching</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Ancillary — Teaching only -->
                        <div class="fsec" id="ts">
                            <div class="fst"><i class="fas fa-star"></i> Ancillary / Additional Role</div>
                            <div class="fr">
                                <div class="fg">
                                    <label><i class="fas fa-users"></i> Club / Coordinator Role</label>
                                    <input type="text" id="fclub" name="club_role" placeholder="e.g. SSG Adviser, Math Club Coordinator">
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-chalkboard-teacher"></i> Advisory Class</label>
                                    <input type="text" id="fadv" name="advisory_class" placeholder="e.g. Grade 8-Rosal">
                                </div>
                            </div>
                        </div>

                        <!-- Non-Teaching extra -->
                        <div class="fsec" id="nts" style="display:none;">
                            <div class="fst"><i class="fas fa-hard-hat"></i> Non-Teaching Appointment</div>
                            <div class="fr c3">
                                <div class="fg">
                                    <label><i class="fas fa-id-card"></i> Position / Role</label>
                                    <input type="text" id="fntpos" name="nt_position" placeholder="e.g. Watchman, Utility Worker">
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-file-alt"></i> Appointment Type</label>
                                    <select id="fntappt" name="nt_appt_type">
                                        <option value="">— Select —</option>
                                        <option value="Job Order/Contract of Service">Job Order / Contract of Service</option>
                                        <option value="Contractual">Contractual</option>
                                        <option value="Volunteer">Volunteer</option>
                                    </select>
                                </div>
                                <div class="fg">
                                    <label><i class="fas fa-coins"></i> Fund Source</label>
                                    <select id="fntfund" name="nt_fund_source">
                                        <option value="">— Select —</option>
                                        <option value="SEF">SEF</option>
                                        <option value="PTA">PTA</option>
                                        <option value="MOOE">MOOE</option>
                                        <option value="SEF, MOOE">SEF, MOOE</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── STEP 3: Schedule ── -->
                    <div class="step-panel" id="sp3">
                        <div class="fsec" id="ss">
                            <div class="fst"><i class="fas fa-calendar-week"></i> Daily Program / Schedule</div>
                            <p style="font-size:12px;color:#64748b;margin:0 0 12px;line-height:1.6;">Add one row per subject or assignment. Each row will appear as a separate line in the SF7 table. Leave empty if not applicable.</p>
                            <div style="overflow-x:auto;">
                                <table class="stbl" id="stbl">
                                    <colgroup>
                                        <col style="width:130px"> <!-- Subject -->
                                        <col style="width:38px"> <!-- Gr -->
                                        <col style="width:90px"> <!-- Section -->
                                        <col style="width:28px"> <!-- M -->
                                        <col style="width:28px"> <!-- T -->
                                        <col style="width:28px"> <!-- W -->
                                        <col style="width:30px"> <!-- TH -->
                                        <col style="width:28px"> <!-- F -->
                                        <col style="width:32px"> <!-- SAT -->
                                        <col style="width:32px"> <!-- SUN -->
                                        <col style="width:100px"> <!-- Time Start -->
                                        <col style="width:100px"> <!-- Time End -->
                                        <col style="width:56px"> <!-- Mins -->
                                        <col style="width:36px"> <!-- Del -->
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th style="text-align:left;padding-left:8px;">Subject / Role</th>
                                            <th>Gr</th>
                                            <th>Section</th>
                                            <th>M</th>
                                            <th>T</th>
                                            <th>W</th>
                                            <th>TH</th>
                                            <th>F</th>
                                            <th>SAT</th>
                                            <th>SUN</th>
                                            <th>Time Start</th>
                                            <th>Time End</th>
                                            <th>Mins</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="srows"></tbody>
                                </table>
                            </div>
                            <button type="button" class="sadd" id="sadd"><i class="fas fa-plus"></i> Add Row</button>
                        </div>
                        <div class="fsec" id="nts-sched" style="display:none;">
                            <div style="text-align:center;padding:24px;color:#94a3b8;">
                                <i class="fas fa-info-circle" style="font-size:28px;display:block;margin-bottom:8px;"></i>
                                <p style="margin:0;font-size:13px;">Schedule builder is for <strong>Teaching</strong> personnel only.</p>
                            </div>
                        </div>
                    </div>

                    <!-- ── STEP 4: Review ── -->
                    <div class="step-panel" id="sp4">
                        <div class="fsec" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-color:#bbf7d0;">
                            <div class="fst" style="color:#16a34a;border-color:#bbf7d0;"><i class="fas fa-check-circle"></i> Ready to Submit</div>
                            <p style="font-size:13px;color:#15803d;margin:0 0 14px;">Please review the summary below before saving. You can go back to any step to make changes.</p>
                            <div id="review-box" style="background:#fff;border-radius:10px;border:1px solid #bbf7d0;padding:16px;font-size:13px;line-height:1.9;color:#374151;">
                                <div id="rv-name" style="font-size:15px;font-weight:800;color:#0f172a;margin-bottom:8px;"></div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;" id="rv-fields"></div>
                            </div>
                        </div>
                    </div>

                </form>
            </div>

            <!-- Footer -->
            <div class="mft">
                <div class="mft-left">
                    <button type="button" class="cbtn" id="cbtn">Cancel</button>
                </div>
                <div class="mft-right">
                    <span class="step-indicator" id="step-ind">Step 1 of 4</span>
                    <button type="button" class="nbtn" id="prevbtn" style="display:none;" onclick="prevStep()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="button" class="nbtn" id="nextbtn" onclick="nextStep()">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="button" class="sbtn grn" id="sbtn" style="display:none;" onclick="document.getElementById('pform').submit()">
                        <i class="fas fa-save"></i> <span id="slbl">Save Personnel</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ══ Schedule builder ══════════════════════════════════════════════════
        var sidx = 0;

        // ── Auto-calculate minutes from time inputs ──────────────────────────
        function calcRowMins(timeInput) {
            var row = timeInput.closest('tr');
            if (!row) return;
            var tsEl = row.querySelector('input[name="sched_tstart[]"]');
            var teEl = row.querySelector('input[name="sched_tend[]"]');
            var mnEl = row.querySelector('input[name="sched_mins[]"]');
            if (!tsEl || !teEl || !mnEl) return;
            var ts = tsEl.value,
                te = teEl.value;
            if (ts && te) {
                var sp = ts.split(':').map(Number);
                var ep = te.split(':').map(Number);
                var diff = (ep[0] * 60 + ep[1]) - (sp[0] * 60 + sp[1]);
                mnEl.value = diff > 0 ? diff : 0;
            } else {
                mnEl.value = '';
            }
        }

        function mkRow(d) {
            d = d || {};
            var i = sidx++;
            var c = function(v) {
                return v ? 'checked' : ''
            };
            return '<tr>' +
                '<td style="padding-left:6px;"><input type="text" name="sched_subject[]" value="' + hq(d.subject || '') + '" placeholder="e.g. ENGLISH"></td>' +
                '<td><input type="text" name="sched_grade[]" value="' + hq(d.grade || '') + '" style="text-align:center;" placeholder="9"></td>' +
                '<td><input type="text" name="sched_section[]" value="' + hq(d.section || '') + '" placeholder="e.g. Mendel"></td>' +
                '<td style="text-align:center;"><input type="checkbox" name="sched_mon[]" value="1" ' + c(d.day_mon) + '></td>' +
                '<td style="text-align:center;"><input type="checkbox" name="sched_tue[]" value="1" ' + c(d.day_tue) + '></td>' +
                '<td style="text-align:center;"><input type="checkbox" name="sched_wed[]" value="1" ' + c(d.day_wed) + '></td>' +
                '<td style="text-align:center;"><input type="checkbox" name="sched_thu[]" value="1" ' + c(d.day_thu) + '></td>' +
                '<td style="text-align:center;"><input type="checkbox" name="sched_fri[]" value="1" ' + c(d.day_fri) + '></td>' +
                '<td style="text-align:center;"><input type="checkbox" name="sched_sat[]" value="1" ' + c(d.day_sat) + '></td>' +
                '<td style="text-align:center;"><input type="checkbox" name="sched_sun[]" value="1" ' + c(d.day_sun) + '></td>' +
                '<td><input type="time" name="sched_tstart[]" value="' + hq(t24(d.time_start || '')) + '" style="display:block;width:100%;" oninput="calcRowMins(this)" onchange="calcRowMins(this)"></td>' +
                '<td><input type="time" name="sched_tend[]"   value="' + hq(t24(d.time_end || '')) + '" style="display:block;width:100%;" oninput="calcRowMins(this)" onchange="calcRowMins(this)"></td>' +
                '<td><input type="number" name="sched_mins[]" value="' + hq(d.minutes || '') + '" min="0" readonly style="text-align:center;background:#f1f5f9;cursor:not-allowed;color:#475569;"></td>' +
                '<td style="text-align:center;"><button type="button" class="sdel" onclick="this.closest(\'tr\').remove()"><i class="fas fa-times"></i></button></td>' +
                '</tr>';
        }

        function hq(v) {
            return String(v || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        function t24(t) {
            if (!t) return '';
            if (/^\d{1,2}:\d{2}$/.test(t)) return t;
            var m = t.match(/(\d+):(\d+)\s*(AM|PM)/i);
            if (!m) return '';
            var h = parseInt(m[1]),
                mn = m[2],
                ap = m[3].toUpperCase();
            if (ap === 'PM' && h !== 12) h += 12;
            if (ap === 'AM' && h === 12) h = 0;
            return String(h).padStart(2, '0') + ':' + mn;
        }

        document.getElementById('sadd').addEventListener('click', function() {
            document.getElementById('srows').insertAdjacentHTML('beforeend', mkRow());
        });

        // ══ Photo label update ════════════════════════════════════════════════
        function updatePhotoLabel(input) {
            var lbl = document.getElementById('photo-lbl');
            if (input.files && input.files[0]) {
                lbl.textContent = '✓ ' + input.files[0].name;
                lbl.style.color = '#16a34a';
            }
        }

        // ══ Step Wizard ═══════════════════════════════════════════════════════
        var currentStep = 1;
        var totalSteps = 4;

        function goStep(n) {
            if (n < 1 || n > totalSteps) return;
            // Update panels
            document.querySelectorAll('.step-panel').forEach(function(p) {
                p.classList.remove('active');
            });
            document.getElementById('sp' + n).classList.add('active');
            // Update step bar
            document.querySelectorAll('.step-item').forEach(function(item) {
                var s = parseInt(item.dataset.step);
                item.classList.remove('active', 'done');
                if (s < n) item.classList.add('done');
                else if (s === n) item.classList.add('active');
            });
            // Update footer
            document.getElementById('step-ind').textContent = 'Step ' + n + ' of ' + totalSteps;
            document.getElementById('prevbtn').style.display = n > 1 ? 'flex' : 'none';
            document.getElementById('nextbtn').style.display = n < totalSteps ? 'flex' : 'none';
            document.getElementById('sbtn').style.display = n === totalSteps ? 'flex' : 'none';
            // Scroll to top
            document.querySelector('.msc').scrollTop = 0;
            // Build review on step 4
            if (n === 4) buildReview();
            currentStep = n;
        }

        function nextStep() {
            goStep(currentStep + 1);
        }

        function prevStep() {
            goStep(currentStep - 1);
        }

        function buildReview() {
            var ln = document.getElementById('fln').value || '—';
            var fn = document.getElementById('ffn').value || '—';
            var mn = document.getElementById('fmn').value || '';
            var fullName = ln.toUpperCase() + ', ' + fn.toUpperCase() + (mn ? ' ' + mn.toUpperCase() : '');
            document.getElementById('rv-name').textContent = fullName;

            var fields = [
                ['Employee No.', document.getElementById('feid').value],
                ['Gender', document.getElementById('fgnd').value],
                ['Email', document.getElementById('femail').value],
                ['Contact', document.getElementById('fcon').value],
                ['Position', document.getElementById('fcl').value],
                ['Appointment', document.getElementById('fappt').value],
                ['Qualification', document.getElementById('fqual').options[document.getElementById('fqual').selectedIndex]?.text || ''],
                ['Major', document.getElementById('fmaj').value],
                ['Funding', document.getElementById('ffund').value],
                ['Category', document.getElementById('fcat').value],
                ['Advisory Class', document.getElementById('fadv').value],
                ['Club Role', document.getElementById('fclub').value],
            ];

            var html = '';
            fields.forEach(function(f) {
                if (f[1]) html += '<div><span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;">' + f[0] + ':</span><br><span style="font-weight:600;color:#0f172a;">' + (f[1] || '—') + '</span></div>';
            });
            document.getElementById('rv-fields').innerHTML = html || '<div style="color:#94a3b8;font-style:italic;">No data entered.</div>';
        }

        // ══ Modal ══════════════════════════════════════════════════════════════
        var modal = document.getElementById('pmod'),
            mhd = document.getElementById('mhd'),
            mtit = document.getElementById('mtit'),
            msubt = document.getElementById('msubt'),
            mhdi = document.getElementById('mhd-ico'),
            mform = document.getElementById('pform'),
            sbtn = document.getElementById('sbtn'),
            slbl = document.getElementById('slbl');

        function openModal() {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            goStep(1);
        }

        function closeModal() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        function syncCat() {
            var isNT = document.getElementById('fcat').value === 'Non-Teaching';
            document.getElementById('nts').style.display = isNT ? 'block' : 'none';
            document.getElementById('ts').style.display = isNT ? 'none' : 'block';
            document.getElementById('ss').style.display = isNT ? 'none' : 'block';
            if (document.getElementById('nts-sched')) {
                document.getElementById('nts-sched').style.display = isNT ? 'block' : 'none';
            }
        }
        document.getElementById('fcat').addEventListener('change', syncCat);
        syncCat();

        function resetForm() {
            mform.reset();
            document.getElementById('frid').value = '';
            document.getElementById('frtype').value = 'teacher';
            document.getElementById('srows').innerHTML = '';
            sidx = 0;
            syncCat();
            document.getElementById('photo-lbl').textContent = 'Click or drag to upload a photo';
            document.getElementById('photo-lbl').style.color = '';
        }

        document.getElementById('xbtn').addEventListener('click', closeModal);
        document.getElementById('cbtn').addEventListener('click', closeModal);
        window.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // Build full name before submit
        mform.addEventListener('submit', function() {
            var ln = (document.getElementById('fln').value || '').trim().toUpperCase();
            var fn = (document.getElementById('ffn').value || '').trim().toUpperCase();
            var mn = (document.getElementById('fmn').value || '').trim().toUpperCase();
            document.getElementById('ffull').value = ln + (fn ? ', ' + fn : '') + (mn ? ' ' + mn : '');
        });

        // Add Teacher
        document.getElementById('btn-teacher').addEventListener('click', function() {
            resetForm();
            document.getElementById('fact').value = 'add';
            document.getElementById('ptw').style.display = 'flex';
            document.getElementById('fisp').checked = false;
            mhd.className = 'mhd grn';
            mhdi.className = 'fas fa-user-plus';
            mtit.textContent = 'Add Personnel';
            msubt.textContent = 'All fields are optional — fill in only what is available';
            sbtn.className = 'sbtn grn';
            slbl.textContent = 'Save Personnel';
            openModal();
        });

        // Add Principal
        document.getElementById('btn-principal').addEventListener('click', function() {
            resetForm();
            document.getElementById('fact').value = 'add';
            document.getElementById('ptw').style.display = 'flex';
            document.getElementById('fisp').checked = true;
            document.getElementById('fcl').value = 'School Principal I';
            document.getElementById('fappt').value = 'REGULAR PERMANENT';
            document.getElementById('fcat').value = 'Teaching';
            syncCat();
            mhd.className = 'mhd gld';
            mhdi.className = 'fas fa-crown';
            mtit.textContent = 'Add School Principal';
            msubt.textContent = 'Appointing a new principal will archive the current one';
            sbtn.className = 'sbtn gld';
            slbl.textContent = 'Appoint Principal';
            openModal();
        });

        // Edit
        document.querySelectorAll('.ab.e').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                resetForm();
                var d = this.dataset;
                document.getElementById('fact').value = 'edit';
                document.getElementById('frid').value = d.rid;
                document.getElementById('frtype').value = d.type;
                document.getElementById('ptw').style.display = 'none';

                document.getElementById('feid').value = d.id || '';
                document.getElementById('fln').value = d.lastname || '';
                document.getElementById('ffn').value = d.firstname || '';
                document.getElementById('fmn').value = d.middlename || '';
                document.getElementById('fgnd').value = d.gender || '';
                document.getElementById('fcl').value = d.careerlevel || '';
                document.getElementById('fappt').value = d.employment || '';
                document.getElementById('fqual').value = d.qual || '';
                document.getElementById('fmaj').value = d.major || '';
                document.getElementById('femail').value = d.email || '';
                document.getElementById('fcon').value = d.contact || '';
                document.getElementById('fadv').value = d.advisory || '';
                document.getElementById('fclub').value = d.club || '';
                document.getElementById('fntpos').value = d.ntpos || '';
                document.getElementById('fntappt').value = d.ntappt || '';
                document.getElementById('fntfund').value = d.ntfund || '';
                document.getElementById('fcat').value = d.category || 'Teaching';
                document.getElementById('ffund').value = d.funding || 'NATIONAL';
                syncCat();

                // Load schedules via AJAX
                fetch('teachers.php?get_schedules=1&tid=' + encodeURIComponent(d.id) + '&ttype=' + encodeURIComponent(d.type))
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(rows) {
                        document.getElementById('srows').innerHTML = '';
                        sidx = 0;
                        if (rows && rows.length) {
                            rows.forEach(function(r) {
                                document.getElementById('srows').insertAdjacentHTML('beforeend', mkRow(r));
                            });
                        } else {
                            document.getElementById('srows').insertAdjacentHTML('beforeend', mkRow());
                        }
                    }).catch(function() {
                        document.getElementById('srows').insertAdjacentHTML('beforeend', mkRow());
                    });

                var isp = (d.type === 'principal');
                mhd.className = isp ? 'mhd gld' : 'mhd grn';
                mhdi.className = isp ? 'fas fa-crown' : 'fas fa-user-edit';
                mtit.textContent = isp ? 'Edit Principal' : 'Edit Personnel';
                msubt.textContent = 'Update the personnel record below';
                sbtn.className = isp ? 'sbtn gld' : 'sbtn grn';
                slbl.textContent = 'Save Changes';
                openModal();
            });
        });

        // Delete
        document.querySelectorAll('.ab.d').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var lbl = this.dataset.type === 'principal' ? 'principal' : 'personnel';
                if (confirm('Delete this ' + lbl + '? This action cannot be undone.')) {
                    document.getElementById('drid').value = this.dataset.rid;
                    document.getElementById('dtype').value = this.dataset.type;
                    document.getElementById('delf').submit();
                }
            });
        });

        // Auto-dismiss alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(a) {
                a.style.transition = 'opacity .4s';
                a.style.opacity = '0';
                setTimeout(function() {
                    a.closest('.alert-c') && a.closest('.alert-c').remove();
                }, 400);
            });
        }, 4000);
    </script>
</body>

</html>