<?php
session_start();
include '../db_connection.php';

// ─── AUTO-CREATE / ALTER TABLES ──────────────────────────────────────────────
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS status ENUM('Active','Transferred','Completers','Dropped') DEFAULT 'Active'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS section VARCHAR(50) DEFAULT ''");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS birthdate DATE DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS middle_initial VARCHAR(5) DEFAULT ''");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS suffix VARCHAR(15) DEFAULT ''");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS lrn VARCHAR(20) DEFAULT ''");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS enrollment_status VARCHAR(50) DEFAULT ''");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS date_enrolled DATE DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS graduation_year SMALLINT DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS class_schedule TEXT DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS phone_number VARCHAR(30) DEFAULT ''");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS honors_awards TEXT DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS club_memberships TEXT DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS sports_teams TEXT DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS height_cm SMALLINT DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(5,1) DEFAULT NULL");
$conn->query("CREATE TABLE IF NOT EXISTS student_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_name VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// ─── CSRF ─────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$adminName = $_SESSION['admin_name'] ?? $_SESSION['username'] ?? 'Admin';

// ─── HELPER: log action ───────────────────────────────────────────────────────
function logAction($conn, $adminName, $action, $studentId)
{
    $stmt = $conn->prepare("INSERT INTO student_logs (admin_name, action, student_id) VALUES (?,?,?)");
    $stmt->bind_param('sss', $adminName, $action, $studentId);
    $stmt->execute();
    $stmt->close();
}

// ─── HANDLE POST ACTIONS ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: students.php');
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $firstName      = trim($_POST['first_name'] ?? '');
        $middleInitial  = trim($_POST['middle_initial'] ?? '');
        $lastName       = trim($_POST['last_name'] ?? '');
        $suffix         = trim($_POST['suffix'] ?? '');
        $rawName        = trim($firstName . ' ' . ($middleInitial ? $middleInitial . ' ' : '') . $lastName . ($suffix ? ' ' . $suffix : ''));
        $studentId      = trim($_POST['student_id'] ?? '');
        $grade          = trim($_POST['grade_section'] ?? '');
        $gender         = trim($_POST['gender'] ?? '');
        $section        = trim($_POST['section'] ?? '');
        $status         = trim($_POST['status'] ?? 'Active');
        $birthdate      = trim($_POST['birthdate'] ?? '');
        $age            = !empty($birthdate) ? (int)date_diff(date_create($birthdate), date_create('today'))->y : (int)($_POST['age'] ?? 0);
        $lrn            = trim($_POST['lrn'] ?? '');
        $enrollmentStatus = trim($_POST['enrollment_status'] ?? '');
        $dateEnrolled   = trim($_POST['date_enrolled'] ?? '');
        $graduationYear = trim($_POST['graduation_year'] ?? '');
        $classSchedule  = trim($_POST['class_schedule'] ?? '');
        $phoneNumber    = trim($_POST['phone_number'] ?? '');
        $honorsAwards   = trim($_POST['honors_awards'] ?? '');
        $clubMemberships = trim($_POST['club_memberships'] ?? '');
        $sportsTeams    = trim($_POST['sports_teams'] ?? '');
        $heightCm       = trim($_POST['height_cm'] ?? '');
        $weightKg       = trim($_POST['weight_kg'] ?? '');
        $chk = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
        $chk->bind_param('s', $studentId);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $_SESSION['error'] = "Student ID {$studentId} already exists.";
            $chk->close();
            header('Location: students.php');
            exit;
        }
        $chk->close();
        $imagePath = '';
        if (!empty($_FILES['student_image']['name'])) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['student_image']['type'], $allowed) && $_FILES['student_image']['size'] <= 2097152) {
                $ext  = pathinfo($_FILES['student_image']['name'], PATHINFO_EXTENSION);
                $dest = '../assets/img/person/' . $studentId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['student_image']['tmp_name'], $dest)) $imagePath = $dest;
            }
        }
        $birthdateVal     = !empty($birthdate) ? $birthdate : null;
        $dateEnrolledVal  = !empty($dateEnrolled) ? $dateEnrolled : null;
        $graduationYearVal = !empty($graduationYear) ? (int)$graduationYear : null;
        $heightVal        = !empty($heightCm) ? (int)$heightCm : null;
        $weightVal        = !empty($weightKg) ? (float)$weightKg : null;
        $stmt = $conn->prepare("INSERT INTO students (first_name,middle_initial,last_name,suffix,student_id,grade_level,gender,age,section,status,birthdate,profile_image,lrn,enrollment_status,date_enrolled,graduation_year,class_schedule,phone_number,honors_awards,club_memberships,sports_teams,height_cm,weight_kg) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssssissssssissssssd', $firstName, $middleInitial, $lastName, $suffix, $studentId, $grade, $gender, $age, $section, $status, $birthdateVal, $imagePath, $lrn, $enrollmentStatus, $dateEnrolledVal, $graduationYearVal, $classSchedule, $phoneNumber, $honorsAwards, $clubMemberships, $sportsTeams, $heightVal, $weightVal);
        if ($stmt->execute()) {
            logAction($conn, $adminName, 'Add Student', $studentId);
            $_SESSION['success'] = "Student {$rawName} added successfully.";
        } else {
            $_SESSION['error'] = "Error adding student: " . $conn->error;
        }
        $stmt->close();
        header('Location: students.php');
        exit;
    }

    if ($action === 'edit') {
        $rawName   = trim($_POST['student_name'] ?? '');
        $parts     = explode(' ', $rawName, 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';
        $studentId = trim($_POST['student_id'] ?? '');
        $grade     = trim($_POST['grade_section'] ?? '');
        $gender    = trim($_POST['gender'] ?? '');
        $section   = trim($_POST['section'] ?? '');
        $status    = trim($_POST['status'] ?? 'Active');
        $birthdate = trim($_POST['birthdate'] ?? '');
        $age       = !empty($birthdate) ? (int)date_diff(date_create($birthdate), date_create('today'))->y : (int)($_POST['age'] ?? 0);
        $imgUpdate = '';
        $imgParams = [];
        if (!empty($_FILES['student_image']['name'])) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['student_image']['type'], $allowed) && $_FILES['student_image']['size'] <= 2097152) {
                $ext  = pathinfo($_FILES['student_image']['name'], PATHINFO_EXTENSION);
                $dest = '../assets/img/person/' . $studentId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['student_image']['tmp_name'], $dest)) {
                    $imgUpdate = ', profile_image = ?';
                    $imgParams[] = $dest;
                }
            }
        }
        $birthdateVal = !empty($birthdate) ? $birthdate : null;
        $sql  = "UPDATE students SET first_name=?, last_name=?, grade_level=?, gender=?, age=?, section=?, status=?, birthdate=? {$imgUpdate} WHERE student_id=?";
        $stmt = $conn->prepare($sql);
        if (!empty($imgParams)) $stmt->bind_param('ssssissa' . 'ss', $firstName, $lastName, $grade, $gender, $age, $section, $status, $birthdateVal, $imgParams[0], $studentId);
        else $stmt->bind_param('ssssissa' . 's', $firstName, $lastName, $grade, $gender, $age, $section, $status, $birthdateVal, $studentId);
        if ($stmt->execute()) {
            logAction($conn, $adminName, 'Edit Student', $studentId);
            $_SESSION['success'] = "Student updated successfully.";
        } else {
            $_SESSION['error'] = "Error updating: " . $conn->error;
        }
        $stmt->close();
        header('Location: students.php');
        exit;
    }

    if ($action === 'delete') {
        $studentId = trim($_POST['student_id'] ?? '');
        $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->bind_param('s', $studentId);
        if ($stmt->execute()) {
            logAction($conn, $adminName, 'Delete Student', $studentId);
            $_SESSION['success'] = "Student deleted.";
        } else {
            $_SESSION['error'] = "Error deleting: " . $conn->error;
        }
        $stmt->close();
        header('Location: students.php');
        exit;
    }

    if ($action === 'csv_import') {
        $added = $skipped = $errors = 0;
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            if ($_FILES['csv_file']['size'] > 5242880) {
                $_SESSION['error'] = 'CSV file exceeds 5MB limit.';
                header('Location: students.php');
                exit;
            }
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $header = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 6) {
                    $errors++;
                    continue;
                }
                [$sid, $fn, $ln, $gl, $gn, $ag] = $row;
                $sid = trim($sid);
                $fn = trim($fn);
                $ln = trim($ln);
                $gl = trim($gl);
                $gn = trim($gn);
                $ag = (int)trim($ag);
                if (empty($sid) || empty($fn) || empty($ln)) {
                    $errors++;
                    continue;
                }
                $chk = $conn->prepare("SELECT id FROM students WHERE student_id=?");
                $chk->bind_param('s', $sid);
                $chk->execute();
                $chk->store_result();
                if ($chk->num_rows > 0) {
                    $skipped++;
                    $chk->close();
                    continue;
                }
                $chk->close();
                $status = 'Active';
                $section = '';
                $img = '';
                $ins = $conn->prepare("INSERT INTO students (student_id,first_name,last_name,grade_level,gender,age,section,status,profile_image) VALUES (?,?,?,?,?,?,?,?,?)");
                $ins->bind_param('sssssssss', $sid, $fn, $ln, $gl, $gn, $ag, $section, $status, $img);
                if ($ins->execute()) {
                    $added++;
                    logAction($conn, $adminName, 'Add Student (CSV)', $sid);
                } else {
                    $errors++;
                }
                $ins->close();
            }
            fclose($handle);
        }
        $_SESSION['import_summary'] = ['added' => $added, 'skipped' => $skipped, 'errors' => $errors];
        $_SESSION['success'] = "CSV Import complete: {$added} added, {$skipped} skipped, {$errors} errors.";
        header('Location: students.php');
        exit;
    }
}

// ─── EXPORT HANDLER ──────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'docx') {

    // ── Query ALL students matching filters (no pagination) ───────────────────
    $expConditions = array();
    $expParams = array();
    $expTypes = '';
    $expSearch = isset($_GET['search']) ? trim($_GET['search']) : '';
    $expGrade  = isset($_GET['grade'])  ? $_GET['grade']  : '';
    $expStatus = isset($_GET['status']) ? $_GET['status'] : '';
    if (!empty($expSearch)) {
        $expConditions[] = '(first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ?)';
        $sp = '%' . $expSearch . '%';
        $expParams[] = $sp;
        $expParams[] = $sp;
        $expParams[] = $sp;
        $expTypes .= 'sss';
    }
    if (!empty($expGrade)) {
        $expConditions[] = 'grade_level=?';
        $expParams[] = $expGrade;
        $expTypes .= 's';
    }
    if (!empty($expStatus)) {
        $expConditions[] = 'status=?';
        $expParams[] = $expStatus;
        $expTypes .= 's';
    }
    $expWhere = !empty($expConditions) ? 'WHERE ' . implode(' AND ', $expConditions) : '';
    $expStmt  = $conn->prepare('SELECT s.*, COALESCE(NULLIF(spd.photo,\'\'), NULLIF(s.profile_image,\'\'), \'../assets/img/person/unknown.jpg\') AS effective_photo, spd.photo AS profile_photo FROM students s LEFT JOIN student_profile_data spd ON spd.student_id = s.student_id ' . $expWhere . ' ORDER BY s.grade_level, s.last_name, s.first_name');
    if (!empty($expParams)) $expStmt->bind_param($expTypes, ...$expParams);
    $expStmt->execute();
    $expStudents = $expStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $expStmt->close();

    // ── Compute stats ─────────────────────────────────────────────────────────
    $expTotalActive = 0;
    $expMale = 0;
    $expFemale = 0;
    $expGradeCounts = array('Grade 7' => 0, 'Grade 8' => 0, 'Grade 9' => 0, 'Grade 10' => 0);
    $allR = $conn->query('SELECT status,gender,grade_level FROM students');
    while ($r = $allR->fetch_assoc()) {
        if ($r['status'] === 'Active') {
            $expTotalActive++;
            if ($r['gender'] === 'Male')   $expMale++;
            if ($r['gender'] === 'Female') $expFemale++;
            if (isset($expGradeCounts[$r['grade_level']])) $expGradeCounts[$r['grade_level']]++;
        }
    }
    $expRealTotal = $expMale + $expFemale;
    $expMalePct   = $expRealTotal > 0 ? round($expMale   / $expRealTotal * 100) : 0;
    $expFemalePct = $expRealTotal > 0 ? (100 - $expMalePct) : 0;
    $expMaxGrade  = max(array_values($expGradeCounts));
    if ($expMaxGrade < 1) $expMaxGrade = 1;
    $expGradeSum  = array_sum($expGradeCounts);

    $genDate    = date('F j, Y \a\t g:i A');
    $schoolName = 'Buyoan National High School';

    // ── XML escape helper ─────────────────────────────────────────────────────
    function xe($s)
    {
        return htmlspecialchars((string)($s === null ? '' : $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
    function xd($s)
    {
        $v = (string)($s === null ? '' : $s);
        return ($v === '') ? chr(226) . chr(128) . chr(148) : htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // ── OOXML helper functions ────────────────────────────────────────────────
    function wRun($text, $sz = 20, $bold = false, $col = '0F0E17', $font = 'Arial')
    {
        $b = $bold ? '<w:b/><w:bCs/>' : '';
        return '<w:r><w:rPr>' . $b
            . '<w:rFonts w:ascii="' . $font . '" w:hAnsi="' . $font . '" w:cs="' . $font . '"/>'
            . '<w:color w:val="' . $col . '"/>'
            . '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>'
            . '</w:rPr><w:t xml:space="preserve">' . xe($text) . '</w:t></w:r>';
    }
    function wPara($runs = '', $align = 'left', $before = 0, $after = 0, $fill = null)
    {
        $ppr = '<w:pPr>'
            . '<w:jc w:val="' . $align . '"/>'
            . '<w:spacing w:before="' . $before . '" w:after="' . $after . '"/>';
        if ($fill !== null) $ppr .= '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>';
        $ppr .= '</w:pPr>';
        return '<w:p>' . $ppr . $runs . '</w:p>';
    }
    function wTcPr($w, $fill, $vAlign = 'center', $bdrColor = 'E8E6E1', $showBdr = true)
    {
        $bdr = $showBdr
            ? '<w:tcBorders>'
            . '<w:top w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '<w:left w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '<w:right w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '</w:tcBorders>'
            : '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders>';
        return '<w:tcPr>'
            . '<w:tcW w:w="' . $w . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>'
            . '<w:vAlign w:val="' . $vAlign . '"/>'
            . '<w:tcMar>'
            . '<w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/>'
            . '<w:left w:w="120" w:type="dxa"/><w:right w:w="120" w:type="dxa"/>'
            . '</w:tcMar>'
            . $bdr
            . '</w:tcPr>';
    }
    function wCell($w, $fill, $content, $vAlign = 'center', $showBdr = true)
    {
        return '<w:tc>' . wTcPr($w, $fill, $vAlign, 'E8E6E1', $showBdr) . $content . '</w:tc>';
    }
    function wTblPr($totalW, $bdrColor = 'E8E6E1')
    {
        return '<w:tblPr>'
            . '<w:tblW w:w="' . $totalW . '" w:type="dxa"/>'
            . '<w:tblLayout w:type="fixed"/>'
            . '<w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '<w:left w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '<w:right w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="' . $bdrColor . '"/>'
            . '</w:tblBorders>'
            . '<w:tblCellMar>'
            . '<w:top w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/>'
            . '<w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/>'
            . '</w:tblCellMar>'
            . '</w:tblPr>';
    }
    function wGrid(array $widths)
    {
        $g = '<w:tblGrid>';
        foreach ($widths as $w) $g .= '<w:gridCol w:w="' . $w . '"/>';
        return $g . '</w:tblGrid>';
    }
    function wGapCell($w, $fill = 'F4F3F0')
    {
        return '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $w . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders></w:tcPr><w:p/></w:tc>';
    }

    // Page usable width: A4 landscape 16838 twips - margins 720*2 = 15398
    $PW = 15398;

    // ════════════════════════════════════════════════════════════════════════
    // 1. HEADER BANNER
    // ════════════════════════════════════════════════════════════════════════
    $bannerContent =
        wPara(wRun('RECORDS MANAGEMENT', 14, false, 'A8E6C0'), 'left', 160, 40, '1A4733')
        . wPara(wRun($schoolName, 34, true, 'FFFFFF'), 'left', 0, 60, '1A4733')
        . wPara(wRun('Student Records Report  -  Generated ' . $genDate, 18, false, 'BBBBBB'), 'left', 0, 160, '1A4733');

    $bannerXml = '<w:tbl>' . wTblPr($PW, '1A4733') . wGrid(array($PW))
        . '<w:tr>' . wCell($PW, '1A4733', $bannerContent, 'center', false) . '</w:tr>'
        . '</w:tbl>';

    // ════════════════════════════════════════════════════════════════════════
    // 2. STAT CARDS
    // ════════════════════════════════════════════════════════════════════════
    $cW  = intval(($PW - 120) / 4);
    $gW  = 40;
    $stW = $cW * 4 + $gW * 3;

    function wStatCard($w, $fill, $iconChar, $value, $label, $valCol, $lblCol)
    {
        $icon = wPara(wRun($iconChar, 26, true, $valCol), 'left', 160, 60, $fill);
        $val  = wPara(wRun((string)$value, 52, true, $valCol), 'left', 0, 40, $fill);
        $lbl  = wPara(wRun(strtoupper($label), 14, true, $lblCol), 'left', 0, 160, $fill);
        return '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $w . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders>'
            . '<w:tcMar>'
            . '<w:top w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/>'
            . '<w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/>'
            . '</w:tcMar></w:tcPr>'
            . $icon . $val . $lbl
            . '</w:tc>';
    }

    $statXml = '<w:tbl>' . wTblPr($stW, 'F4F3F0') . wGrid(array($cW, $gW, $cW, $gW, $cW, $gW, $cW))
        . '<w:tr>'
        . wStatCard($cW, '1A4733', chr(9654), $expTotalActive, 'Total Active',   'FFFFFF', 'FFFFFF99')
        . wGapCell($gW, 'F4F3F0')
        . wStatCard($cW, 'DBEAFE', chr(9794), $expMale,        'Male Students',  '1D4ED8', '1D4ED8')
        . wGapCell($gW, 'F4F3F0')
        . wStatCard($cW, 'FCE7F3', chr(9792), $expFemale,      'Female Students', 'DB2777', 'DB2777')
        . wGapCell($gW, 'F4F3F0')
        . wStatCard($cW, 'DCFCE7', chr(9642), $expGradeSum,    'Across 4 Grades', '15803D', '15803D')
        . '</w:tr>'
        . '</w:tbl>';

    // ════════════════════════════════════════════════════════════════════════
    // 3. CHARTS ROW
    // ════════════════════════════════════════════════════════════════════════
    $chartMainW = intval($PW * 0.72) - 20;
    $chartSideW = $PW - $chartMainW - 40;
    $chartGap   = 40;

    // Grade bars
    $gradeBarColors = array(
        'Grade 7'  => array('8B7CF8', '6D28D9'),
        'Grade 8'  => array('38BDF8', '0284C7'),
        'Grade 9'  => array('4ADE80', '15803D'),
        'Grade 10' => array('FB923C', 'C2410C'),
    );
    $barW = intval(($chartMainW - 200) / 4);
    $bGap = 40;
    $barGridWidths = array();
    $bIdx = 0;
    foreach ($expGradeCounts as $gn => $gc) {
        if ($bIdx > 0) $barGridWidths[] = $bGap;
        $barGridWidths[] = $barW;
        $bIdx++;
    }
    $barTotalW = array_sum($barGridWidths);

    // Row 1: count labels
    $barRow1 = '<w:tr><w:trPr><w:trHeight w:val="240" w:hRule="exact"/></w:trPr>';
    $bIdx = 0;
    foreach ($expGradeCounts as $gn => $gc) {
        $cols2 = $gradeBarColors[$gn];
        if ($bIdx > 0) $barRow1 .= wGapCell($bGap, 'FFFFFF');
        $barRow1 .= '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $barW . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="FFFFFF"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders>'
            . '<w:vAlign w:val="bottom"/></w:tcPr>'
            . wPara(wRun((string)$gc, 18, true, $cols2[1]), 'center', 0, 0)
            . '</w:tc>';
        $bIdx++;
    }
    $barRow1 .= '</w:tr>';

    // Row 2: colored bar cells
    $barRowH = 800;
    $barRow2 = '<w:tr><w:trPr><w:trHeight w:val="' . $barRowH . '" w:hRule="exact"/></w:trPr>';
    $bIdx = 0;
    foreach ($expGradeCounts as $gn => $gc) {
        $cols2 = $gradeBarColors[$gn];
        if ($bIdx > 0) $barRow2 .= wGapCell($bGap, 'FFFFFF');
        $barRow2 .= '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $barW . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="' . $cols2[0] . '"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders>'
            . '<w:vAlign w:val="bottom"/>'
            . '<w:tcMar>'
            . '<w:top w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/>'
            . '<w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/>'
            . '</w:tcMar></w:tcPr>'
            . wPara(wRun(' ', 14, false, 'FFFFFF'), 'center', 0, 0, $cols2[0])
            . '</w:tc>';
        $bIdx++;
    }
    $barRow2 .= '</w:tr>';

    // Row 3: grade name labels
    $barRow3 = '<w:tr><w:trPr><w:trHeight w:val="280" w:hRule="exact"/></w:trPr>';
    $bIdx = 0;
    foreach ($expGradeCounts as $gn => $gc) {
        if ($bIdx > 0) $barRow3 .= wGapCell($bGap, 'FFFFFF');
        $barRow3 .= '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $barW . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="FFFFFF"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="single" w:sz="4" w:space="0" w:color="E8E6E1"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders></w:tcPr>'
            . wPara(wRun($gn, 16, false, '9190A0'), 'center', 60, 0)
            . '</w:tc>';
        $bIdx++;
    }
    $barRow3 .= '</w:tr>';

    $gradeChartXml = '<w:tbl>' . wTblPr($barTotalW, 'FFFFFF') . wGrid($barGridWidths)
        . $barRow1 . $barRow2 . $barRow3
        . '</w:tbl>';

    // Grade chart cell content
    $gradeChartContent =
        wPara(wRun('ENROLLMENT', 14, false, '9190A0'), 'left', 0, 40)
        . wPara(wRun('Students by Grade Level', 22, true, '0F0E17'), 'left', 0, 160)
        . $gradeChartXml;

    // Gender side content
    $sideInnerW = $chartSideW - 40;
    $sideInnerW = max($sideInnerW, 200);
    $genderSideXml =
        wPara(wRun('DISTRIBUTION', 14, false, '9190A0'), 'left', 0, 40)
        . wPara(wRun('Gender Split', 22, true, '0F0E17'), 'left', 0, 120)
        . '<w:tbl>' . wTblPr($sideInnerW, 'FFFFFF') . wGrid(array(160, $sideInnerW - 400, 240))
        . '<w:tr>'
        . wCell(160, 'DBEAFE', wPara(wRun(chr(9794) . ' Male', 18, true, '1D4ED8'), 'left', 60, 60, 'DBEAFE'), 'center', false)
        . wCell($sideInnerW - 400, 'FFFFFF', wPara(wRun((string)$expMale . ' students', 17, false, '0F0E17'), 'left', 60, 60), 'center', false)
        . wCell(240, 'DBEAFE', wPara(wRun($expMalePct . '%', 18, true, '1D4ED8'), 'center', 60, 60, 'DBEAFE'), 'center', false)
        . '</w:tr></w:tbl>'
        . wPara('', 'left', 80, 0)
        . '<w:tbl>' . wTblPr($sideInnerW, 'FFFFFF') . wGrid(array(160, $sideInnerW - 400, 240))
        . '<w:tr>'
        . wCell(160, 'FCE7F3', wPara(wRun(chr(9792) . ' Female', 18, true, 'DB2777'), 'left', 60, 60, 'FCE7F3'), 'center', false)
        . wCell($sideInnerW - 400, 'FFFFFF', wPara(wRun((string)$expFemale . ' students', 17, false, '0F0E17'), 'left', 60, 60), 'center', false)
        . wCell(240, 'FCE7F3', wPara(wRun($expFemalePct . '%', 18, true, 'DB2777'), 'center', 60, 60, 'FCE7F3'), 'center', false)
        . '</w:tr></w:tbl>'
        . wPara(wRun('Total: ' . $expRealTotal . ' students', 17, false, '6B6A7A'), 'center', 120, 0);

    $chartsXml = '<w:tbl>' . wTblPr($PW, 'F4F3F0') . wGrid(array($chartMainW, $chartGap, $chartSideW))
        . '<w:tr>'
        . wCell($chartMainW, 'FFFFFF', $gradeChartContent, 'top', false)
        . wGapCell($chartGap, 'F4F3F0')
        . wCell($chartSideW, 'FFFFFF', $genderSideXml, 'top', false)
        . '</w:tr></w:tbl>';

    // ════════════════════════════════════════════════════════════════════════
    // 4. STUDENT TABLE
    // ════════════════════════════════════════════════════════════════════════
    $tCols  = array('Student Name', 'ID Number', 'Grade', 'Section', 'Age', 'Gender', 'Status');
    $tColWs = array(
        intval($PW * 0.22),
        intval($PW * 0.14),
        intval($PW * 0.10),
        intval($PW * 0.13),
        intval($PW * 0.07),
        intval($PW * 0.10),
        intval($PW * 0.12),
    );
    $tColWs[6] = $PW - array_sum(array_slice($tColWs, 0, 6));

    // Table column headers
    $tHead = '<w:tr><w:trPr><w:tblHeader/><w:trHeight w:val="460" w:hRule="exact"/></w:trPr>';
    foreach ($tCols as $ci => $colName) {
        $tHead .= '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $tColWs[$ci] . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="FAFAF8"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="E8E6E1"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders>'
            . '<w:vAlign w:val="center"/>'
            . '<w:tcMar>'
            . '<w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/>'
            . '<w:left w:w="160" w:type="dxa"/><w:right w:w="80" w:type="dxa"/>'
            . '</w:tcMar></w:tcPr>'
            . wPara(wRun(strtoupper($colName), 14, true, '9190A0'), 'left', 0, 0, 'FAFAF8')
            . '</w:tc>';
    }
    $tHead .= '</w:tr>';

    // Status colors
    $statusMap = array(
        'Active'      => array('DCFCE7', '15803D'),
        'Transferred' => array('FEF9C3', 'A16207'),
        'Completers'   => array('DBEAFE', '1D4ED8'),
        'Dropped'     => array('FEE2E2', 'DC2626'),
    );

    // Data rows
    $tRows = '';
    foreach ($expStudents as $ri => $s) {
        $rowFill  = ($ri % 2 === 0) ? 'FFFFFF' : 'F8F7F4';
        $fullName = trim(($s['first_name'] !== null ? $s['first_name'] : '') . ' ' . ($s['last_name'] !== null ? $s['last_name'] : ''));
        $sid      = $s['student_id'] !== null ? $s['student_id'] : '';
        $grade    = $s['grade_level'] !== null ? $s['grade_level'] : 'N/A';
        $sec      = $s['section'] !== null && $s['section'] !== '' ? $s['section'] : chr(226) . chr(128) . chr(148);
        $age      = $s['age'] !== null ? (string)$s['age'] : 'N/A';
        $gen      = $s['gender'] !== null ? $s['gender'] : 'N/A';
        $st       = $s['status'] !== null ? $s['status'] : 'Active';
        $stColors = isset($statusMap[$st]) ? $statusMap[$st] : array('DCFCE7', '15803D');

        $ini = strtoupper(
            substr($s['first_name'] !== null ? $s['first_name'] : '?', 0, 1)
                . substr($s['last_name']  !== null ? $s['last_name']  : '?', 0, 1)
        );

        $tRows .= '<w:tr><w:trPr><w:trHeight w:val="500" w:hRule="atLeast"/></w:trPr>';

        // Name cell
        $avatarW  = 360;
        $nameTextW = $tColWs[0] - 240 - $avatarW;
        $nameInner = '<w:tbl>' . wTblPr($tColWs[0] - 240, 'FFFFFF') . wGrid(array($avatarW, $nameTextW))
            . '<w:tr>'
            . wCell($avatarW, '2D5C43', wPara(wRun($ini, 17, true, 'FFFFFF'), 'center', 0, 0, '2D5C43'), 'center', false)
            . wCell($nameTextW, $rowFill, wPara(wRun($fullName, 18, true, '0F0E17'), 'left', 0, 0, $rowFill), 'center', false)
            . '</w:tr></w:tbl>';
        $tRows .= '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $tColWs[0] . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="' . $rowFill . '"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="F4F3F0"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders>'
            . '<w:vAlign w:val="center"/>'
            . '<w:tcMar>'
            . '<w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/>'
            . '<w:left w:w="160" w:type="dxa"/><w:right w:w="80" w:type="dxa"/>'
            . '</w:tcMar></w:tcPr>'
            . $nameInner . '</w:tc>';

        // ID cell
        $idInnerW = $tColWs[1] - 240;
        $tRows .= '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $tColWs[1] . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="' . $rowFill . '"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="F4F3F0"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders>'
            . '<w:vAlign w:val="center"/>'
            . '<w:tcMar>'
            . '<w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/>'
            . '<w:left w:w="160" w:type="dxa"/><w:right w:w="80" w:type="dxa"/>'
            . '</w:tcMar></w:tcPr>'
            . '<w:tbl>' . wTblPr($idInnerW, 'F4F3F0') . wGrid(array($idInnerW))
            . '<w:tr>' . wCell($idInnerW, 'F4F3F0', wPara(wRun($sid, 17, false, '5A5870', 'Courier New'), 'left', 60, 60, 'F4F3F0'), 'center', false) . '</w:tr>'
            . '</w:tbl></w:tc>';

        // Grade chip
        $gradeInnerW = $tColWs[2] - 240;
        $tRows .= '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $tColWs[2] . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="' . $rowFill . '"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="F4F3F0"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders>'
            . '<w:vAlign w:val="center"/>'
            . '<w:tcMar>'
            . '<w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/>'
            . '<w:left w:w="160" w:type="dxa"/><w:right w:w="80" w:type="dxa"/>'
            . '</w:tcMar></w:tcPr>'
            . '<w:tbl>' . wTblPr($gradeInnerW, 'EDE9FE') . wGrid(array($gradeInnerW))
            . '<w:tr>' . wCell($gradeInnerW, 'EDE9FE', wPara(wRun($grade, 17, true, '6D28D9'), 'center', 60, 60, 'EDE9FE'), 'center', false) . '</w:tr>'
            . '</w:tbl></w:tc>';

        // Section, Age, Gender — plain cells
        $plainVals = array($sec, $age, $gen);
        for ($pci = 0; $pci < 3; $pci++) {
            $colIdx = $pci + 3;
            $tRows .= '<w:tc><w:tcPr>'
                . '<w:tcW w:w="' . $tColWs[$colIdx] . '" w:type="dxa"/>'
                . '<w:shd w:val="clear" w:color="auto" w:fill="' . $rowFill . '"/>'
                . '<w:tcBorders>'
                . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
                . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
                . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="F4F3F0"/>'
                . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
                . '</w:tcBorders>'
                . '<w:vAlign w:val="center"/>'
                . '<w:tcMar>'
                . '<w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/>'
                . '<w:left w:w="160" w:type="dxa"/><w:right w:w="80" w:type="dxa"/>'
                . '</w:tcMar></w:tcPr>'
                . wPara(wRun($plainVals[$pci], 18, false, '2D2C3E'), 'left', 0, 0, $rowFill)
                . '</w:tc>';
        }

        // Status badge
        $stInnerW = $tColWs[6] - 240;
        $tRows .= '<w:tc><w:tcPr>'
            . '<w:tcW w:w="' . $tColWs[6] . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="' . $rowFill . '"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="F4F3F0"/>'
            . '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tcBorders>'
            . '<w:vAlign w:val="center"/>'
            . '<w:tcMar>'
            . '<w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/>'
            . '<w:left w:w="160" w:type="dxa"/><w:right w:w="80" w:type="dxa"/>'
            . '</w:tcMar></w:tcPr>'
            . '<w:tbl>' . wTblPr($stInnerW, $stColors[0]) . wGrid(array($stInnerW))
            . '<w:tr>'
            . wCell($stInnerW, $stColors[0], wPara(wRun(chr(9679) . ' ' . $st, 17, true, $stColors[1]), 'center', 60, 60, $stColors[0]), 'center', false)
            . '</w:tr></w:tbl></w:tc>';

        $tRows .= '</w:tr>';
    }

    if (empty($expStudents)) {
        $tRows = '<w:tr><w:tc><w:tcPr><w:tcW w:w="' . $PW . '" w:type="dxa"/><w:gridSpan w:val="7"/></w:tcPr>'
            . wPara(wRun('No students found.', 18, false, 'B0AEC0'), 'center', 200, 200)
            . '</w:tc></w:tr>';
    }

    $studentTableXml = '<w:tbl>' . wTblPr($PW, 'E8E6E1') . wGrid($tColWs) . $tHead . $tRows . '</w:tbl>';

    // Table heading label
    $tblLabelXml = '<w:tbl>' . wTblPr($PW, 'FFFFFF') . wGrid(array($PW))
        . '<w:tr>'
        . wCell(
            $PW,
            'FFFFFF',
            wPara(
                wRun('STUDENT INFORMATION', 14, true, '2D5C43')
                    . wRun('   ' . count($expStudents) . ' RECORDS', 14, false, '9190A0'),
                'left',
                120,
                120,
                'FFFFFF'
            ),
            'center',
            false
        )
        . '</w:tr></w:tbl>';

    // Footer
    $footerXml = wPara(
        wRun($schoolName . '   -   Student Records Report   -   ' . count($expStudents) . ' total records   -   Generated ' . $genDate, 16, false, 'B0AEC0'),
        'center',
        160,
        0
    );

    // ════════════════════════════════════════════════════════════════════════
    // 5. ASSEMBLE document.xml
    // ════════════════════════════════════════════════════════════════════════
    $bodyXml =
        $bannerXml
        . wPara('', 'left', 140, 0)
        . $statXml
        . wPara('', 'left', 140, 0)
        . $chartsXml
        . wPara('', 'left', 140, 0)
        . $tblLabelXml
        . $studentTableXml
        . $footerXml;

    $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>'
        . $bodyXml
        . '<w:sectPr>'
        . '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
        . '<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/>'
        . '</w:sectPr>'
        . '</w:body></w:document>';

    // ── Required DOCX support files ───────────────────────────────────────────
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';

    $wordRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr>'
        . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
        . '<w:sz w:val="20"/><w:szCs w:val="20"/><w:color w:val="0F0E17"/>'
        . '</w:rPr></w:rPrDefault></w:docDefaults>'
        . '</w:styles>';

    // ── Build ZIP binary (pure PHP, no extensions needed) ────────────────────
    $zipFiles = array(
        '[Content_Types].xml'          => $contentTypes,
        '_rels/.rels'                  => $rootRels,
        'word/document.xml'            => $docXml,
        'word/_rels/document.xml.rels' => $wordRels,
        'word/styles.xml'              => $styles,
    );

    $zipData = '';
    $centralDir = '';
    $offset = 0;
    $count = 0;
    $dosTime = 0x4A210000;
    foreach ($zipFiles as $fname => $fdata) {
        $fnl  = strlen($fname);
        $size = strlen($fdata);
        $crc  = crc32($fdata);
        $lh   = pack('V', 0x04034b50) . pack('v', 20) . pack('v', 0) . pack('v', 0)
            . pack('V', $dosTime) . pack('V', $crc) . pack('V', $size) . pack('V', $size)
            . pack('v', $fnl) . pack('v', 0) . $fname;
        $centralDir .= pack('V', 0x02014b50) . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 0)
            . pack('V', $dosTime) . pack('V', $crc) . pack('V', $size) . pack('V', $size)
            . pack('v', $fnl) . pack('v', 0) . pack('v', 0)
            . pack('v', 0) . pack('v', 0) . pack('V', 0) . pack('V', $offset) . $fname;
        $zipData .= $lh . $fdata;
        $offset  += strlen($lh) + $size;
        $count++;
    }
    $cdSize = strlen($centralDir);
    $eocd   = pack('V', 0x06054b50) . pack('v', 0) . pack('v', 0)
        . pack('v', $count) . pack('v', $count)
        . pack('V', $cdSize) . pack('V', $offset) . pack('v', 0);
    $docxBin = $zipData . $centralDir . $eocd;

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="student_report_' . date('Y-m-d') . '.docx"');
    header('Content-Length: ' . strlen($docxBin));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $docxBin;
    exit;
}

// ─── FILTERS & QUERY ─────────────────────────────────────────────────────────
$search        = isset($_GET['search'])  ? trim($_GET['search'])  : '';
$gradeFilter   = isset($_GET['grade'])   ? $_GET['grade']         : '';
$genderFilter  = isset($_GET['gender'])  ? $_GET['gender']        : '';
$statusFilter  = isset($_GET['status'])  ? $_GET['status']        : '';
$sectionFilter = isset($_GET['section']) ? $_GET['section']       : '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 20;
$offset        = ($page - 1) * $perPage;

$conditions = [];
$params = [];
$types = '';
if (!empty($search)) {
    $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ?)";
    $sp = "%{$search}%";
    $params[] = $sp;
    $params[] = $sp;
    $params[] = $sp;
    $types .= 'sss';
}
if (!empty($gradeFilter)) {
    $conditions[] = "grade_level=?";
    $params[] = $gradeFilter;
    $types .= 's';
}
if (!empty($genderFilter)) {
    $conditions[] = "gender=?";
    $params[] = $genderFilter;
    $types .= 's';
}
if (!empty($statusFilter)) {
    $conditions[] = "status=?";
    $params[] = $statusFilter;
    $types .= 's';
}
if (!empty($sectionFilter)) {
    $conditions[] = "section=?";
    $params[] = $sectionFilter;
    $types .= 's';
}
$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM students {$whereClause}");
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalFiltered = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = max(1, ceil($totalFiltered / $perPage));

$stmt = $conn->prepare("SELECT s.*,
    COALESCE(
        NULLIF(spd.photo, ''),
        NULLIF(s.profile_image, ''),
        '../assets/img/person/unknown.jpg'
    ) AS effective_photo,
    spd.photo AS profile_photo
    FROM students s
    LEFT JOIN student_profile_data spd ON spd.student_id = s.student_id
    {$whereClause} ORDER BY s.id DESC LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── STATS ────────────────────────────────────────────────────────────────────
$res = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Active'");
$totalActive = $res->fetch_assoc()['c'];
$stats = ['byGrade' => [], 'byGender' => ['Male' => 0, 'Female' => 0]];
$res = $conn->query("SELECT grade_level,COUNT(*) as count FROM students WHERE status='Active' AND grade_level!='' GROUP BY grade_level");
while ($row = $res->fetch_assoc()) $stats['byGrade'][] = $row;
$res = $conn->query("SELECT gender,COUNT(*) as count FROM students WHERE status='Active' GROUP BY gender");
while ($row = $res->fetch_assoc()) if (isset($stats['byGender'][$row['gender']])) $stats['byGender'][$row['gender']] = $row['count'];
$gradeCounts = ['Grade 7' => 0, 'Grade 8' => 0, 'Grade 9' => 0, 'Grade 10' => 0];
foreach ($stats['byGrade'] as $g) if (isset($gradeCounts[$g['grade_level']])) $gradeCounts[$g['grade_level']] = $g['count'];
$sectionRows = $conn->query("SELECT DISTINCT section FROM students WHERE section!='' ORDER BY section");
$allSections = [];
while ($row = $sectionRows->fetch_assoc()) $allSections[] = $row['section'];
$grades = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'];
if (empty($allSections)) $allSections = ['Grade 7 - Rizal', 'Grade 7 - Bonifacio', 'Grade 8 - Mabini', 'Grade 8 - Luna', 'Grade 9 - Aguinaldo', 'Grade 9 - Quezon', 'Grade 10 - Marcos', 'Grade 10 - Laurel'];

// Grade chart max
$gradeCnt = array_column($stats['byGrade'], 'count') ?: [];
$maxCnt = !empty($gradeCnt) ? max($gradeCnt) : 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records — School Admin</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="admin_assets/cs/student.css">
    <link rel="stylesheet" href="../overall_body.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════
   BASE
══════════════════════════════════════════ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* ══════════════════════════════════════════
   ADD STUDENT MODAL — REDESIGNED
══════════════════════════════════════════ */

        /* Import Sora for the add modal heading */
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap');

        textarea.form-input {
            resize: vertical;
            min-height: 60px;
            font-family: inherit;
        }

        /* Wider modal for the add form */
        #add-student-modal {
            align-items: flex-start;
            padding: 20px;
            overflow-y: auto;
        }

        #add-student-modal .modal-box {
            max-width: 720px;
            width: 100%;
            border-radius: 24px;
            overflow: visible;
            background: transparent;
            box-shadow: none;
            /* No max-height here — let inner card control it */
            margin: auto;
        }

        /* Inner card — fixed height with internal scroll */
        #add-student-modal .modal-inner {
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(15, 14, 23, .22), 0 0 0 1px rgba(15, 14, 23, .06);
            display: flex;
            flex-direction: column;
        }

        /* ── Hero Header ── */
        #add-student-modal .modal-hero {
            background: linear-gradient(135deg, #0d2b1e 0%, #1a4733 50%, #2a6347 100%);
            padding: 28px 32px 22px;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        #add-student-modal .modal-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 80% 50%, rgba(180, 215, 100, .20) 0%, transparent 70%);
            pointer-events: none;
        }

        #add-student-modal .modal-hero::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 1px solid rgba(139, 124, 248, .2);
        }

        #add-student-modal .hero-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            position: relative;
            z-index: 5;
        }

        #add-student-modal .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(60, 120, 90, .25);
            border: 1px solid rgba(120, 200, 150, .35);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #a8e6c0;
            margin-bottom: 10px;
        }

        #add-student-modal .hero-title {
            font-family: 'Sora', 'DM Sans', sans-serif;
            font-size: 1.55rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -.03em;
            line-height: 1.1;
            margin-bottom: 6px;
        }

        #add-student-modal .hero-sub {
            font-size: .8rem;
            color: rgba(255, 255, 255, .5);
            font-weight: 400;
        }

        #add-student-modal .hero-close {
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 10px;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, .7);
            cursor: pointer;
            font-size: .9rem;
            transition: background .15s, color .15s;
            flex-shrink: 0;
            position: relative;
            z-index: 10;
        }

        #add-student-modal .hero-close:hover {
            background: rgba(255, 255, 255, .18);
            color: #fff;
        }

        /* ── Step tabs ── */
        #add-student-modal .step-tabs {
            display: flex;
            gap: 0;
            margin-top: 20px;
            position: relative;
        }

        #add-student-modal .step-tab {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: background .15s;
            position: relative;
            z-index: 1;
        }

        #add-student-modal .step-tab:hover {
            background: rgba(255, 255, 255, .07);
        }

        #add-student-modal .step-tab.active {
            background: rgba(139, 124, 248, .18);
        }

        #add-student-modal .step-num {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            font-weight: 700;
            background: rgba(255, 255, 255, .12);
            color: rgba(255, 255, 255, .5);
            flex-shrink: 0;
            transition: background .2s, color .2s;
        }

        #add-student-modal .step-tab.active .step-num {
            background: #3c785a;
            color: #fff;
        }

        #add-student-modal .step-tab.done .step-num {
            background: #22c55e;
            color: #fff;
        }

        #add-student-modal .step-info {
            display: flex;
            flex-direction: column;
        }

        #add-student-modal .step-label {
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .4);
            line-height: 1;
        }

        #add-student-modal .step-tab.active .step-label {
            color: #a8e6c0;
        }

        #add-student-modal .step-name {
            font-size: .78rem;
            font-weight: 600;
            color: rgba(255, 255, 255, .55);
            margin-top: 2px;
        }

        #add-student-modal .step-tab.active .step-name {
            color: #fff;
        }

        /* ── Scrollable body ── */
        #add-student-modal .modal-scroll {
            overflow: visible;
            scroll-behavior: smooth;
        }

        /* ── Step panels ── */
        #add-student-modal .step-panel {
            display: none;
            padding: 28px 32px 8px;
            animation: stepFadeIn .22s ease;
        }

        #add-student-modal .step-panel.active {
            display: block;
        }

        @keyframes stepFadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── Section heading ── */
        .form-section-heading {
            font-size: .68rem;
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

        .form-section-heading i {
            width: 22px;
            height: 22px;
            background: #d4f0e2;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            color: #2d5c43;
        }

        /* ── Redesigned grid ── */
        #add-student-modal .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        #add-student-modal .form-grid-full {
            grid-column: span 2;
        }

        #add-student-modal .form-grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        /* ── Floating label inputs ── */
        #add-student-modal .field-wrap {
            position: relative;
            display: flex;
            flex-direction: column;
        }

        #add-student-modal .field-label {
            font-size: .7rem;
            font-weight: 700;
            color: #8b89a0;
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        #add-student-modal .field-label .req {
            color: #f43f5e;
            font-size: .85em;
        }

        #add-student-modal .field-input,
        #add-student-modal .field-select,
        #add-student-modal .field-textarea {
            font-family: inherit;
            font-size: .875rem;
            font-weight: 500;
            color: #0f0e17;
            background: #f4faf7;
            border: 1.5px solid #d5ebe0;
            border-radius: 12px;
            padding: 11px 14px;
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
            width: 100%;
        }

        #add-student-modal .field-input::placeholder,
        #add-student-modal .field-textarea::placeholder {
            color: #c5c3d6;
            font-weight: 400;
        }

        #add-student-modal .field-input:focus,
        #add-student-modal .field-select:focus,
        #add-student-modal .field-textarea:focus {
            border-color: #3c785a;
            box-shadow: 0 0 0 4px rgba(60, 120, 90, .12);
            background: #fff;
        }

        #add-student-modal .field-input:not(:placeholder-shown):valid,
        #add-student-modal .field-select:valid:not([value=""]) {
            border-color: #5a9478;
            background: #fdfcff;
        }

        #add-student-modal .field-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%233c785a' stroke-width='1.8' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
            cursor: pointer;
        }

        #add-student-modal .field-textarea {
            resize: vertical;
            min-height: 74px;
            font-family: inherit;
            line-height: 1.5;
        }

        #add-student-modal .field-hint {
            font-size: .7rem;
            color: #a8a6bc;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ── Photo upload zone ── */
        #add-student-modal .photo-zone {
            border: 2px dashed #d4d0ee;
            border-radius: 16px;
            padding: 28px 20px;
            text-align: center;
            background: #faf8ff;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            position: relative;
        }

        #add-student-modal .photo-zone:hover {
            border-color: #3c785a;
            background: #edf8f3;
        }

        #add-student-modal .photo-zone.has-photo {
            border-style: solid;
            border-color: #3c785a;
        }

        #add-student-modal .photo-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        #add-student-modal .photo-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3c785a;
            display: none;
            margin: 0 auto 10px;
        }

        #add-student-modal .photo-icon {
            width: 56px;
            height: 56px;
            background: #d4f0e2;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #2d5c43;
            margin: 0 auto 12px;
        }

        #add-student-modal .photo-zone-title {
            font-size: .88rem;
            font-weight: 700;
            color: #3d3b52;
            margin-bottom: 4px;
        }

        #add-student-modal .photo-zone-sub {
            font-size: .74rem;
            color: #a8a6bc;
        }

        /* ── Radio cards for gender ── */
        #add-student-modal .radio-group {
            display: flex;
            gap: 10px;
        }

        #add-student-modal .radio-card {
            flex: 1;
            position: relative;
        }

        #add-student-modal .radio-card input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        #add-student-modal .radio-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 14px 10px;
            border: 1.5px solid #e8e5f5;
            border-radius: 12px;
            background: #f8f7ff;
            cursor: pointer;
            transition: all .15s;
            font-size: .82rem;
            font-weight: 600;
            color: #6b6a7a;
        }

        #add-student-modal .radio-label i {
            font-size: 1.2rem;
        }

        #add-student-modal .radio-card input:checked+.radio-label {
            border-color: #3c785a;
            background: #e8f5ee;
            color: #1a4733;
            box-shadow: 0 0 0 3px rgba(60, 120, 90, .15);
        }

        #add-student-modal .radio-label:hover {
            border-color: #5a9478;
            background: #f5f2ff;
        }

        /* ── Modal footer redesign ── */
        #add-student-modal .modal-footer-new {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 32px 24px;
            border-top: 1px solid #e0f0e8;
            gap: 12px;
            flex-shrink: 0;
            background: #fff;
        }

        #add-student-modal .footer-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #add-student-modal .step-dots {
            display: flex;
            gap: 5px;
        }

        #add-student-modal .step-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #e0ddf0;
            transition: background .2s, width .2s;
        }

        #add-student-modal .step-dot.active {
            background: #3c785a;
            width: 18px;
            border-radius: 3px;
        }

        #add-student-modal .btn-step-prev {
            background: #f4f3f0;
            color: #0f0e17;
            border: none;
            border-radius: 12px;
            padding: 11px 20px;
            font-family: inherit;
            font-size: .84rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background .15s;
        }

        #add-student-modal .btn-step-prev:hover {
            background: #e8e6e0;
        }

        #add-student-modal .btn-step-next {
            background: linear-gradient(135deg, #2d5c43, #3c785a);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 11px 24px;
            font-family: inherit;
            font-size: .84rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: opacity .15s, box-shadow .15s;
            box-shadow: 0 4px 16px rgba(44, 92, 67, .35);
        }

        #add-student-modal .btn-step-next:hover {
            opacity: .9;
            box-shadow: 0 6px 20px rgba(44, 92, 67, .45);
        }

        #add-student-modal .btn-save-final {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 11px 26px;
            font-family: inherit;
            font-size: .84rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: opacity .15s, box-shadow .15s;
            box-shadow: 0 4px 16px rgba(5, 150, 105, .3);
        }

        #add-student-modal .btn-save-final:hover {
            opacity: .9;
            box-shadow: 0 6px 20px rgba(5, 150, 105, .4);
        }

        /* ── Progress bar ── */
        #add-student-modal .progress-bar-wrap {
            height: 3px;
            background: rgba(255, 255, 255, .1);
            border-radius: 0;
            overflow: hidden;
        }

        #add-student-modal .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3c785a, #7dba9a);
            border-radius: 0 2px 2px 0;
            transition: width .35s cubic-bezier(.34, 1.56, .64, 1);
        }

        .page-content {
            font-family: 'DM Sans', sans-serif;
            background: #f4f3f0;
            min-height: 100vh;
            padding: 28px 32px 56px;
            color: #0f0e17;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Force .main to consume all remaining width beside sidebar */
        .main {
            flex: 1 1 0 !important;
            min-width: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
            overflow-x: hidden;
        }

        /* ── Page Header ── */
        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .page-header-left {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .page-header-eyebrow {
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #2d5c43;
        }

        .page-header-title {
            font-size: 1.7rem;
            font-weight: 700;
            color: #0f0e17;
            letter-spacing: -.025em;
            line-height: 1.1;
        }

        .page-header-sub {
            font-size: .82rem;
            color: #9190a0;
            margin-top: 2px;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: #1a4733;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 10px 18px;
            font-family: inherit;
            font-size: .84rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background .15s, box-shadow .15s;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: #2d5c43;
            box-shadow: 0 4px 14px rgba(26, 71, 51, .3);
        }

        .btn-secondary {
            background: #fff;
            color: #0f0e17;
            border: 1px solid #e4e2de;
            border-radius: 10px;
            padding: 10px 16px;
            font-family: inherit;
            font-size: .84rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background .15s;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #f4f3f0;
        }

        .btn-ghost {
            background: transparent;
            color: #2d5c43;
            border: none;
            font-family: inherit;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            transition: background .15s;
        }

        .btn-ghost:hover {
            background: rgba(139, 124, 248, .08);
        }

        /* ══════════════════════════════════════════
   BENTO GRID
══════════════════════════════════════════ */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 14px;
        }

        .bento-cell {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e8e6e1;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
            transition: box-shadow .2s;
            overflow: hidden;
        }

        .bento-cell:hover {
            box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
        }

        /* ── Row 1: Stat cards (3 col each × 4) ── */
        .cell-stat {
            grid-column: span 3;
            padding: 22px 22px 20px;
            position: relative;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-bottom: 16px;
            flex-shrink: 0;
        }

        .stat-value {
            font-size: 2.1rem;
            font-weight: 700;
            letter-spacing: -.04em;
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: .72rem;
            font-weight: 600;
            color: #9190a0;
            text-transform: uppercase;
            letter-spacing: .09em;
        }

        .stat-bg-icon {
            position: absolute;
            right: -8px;
            bottom: -10px;
            font-size: 5.5rem;
            opacity: .045;
            pointer-events: none;
        }

        .stat-dark {
            background: #1a4733;
            border-color: #1a4733;
        }

        .stat-dark .stat-icon {
            background: rgba(255, 255, 255, .1);
            color: #fff;
        }

        .stat-dark .stat-value {
            color: #fff;
        }

        .stat-dark .stat-label {
            color: rgba(255, 255, 255, .4);
        }

        .stat-male .stat-icon {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .stat-male .stat-value {
            color: #1d4ed8;
        }

        .stat-female .stat-icon {
            background: #fce7f3;
            color: #db2777;
        }

        .stat-female .stat-value {
            color: #db2777;
        }

        .stat-grade .stat-icon {
            background: #dcfce7;
            color: #15803d;
        }

        .stat-grade .stat-value {
            color: #15803d;
        }

        /* ── Row 2: Charts (8+4) ── */
        .cell-chart-main {
            grid-column: span 8;
            padding: 24px 24px 20px;
        }

        .cell-chart-side {
            grid-column: span 4;
            padding: 24px 22px 20px;
            display: flex;
            flex-direction: column;
        }

        .chart-label {
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #9190a0;
            margin-bottom: 4px;
        }

        .chart-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f0e17;
            margin-bottom: 20px;
            letter-spacing: -.015em;
        }

        /* Grade bars */
        .grade-bars {
            display: flex;
            align-items: flex-end;
            gap: 20px;
            height: 140px;
            padding-bottom: 0;
        }

        .grade-bar-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .grade-bar-track {
            width: 100%;
            flex: 1;
            display: flex;
            align-items: flex-end;
        }

        .grade-bar-fill {
            width: 100%;
            background: linear-gradient(180deg, #3c785a 0%, #1a4733 100%);
            border-radius: 8px 8px 4px 4px;
            position: relative;
            min-height: 4px;
            transition: height .6s cubic-bezier(.34, 1.56, .64, 1);
        }

        .grade-bar-fill:nth-child(1) {
            background: linear-gradient(180deg, #3c785a, #1a4733);
        }

        .grade-bar-val {
            position: absolute;
            top: -22px;
            left: 50%;
            transform: translateX(-50%);
            font-size: .72rem;
            font-weight: 700;
            color: #0f0e17;
            white-space: nowrap;
        }

        .grade-bar-name {
            font-size: .72rem;
            font-weight: 600;
            color: #9190a0;
        }

        /* Gender donut */
        .gender-donut-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            flex: 1;
            justify-content: center;
        }

        .donut-svg {
            width: 120px;
            height: 120px;
        }

        .donut-center {
            font-family: 'DM Sans', sans-serif;
        }

        .gender-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }

        .legend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: .8rem;
        }

        .legend-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-name {
            color: #6b6a7a;
            font-weight: 500;
            flex: 1;
        }

        .legend-val {
            font-weight: 700;
            color: #0f0e17;
        }

        /* ── Row 3: Filters (span 12) ── */
        .cell-filters {
            grid-column: span 12;
            padding: 16px 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-search-wrap {
            position: relative;
            flex: 1;
            min-width: 200px;
        }

        .filter-search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0aec0;
            font-size: .8rem;
            pointer-events: none;
        }

        .filter-search-input {
            font-family: inherit;
            font-size: .84rem;
            border: 1px solid #e4e2de;
            border-radius: 10px;
            padding: 9px 14px 9px 34px;
            width: 100%;
            background: #f8f7f4;
            color: #0f0e17;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .filter-search-input:focus {
            border-color: #2d5c43;
            box-shadow: 0 0 0 3px rgba(139, 124, 248, .12);
            background: #fff;
        }

        .filter-search-input::placeholder {
            color: #c4c2ce;
        }

        .filter-select {
            font-family: inherit;
            font-size: .82rem;
            padding: 9px 32px 9px 12px;
            border: 1px solid #e4e2de;
            border-radius: 10px;
            background: #f8f7f4 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%239190a0' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 10px center;
            color: #0f0e17;
            cursor: pointer;
            appearance: none;
            outline: none;
            transition: border-color .15s;
        }

        .filter-select:focus {
            border-color: #2d5c43;
        }

        .filter-divider {
            width: 1px;
            height: 28px;
            background: #e4e2de;
            flex-shrink: 0;
        }

        /* ── Row 4: Table (span 12) ── */
        .cell-table {
            grid-column: span 12;
        }

        .table-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px 14px;
            border-bottom: 1px solid #f4f3f0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-topbar-title {
            font-size: .92rem;
            font-weight: 700;
            color: #0f0e17;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-topbar-title i {
            color: #2d5c43;
            font-size: .88rem;
        }

        .table-count-pill {
            background: #f4f3f0;
            color: #6b6a7a;
            font-size: .72rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: .03em;
        }

        .student-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .855rem;
        }

        .student-table thead tr {
            background: #fafaf8;
            border-bottom: 1px solid #eeecE8;
        }

        .student-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #9190a0;
        }

        .student-table td {
            padding: 13px 16px;
            border-bottom: 1px solid #f4f3f0;
            color: #2d2c3e;
            vertical-align: middle;
        }

        .student-table tbody tr:last-child td {
            border-bottom: none;
        }

        .student-table tbody tr {
            transition: background .12s;
            cursor: pointer;
        }

        .student-table tbody tr:hover td {
            background: #faf8ff;
        }

        .student-name-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #f0eef8;
            flex-shrink: 0;
        }

        .student-name {
            font-weight: 600;
            color: #0f0e17;
            font-size: .855rem;
        }

        .student-id-mono {
            font-family: 'DM Mono', monospace;
            font-size: .78rem;
            background: #f4f3f0;
            color: #5a5870;
            padding: 3px 9px;
            border-radius: 6px;
            border: 1px solid #e8e6e1;
        }

        .grade-chip {
            background: #ede9fe;
            color: #6d28d9;
            font-size: .72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 6px;
        }

        /* Status badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: .71rem;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            opacity: .7;
        }

        .badge-active {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-transferred {
            background: #fef9c3;
            color: #a16207;
        }

        .badge-graduated {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-dropped {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Action buttons */
        .action-btns {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: 1px solid #e4e2de;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            cursor: pointer;
            text-decoration: none;
            transition: all .14s;
            color: #6b6a7a;
        }

        .action-btn.edit:hover {
            background: #ede9fe;
            border-color: #c4b5fd;
            color: #6d28d9;
        }

        .action-btn.delete:hover {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #dc2626;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 56px 20px;
            color: #b0aec0;
        }

        .empty-state i {
            font-size: 2.4rem;
            display: block;
            margin-bottom: 12px;
            color: #dddae8;
        }

        .empty-state p {
            font-size: .88rem;
        }

        /* ── Row 5: Pagination ── */
        .cell-pagination {
            grid-column: span 12;
            padding: 14px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .page-info {
            font-size: .78rem;
            color: #9190a0;
            font-weight: 500;
        }

        .pagination-btns {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-btn {
            border: 1px solid #e4e2de;
            background: #fff;
            color: #374151;
            padding: 7px 13px;
            border-radius: 9px;
            cursor: pointer;
            font-size: .8rem;
            font-weight: 600;
            text-decoration: none;
            font-family: inherit;
            transition: all .14s;
            display: inline-flex;
            align-items: center;
        }

        .page-btn:hover {
            background: #f4f3f0;
        }

        .page-btn.active {
            background: #0f0e17;
            color: #fff;
            border-color: #0f0e17;
        }

        /* ══════════════════════════════════════════
   ALERTS
══════════════════════════════════════════ */
        .alert-wrap {
            grid-column: span 12;
        }

        .alert {
            padding: 12px 18px;
            border-radius: 12px;
            font-size: .84rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* ══════════════════════════════════════════
   MODALS
══════════════════════════════════════════ */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 14, 23, .5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }

        .modal[style*="flex"] {
            display: flex !important;
            animation: fadeIn .18s ease;
        }

        .modal-box {
            background: #fff;
            border-radius: 20px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .18);
            animation: scaleIn .2s ease;
            overflow: hidden;
        }

        .modal-box.modal-lg {
            max-width: 580px;
        }

        .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px 16px;
            border-bottom: 1px solid #f0eee9;
        }

        .modal-head h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #0f0e17;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-head h3 i {
            color: #2d5c43;
        }

        .modal-close {
            background: #f4f3f0;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            color: #6b6a7a;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .15s;
        }

        .modal-close:hover {
            background: #eeecE8;
        }

        .modal-body {
            padding: 20px 24px;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-grid-full {
            grid-column: span 2;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-label {
            font-size: .73rem;
            font-weight: 600;
            color: #5a5870;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .form-label .req {
            color: #ef4444;
        }

        .form-input,
        .form-select {
            font-family: inherit;
            font-size: .84rem;
            border: 1px solid #e4e2de;
            border-radius: 10px;
            padding: 9px 12px;
            background: #fafaf8;
            color: #0f0e17;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            width: 100%;
        }

        .form-input:focus,
        .form-select:focus {
            border-color: #2d5c43;
            box-shadow: 0 0 0 3px rgba(139, 124, 248, .1);
            background: #fff;
        }

        .form-input::placeholder {
            color: #c4c2ce;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%239190a0' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
            cursor: pointer;
        }

        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 24px 20px;
            border-top: 1px solid #f0eee9;
        }

        .grad-warning {
            background: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 11px 14px;
            margin-bottom: 16px;
            font-size: .82rem;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* image preview */
        .img-preview-row {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .img-preview {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #e8e6e1;
        }

        .img-upload-btn {
            background: #f4f3f0;
            border: 1px solid #e4e2de;
            border-radius: 10px;
            padding: 8px 14px;
            font-family: inherit;
            font-size: .8rem;
            font-weight: 600;
            color: #5a5870;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background .15s;
        }

        .img-upload-btn:hover {
            background: #eeece8;
        }

        /* CSV drop */
        .csv-drop {
            border: 2px dashed #e4e2de;
            border-radius: 14px;
            padding: 32px 24px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }

        .csv-drop:hover,
        .csv-drop.drag-over {
            border-color: #2d5c43;
            background: #faf8ff;
        }

        .csv-drop i {
            font-size: 2rem;
            color: #c4c2ce;
            margin-bottom: 8px;
            display: block;
        }

        .csv-drop-text {
            font-size: .84rem;
            color: #6b6a7a;
        }

        .csv-drop-hint {
            font-size: .74rem;
            color: #b0aec0;
            margin-top: 4px;
        }

        .csv-filename {
            color: #7c3aed;
            font-size: .82rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .import-summary {
            background: #f8f7f4;
            border-radius: 10px;
            padding: 14px 16px;
            margin-top: 14px;
        }

        .sum-row {
            display: flex;
            justify-content: space-between;
            font-size: .84rem;
            padding: 5px 0;
            border-bottom: 1px solid #eeece8;
        }

        .sum-row:last-child {
            border: none;
        }

        /* Profile modal */
        .profile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 14, 23, .5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .profile-overlay.open {
            display: flex;
            animation: fadeIn .18s ease;
        }

        .profile-box {
            background: #fff;
            border-radius: 20px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .18);
            animation: scaleIn .2s ease;
            overflow: hidden;
            position: relative;
        }

        .profile-banner {
            height: 72px;
            background: linear-gradient(135deg, #0f0e17 0%, #3730a3 100%);
            position: relative;
        }

        .profile-avatar-wrap {
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
        }

        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .15);
            display: block;
        }

        .profile-close-btn {
            position: absolute;
            top: 10px;
            right: 12px;
            background: rgba(255, 255, 255, .15);
            border: none;
            color: #fff;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            font-size: .9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-body {
            padding: 44px 24px 20px;
            text-align: center;
        }

        .profile-name {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f0e17;
            margin-bottom: 6px;
        }

        .profile-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 18px 0 20px;
            text-align: left;
        }

        .profile-info-item {
            background: #fafaf8;
            border-radius: 10px;
            padding: 10px 12px;
        }

        .profile-info-item label {
            font-size: .67rem;
            font-weight: 700;
            color: #9190a0;
            text-transform: uppercase;
            letter-spacing: .08em;
            display: block;
            margin-bottom: 3px;
        }

        .profile-info-item span {
            font-size: .855rem;
            font-weight: 600;
            color: #0f0e17;
        }

        .profile-footer {
            padding: 0 24px 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        @keyframes fadeIn {
            from {
                opacity: 0
            }

            to {
                opacity: 1
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(.93)
            }

            to {
                opacity: 1;
                transform: scale(1)
            }
        }

        /* Responsive */
        @media (max-width: 1100px) {
            .cell-stat {
                grid-column: span 6;
            }

            .cell-chart-main {
                grid-column: span 12;
            }

            .cell-chart-side {
                grid-column: span 12;
                flex-direction: row;
                gap: 24px;
            }

            .gender-donut-wrap {
                flex-direction: row;
            }
        }

        @media (max-width: 700px) {
            .page-content {
                padding: 16px 14px 48px;
            }

            .bento-grid {
                gap: 10px;
            }

            .cell-stat {
                grid-column: span 6;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-grid-full {
                grid-column: span 1;
            }
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

    <section class="page-content">

        <!-- Top Bar -->
        <div class="page-header">
            <div class="page-header-left">
                <span class="page-header-eyebrow"><i class="fas fa-graduation-cap"></i>&ensp;Records Management</span>
                <h1 class="page-header-title">Student Records</h1>
                <p class="page-header-sub">Manage student information and enrollment statistics</p>
            </div>
            <div class="page-header-actions">
                <a href="student_logs.php" class="btn-ghost"><i class="fas fa-history"></i> Activity Logs</a>
                <button id="export-report-btn" class="btn-secondary"><i class="fas fa-file-export"></i> Export Report</button>
                <button id="add-student-btn" class="btn-primary"><i class="fas fa-plus"></i> Add Student</button>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bento-grid" style="margin-bottom:10px;">
                <div class="alert-wrap">
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']);
                                                                                            unset($_SESSION['success']); ?></div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bento-grid" style="margin-bottom:10px;">
                <div class="alert-wrap">
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']);
                                                                                                unset($_SESSION['error']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════
         BENTO GRID
    ═══════════════════════════════════════ -->
        <div class="bento-grid">

            <!-- ── Stat: Total Active ── -->
            <div class="bento-cell cell-stat stat-dark">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value" data-target="<?php echo $totalActive; ?>">0</div>
                <div class="stat-label">Total Active</div>
                <i class="fas fa-users stat-bg-icon"></i>
            </div>

            <!-- ── Stat: Male ── -->
            <div class="bento-cell cell-stat stat-male">
                <div class="stat-icon"><i class="fas fa-mars"></i></div>
                <div class="stat-value" data-target="<?php echo $stats['byGender']['Male']; ?>">0</div>
                <div class="stat-label">Male Students</div>
                <i class="fas fa-mars stat-bg-icon"></i>
            </div>

            <!-- ── Stat: Female ── -->
            <div class="bento-cell cell-stat stat-female">
                <div class="stat-icon"><i class="fas fa-venus"></i></div>
                <div class="stat-value" data-target="<?php echo $stats['byGender']['Female']; ?>">0</div>
                <div class="stat-label">Female Students</div>
                <i class="fas fa-venus stat-bg-icon"></i>
            </div>

            <!-- ── Stat: Grade totals (combined) ── -->
            <div class="bento-cell cell-stat stat-grade">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-value" data-target="<?php echo array_sum($gradeCounts); ?>">0</div>
                <div class="stat-label">Across 4 Grades</div>
                <i class="fas fa-layer-group stat-bg-icon"></i>
            </div>

            <!-- ── Chart: Grade Enrollment ── -->
            <div class="bento-cell cell-chart-main">
                <div class="chart-label">Enrollment</div>
                <div class="chart-title">Students by Grade Level</div>
                <div class="grade-bars" id="grade-chart">
                    <?php foreach ($grades as $grade):
                        $cnt = 0;
                        foreach ($stats['byGrade'] as $g) if ($g['grade_level'] === $grade) {
                            $cnt = $g['count'];
                            break;
                        }
                        $h = $maxCnt > 0 ? ($cnt / $maxCnt) * 100 : 0;
                        $colors = ['Grade 7' => ['#8b7cf8', '#6d28d9'], 'Grade 8' => ['#38bdf8', '#0284c7'], 'Grade 9' => ['#4ade80', '#15803d'], 'Grade 10' => ['#fb923c', '#c2410c']];
                        [$c1, $c2] = $colors[$grade] ?? ['#8b7cf8', '#6d28d9'];
                    ?>
                        <div class="grade-bar-group">
                            <div class="grade-bar-track">
                                <div class="grade-bar-fill" style="height:<?php echo max(4, $h); ?>%;background:linear-gradient(180deg,<?php echo $c1; ?>,<?php echo $c2; ?>);" data-h="<?php echo $h; ?>">
                                    <span class="grade-bar-val"><?php echo $cnt; ?></span>
                                </div>
                            </div>
                            <div class="grade-bar-name"><?php echo $grade; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Chart: Gender Distribution ── -->
            <div class="bento-cell cell-chart-side">
                <div class="chart-label">Distribution</div>
                <div class="chart-title" style="margin-bottom:10px;">Gender Split</div>
                <div class="gender-donut-wrap">
                    <?php
                    // Real counts straight from DB query results
                    $male   = (int)($stats['byGender']['Male']   ?? 0);
                    $female = (int)($stats['byGender']['Female'] ?? 0);
                    $realTotal = $male + $female; // actual total, may be 0

                    // Safe divisor — only used for arc math, never shown as the total
                    $divisor = $realTotal > 0 ? $realTotal : 1;

                    // Percentages: show 0% for both when no students exist
                    $malePct   = $realTotal > 0 ? round($male   / $divisor * 100) : 0;
                    $femalePct = $realTotal > 0 ? (100 - $malePct)                : 0;

                    // SVG donut arc math (circumference of r=46 circle)
                    $r    = 46;
                    $circ = 2 * M_PI * $r; // ≈ 289.03

                    if ($realTotal > 0) {
                        $maleDash   = $circ * ($male   / $divisor);
                        $femaleDash = $circ * ($female / $divisor);
                    } else {
                        // Empty state: render a single grey ring, no colored arcs
                        $maleDash   = 0;
                        $femaleDash = 0;
                    }
                    ?>
                    <svg class="donut-svg" viewBox="0 0 110 110">
                        <!-- Background track (always visible) -->
                        <circle cx="55" cy="55" r="46" fill="none" stroke="#f4f3f0" stroke-width="12" />

                        <?php if ($realTotal > 0): ?>
                            <!-- Female arc (drawn first, starts at top) -->
                            <circle cx="55" cy="55" r="46" fill="none" stroke="#db2777" stroke-width="12"
                                stroke-dasharray="<?php echo round($femaleDash, 4) . ' ' . $circ; ?>"
                                stroke-dashoffset="0"
                                transform="rotate(-90 55 55)"
                                style="transition:stroke-dasharray .8s ease;" />
                            <!-- Male arc (offset by female arc length) -->
                            <circle cx="55" cy="55" r="46" fill="none" stroke="#1d4ed8" stroke-width="12"
                                stroke-dasharray="<?php echo round($maleDash, 4) . ' ' . $circ; ?>"
                                stroke-dashoffset="-<?php echo round($femaleDash, 4); ?>"
                                transform="rotate(-90 55 55)"
                                style="transition:stroke-dasharray .8s ease;" />
                        <?php endif; ?>

                        <!-- Centre label: show real total (0 when empty) -->
                        <text x="55" y="52" text-anchor="middle" font-family="DM Sans,sans-serif"
                            font-size="14" font-weight="700"
                            fill="<?php echo $realTotal > 0 ? '#0f0e17' : '#c4c2ce'; ?>">
                            <?php echo $realTotal; ?>
                        </text>
                        <text x="55" y="64" text-anchor="middle" font-family="DM Sans,sans-serif"
                            font-size="8" fill="#9190a0">
                            <?php echo $realTotal === 1 ? 'student' : 'students'; ?>
                        </text>
                    </svg>

                    <div class="gender-legend">
                        <div class="legend-row">
                            <span class="legend-dot" style="background:#1d4ed8;"></span>
                            <span class="legend-name">Male</span>
                            <span class="legend-val">
                                <?php echo $male; ?>
                                <small style="color:#9190a0;font-weight:500;">(<?php echo $malePct; ?>%)</small>
                            </span>
                        </div>
                        <div class="legend-row">
                            <span class="legend-dot" style="background:#db2777;"></span>
                            <span class="legend-name">Female</span>
                            <span class="legend-val">
                                <?php echo $female; ?>
                                <small style="color:#9190a0;font-weight:500;">(<?php echo $femalePct; ?>%)</small>
                            </span>
                        </div>
                        <?php if ($realTotal === 0): ?>
                            <p style="font-size:.73rem;color:#c4c2ce;text-align:center;margin-top:6px;">No active students yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Filter Bar ── -->
            <div class="bento-cell cell-filters">
                <div class="filter-search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-input" class="filter-search-input"
                        placeholder="Search by name or ID…"
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-divider"></div>
                <select id="grade-filter" class="filter-select">
                    <option value="">All Grades</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $gradeFilter === $g ? 'selected' : ''; ?>><?php echo htmlspecialchars($g); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="gender-filter" class="filter-select">
                    <option value="">All Genders</option>
                    <option value="Male" <?php echo $genderFilter === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $genderFilter === 'Female' ? 'selected' : ''; ?>>Female</option>
                </select>
                <select id="section-filter" class="filter-select">
                    <option value="">All Sections</option>
                    <?php foreach ($allSections as $sec): ?>
                        <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $sectionFilter === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="status-filter" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Transferred" <?php echo $statusFilter === 'Transferred' ? 'selected' : ''; ?>>Transferred</option>
                    <option value="Completers" <?php echo $statusFilter === 'Completers' ? 'selected' : ''; ?>>Completers</option>
                    <option value="Dropped" <?php echo $statusFilter === 'Dropped' ? 'selected' : ''; ?>>Dropped</option>
                </select>
            </div>

            <!-- ── Student Table ── -->
            <div class="bento-cell cell-table">
                <div class="table-topbar">
                    <span class="table-topbar-title">
                        <i class="fas fa-user-graduate"></i>
                        Student Information
                        <span class="table-count-pill"><?php echo $totalFiltered; ?> records</span>
                    </span>
                </div>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>ID Number</th>
                            <th>Grade</th>
                            <th>Section</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="student-table-body">
                        <?php if (count($students) > 0): ?>
                            <?php foreach ($students as $student):
                                $fullName = htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
                                // Prefer the student's own uploaded photo (from profile.php) over admin-uploaded photo
                                $profilePhoto = !empty($student['profile_photo']) ? $student['profile_photo'] : '';
                                $imgSrc = htmlspecialchars($student['effective_photo'] ?? '../assets/img/person/unknown.jpg');
                                $statusVal = $student['status'] ?? 'Active';
                                $badgeClass = match ($statusVal) {
                                    'Active' => 'badge-active',
                                    'Transferred' => 'badge-transferred',
                                    'Completers' => 'badge-completers',
                                    'Dropped' => 'badge-dropped',
                                    default => 'badge-active'
                                };
                            ?>
                                <tr class="student-row"
                                    data-id="<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>"
                                    data-image="<?php echo htmlspecialchars($student['effective_photo'] ?? ''); ?>"
                                    data-profile-photo="<?php echo htmlspecialchars($profilePhoto); ?>"
                                    data-name="<?php echo $fullName; ?>"
                                    data-grade="<?php echo htmlspecialchars($student['grade_level'] ?? ''); ?>"
                                    data-age="<?php echo htmlspecialchars($student['age'] ?? ''); ?>"
                                    data-gender="<?php echo htmlspecialchars($student['gender'] ?? ''); ?>"
                                    data-status="<?php echo htmlspecialchars($statusVal); ?>"
                                    data-section="<?php echo htmlspecialchars($student['section'] ?? ''); ?>"
                                    data-added="<?php echo htmlspecialchars($student['created_at'] ?? ''); ?>">
                                    <td>
                                        <div class="student-name-cell">
                                            <img src="<?php echo $imgSrc; ?>" alt="<?php echo $fullName; ?>"
                                                class="student-avatar" onerror="this.src='../assets/img/person/unknown.jpg'">
                                            <span class="student-name"><?php echo $fullName; ?></span>
                                        </div>
                                    </td>
                                    <td><span class="student-id-mono"><?php echo htmlspecialchars($student['student_id'] ?? ''); ?></span></td>
                                    <td><span class="grade-chip"><?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?></span></td>
                                    <td><?php echo htmlspecialchars($student['section'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($student['age'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></td>
                                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($statusVal); ?></span></td>
                                    <td class="actions" onclick="event.stopPropagation()">
                                        <div class="action-btns">
                                            <a href="#" class="action-btn edit" title="Edit"
                                                data-id="<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>"
                                                data-image="<?php echo htmlspecialchars($student['effective_photo'] ?? ''); ?>"
                                                data-status="<?php echo htmlspecialchars($statusVal); ?>">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <a href="#" class="action-btn delete" title="Delete"
                                                data-id="<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash"></i>
                                        <p>No students found. Click "Add Student" to get started.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ── Pagination ── -->
            <?php if ($totalPages > 1): ?>
                <div class="bento-cell cell-pagination">
                    <span class="page-info">
                        Showing <?php echo ($offset + 1) . '-' . min($offset + $perPage, $totalFiltered); ?> of <?php echo number_format($totalFiltered); ?> students
                    </span>
                    <div class="pagination-btns">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                        <?php endif; ?>
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>" class="page-btn <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div><!-- /bento-grid -->
    </section>

    <!-- ══ PROFILE MODAL ══ -->
    <div class="profile-overlay" id="profile-modal">
        <div class="profile-box">
            <div class="profile-banner">
                <div class="profile-avatar-wrap">
                    <img id="pm-photo" src="../assets/img/person/unknown.jpg" class="profile-avatar" alt="Profile" onerror="this.src='../assets/img/person/unknown.jpg'">
                </div>
                <button class="profile-close-btn" id="profile-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="profile-body">
                <p class="profile-name" id="pm-name"></p>
                <div id="pm-badge-wrap" style="margin-bottom:4px;"></div>
                <div class="profile-info-grid">
                    <div class="profile-info-item"><label>Student ID</label><span id="pm-id"></span></div>
                    <div class="profile-info-item"><label>Grade Level</label><span id="pm-grade"></span></div>
                    <div class="profile-info-item"><label>Section</label><span id="pm-section"></span></div>
                    <div class="profile-info-item"><label>Age</label><span id="pm-age"></span></div>
                    <div class="profile-info-item"><label>Gender</label><span id="pm-gender"></span></div>
                    <div class="profile-info-item"><label>Date Added</label><span id="pm-added"></span></div>
                </div>
            </div>
            <div class="profile-footer">
                <button class="btn-primary" id="pm-edit-btn" style="font-size:.84rem;padding:9px 18px;"><i class="fas fa-pen"></i> Edit Student</button>
                <button class="btn-secondary" id="pm-close-btn" style="font-size:.84rem;padding:9px 18px;">Close</button>
            </div>
        </div>
    </div>

    <!-- ══ ADD STUDENT MODAL — REDESIGNED ══ -->
    <div id="add-student-modal" class="modal">
        <div class="modal-box">
            <div class="modal-inner">
                <!-- Hero Header -->
                <div class="modal-hero">
                    <div class="progress-bar-wrap">
                        <div class="progress-bar-fill" id="add-progress-bar" style="width:25%"></div>
                    </div>
                    <div style="height:18px"></div>
                    <div class="hero-top">
                        <div>
                            <div class="hero-badge"><i class="fas fa-user-plus"></i> New Enrollment</div>
                            <div class="hero-title">Add New Student</div>
                            <div class="hero-sub">Fill in the details across all sections below</div>
                        </div>
                        <button type="button" class="hero-close close-modal"><i class="fas fa-times"></i></button>
                    </div>
                    <!-- Step tabs -->
                    <div class="step-tabs" id="add-step-tabs">
                        <div class="step-tab active" data-step="1">
                            <div class="step-num">1</div>
                            <div class="step-info">
                                <span class="step-label">Step 1</span>
                                <span class="step-name">Identity</span>
                            </div>
                        </div>
                        <div class="step-tab" data-step="2">
                            <div class="step-num">2</div>
                            <div class="step-info">
                                <span class="step-label">Step 2</span>
                                <span class="step-name">Academic</span>
                            </div>
                        </div>
                        <div class="step-tab" data-step="3">
                            <div class="step-num">3</div>
                            <div class="step-info">
                                <span class="step-label">Step 3</span>
                                <span class="step-name">Activities</span>
                            </div>
                        </div>
                        <div class="step-tab" data-step="4">
                            <div class="step-num">4</div>
                            <div class="step-info">
                                <span class="step-label">Step 4</span>
                                <span class="step-name">Photo</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form id="add-student-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div class="modal-scroll">

                        <!-- ── STEP 1: Identity ── -->
                        <div class="step-panel active" id="add-step-1">

                            <div class="form-grid" style="margin-bottom:22px;">
                                <div class="form-grid-full">
                                    <label class="form-section-heading"><i class="fas fa-id-card"></i> Student Name</label>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">First Name <span class="req">*</span></label>
                                    <input type="text" name="first_name" class="field-input" required placeholder="e.g. Juan">
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Middle Initial</label>
                                    <input type="text" name="middle_initial" class="field-input" maxlength="3" placeholder="e.g. D.">
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Last Name <span class="req">*</span></label>
                                    <input type="text" name="last_name" class="field-input" required placeholder="e.g. Dela Cruz">
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Suffix</label>
                                    <input type="text" name="suffix" class="field-input" maxlength="10" placeholder="Jr., III, etc.">
                                </div>
                            </div>

                            <div class="form-grid" style="margin-bottom:22px;">
                                <div class="form-grid-full">
                                    <label class="form-section-heading"><i class="fas fa-fingerprint"></i> Identification</label>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Student ID <span class="req">*</span></label>
                                    <input type="text" name="student_id" class="field-input" required placeholder="e.g. 2025-0001">
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Learner Reference Number (LRN)</label>
                                    <input type="text" name="lrn" class="field-input" maxlength="12" placeholder="12-digit LRN">
                                </div>
                            </div>

                            <div class="form-grid" style="padding-bottom:24px;">
                                <div class="form-grid-full">
                                    <label class="form-section-heading"><i class="fas fa-user"></i> Personal Information</label>
                                </div>
                                <div class="field-wrap form-grid-full">
                                    <label class="field-label">Gender <span class="req">*</span></label>
                                    <div class="radio-group">
                                        <label class="radio-card">
                                            <input type="radio" name="gender" value="Male" required>
                                            <span class="radio-label"><i class="fas fa-mars" style="color:#3b82f6"></i> Male</span>
                                        </label>
                                        <label class="radio-card">
                                            <input type="radio" name="gender" value="Female">
                                            <span class="radio-label"><i class="fas fa-venus" style="color:#ec4899"></i> Female</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Birthdate</label>
                                    <input type="date" name="birthdate" id="add-birthdate" class="field-input" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Age <span class="req">*</span></label>
                                    <input type="number" name="age" id="add-age" class="field-input" required min="5" max="25" placeholder="Auto-filled or enter">
                                </div>
                                <div class="field-wrap form-grid-full">
                                    <label class="field-label">Student / Parent Phone Number</label>
                                    <input type="tel" name="phone_number" class="field-input" placeholder="e.g. 09XX-XXX-XXXX">
                                </div>
                            </div>

                        </div><!-- /step-1 -->

                        <!-- ── STEP 2: Academic ── -->
                        <div class="step-panel" id="add-step-2">

                            <div class="form-grid" style="margin-bottom:22px;">
                                <div class="form-grid-full">
                                    <label class="form-section-heading"><i class="fas fa-graduation-cap"></i> Academic Information</label>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Grade Level <span class="req">*</span></label>
                                    <select name="grade_section" class="field-select" required>
                                        <option value="">Select Grade</option>
                                        <?php for ($g = 1; $g <= 10; $g++): ?>
                                            <option value="Grade <?php echo $g; ?>">Grade <?php echo $g; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Section</label>
                                    <select name="section" class="field-select">
                                        <option value="">Select Section</option>
                                        <?php foreach ($allSections as $sec): ?>
                                            <option value="<?php echo htmlspecialchars($sec); ?>"><?php echo htmlspecialchars($sec); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Enrollment Status <span class="req">*</span></label>
                                    <select name="enrollment_status" class="field-select" required>
                                        <option value="">Select Status</option>
                                        <option value="Enrolled">Enrolled</option>
                                        <option value="Not Yet Enrolled">Not Yet Enrolled</option>
                                        <option value="Re-enrolled">Re-enrolled</option>
                                        <option value="Conditionally Enrolled">Conditionally Enrolled</option>
                                    </select>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Student Status</label>
                                    <select name="status" class="field-select">
                                        <option value="Active">Active</option>
                                        <option value="Transferred">Transferred</option>
                                        <option value="Completers">Completers</option>
                                        <option value="Dropped">Dropped</option>
                                    </select>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Date Enrolled</label>
                                    <input type="date" name="date_enrolled" class="field-input" max="<?php echo date('Y-m-d'); ?>">
                                    <span class="field-hint"><i class="fas fa-info-circle"></i> Leave blank if not yet enrolled</span>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Graduation Year</label>
                                    <input type="number" name="graduation_year" class="field-input" min="2020" max="2040" placeholder="e.g. 2028">
                                </div>
                                <div class="field-wrap form-grid-full">
                                    <label class="field-label">Class Schedule</label>
                                    <textarea name="class_schedule" class="field-textarea" placeholder="e.g. Mon–Fri 7:30AM–4:00PM&#10;Math: Room 201, Tue/Thu 8–9AM..."></textarea>
                                </div>
                            </div>

                        </div><!-- /step-2 -->

                        <!-- ── STEP 3: Activities & Athletics ── -->
                        <div class="step-panel" id="add-step-3">

                            <div class="form-grid" style="margin-bottom:22px;">
                                <div class="form-grid-full">
                                    <label class="form-section-heading"><i class="fas fa-trophy"></i> Achievements & Activities</label>
                                </div>
                                <div class="field-wrap form-grid-full">
                                    <label class="field-label">Honors and Awards</label>
                                    <textarea name="honors_awards" class="field-textarea" placeholder="e.g. With Honors, Best in Math, Academic Excellence Award..."></textarea>
                                </div>
                                <div class="field-wrap form-grid-full">
                                    <label class="field-label">Club Memberships and Organizations</label>
                                    <textarea name="club_memberships" class="field-textarea" placeholder="e.g. Science Club, Student Council, Glee Club..."></textarea>
                                </div>
                                <div class="field-wrap form-grid-full">
                                    <label class="field-label">Sports Team Participation</label>
                                    <textarea name="sports_teams" class="field-textarea" placeholder="e.g. Basketball Varsity, Swimming Team..."></textarea>
                                </div>
                            </div>

                            <div class="form-grid" style="padding-bottom:24px;">
                                <div class="form-grid-full">
                                    <label class="form-section-heading"><i class="fas fa-running"></i> Athletic Stats</label>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Height</label>
                                    <div style="position:relative;">
                                        <input type="number" name="height_cm" class="field-input" min="80" max="250" placeholder="e.g. 155" style="padding-right:44px;">
                                        <span style="position:absolute;right:13px;top:50%;transform:translateY(-50%);font-size:.74rem;font-weight:700;color:#a8a6bc;">cm</span>
                                    </div>
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Weight</label>
                                    <div style="position:relative;">
                                        <input type="number" name="weight_kg" class="field-input" min="20" max="200" step="0.1" placeholder="e.g. 48.5" style="padding-right:44px;">
                                        <span style="position:absolute;right:13px;top:50%;transform:translateY(-50%);font-size:.74rem;font-weight:700;color:#a8a6bc;">kg</span>
                                    </div>
                                </div>
                            </div>

                        </div><!-- /step-3 -->

                        <!-- ── STEP 4: Photo ── -->
                        <div class="step-panel" id="add-step-4">

                            <div style="padding-bottom:24px;">
                                <label class="form-section-heading" style="margin-bottom:20px;"><i class="fas fa-camera"></i> School Photograph</label>
                                <div class="photo-zone" id="add-photo-zone">
                                    <input type="file" name="student_image" id="add-photo-input" accept="image/*">
                                    <img id="add-photo-preview" class="photo-preview" src="" alt="Preview">
                                    <div class="photo-icon" id="add-photo-icon"><i class="fas fa-camera"></i></div>
                                    <div class="photo-zone-title" id="add-photo-title">Upload School Photo</div>
                                    <div class="photo-zone-sub" id="add-photo-sub">Click or drag & drop · Max 2MB · JPEG, PNG, WebP</div>
                                </div>
                                <div style="margin-top:28px; background:#f0f9f4; border-radius:16px; padding:20px; border:1px solid #c5e8d5;">
                                    <div style="font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#2d5c43;margin-bottom:14px;display:flex;align-items:center;gap:6px;"><i class="fas fa-check-circle"></i> Summary Preview</div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;" id="add-summary">
                                        <div style="font-size:.78rem;color:#6b6a7a;"><span style="font-weight:700;color:#0f0e17;">Name:</span> <span id="sum-name">—</span></div>
                                        <div style="font-size:.78rem;color:#6b6a7a;"><span style="font-weight:700;color:#0f0e17;">ID:</span> <span id="sum-id">—</span></div>
                                        <div style="font-size:.78rem;color:#6b6a7a;"><span style="font-weight:700;color:#0f0e17;">Grade:</span> <span id="sum-grade">—</span></div>
                                        <div style="font-size:.78rem;color:#6b6a7a;"><span style="font-weight:700;color:#0f0e17;">Status:</span> <span id="sum-status">—</span></div>
                                        <div style="font-size:.78rem;color:#6b6a7a;"><span style="font-weight:700;color:#0f0e17;">Gender:</span> <span id="sum-gender">—</span></div>
                                        <div style="font-size:.78rem;color:#6b6a7a;"><span style="font-weight:700;color:#0f0e17;">Phone:</span> <span id="sum-phone">—</span></div>
                                    </div>
                                </div>
                            </div>

                        </div><!-- /step-4 -->

                    </div><!-- /modal-scroll -->

                    <!-- Footer -->
                    <div class="modal-footer-new">
                        <div class="footer-left">
                            <div class="step-dots">
                                <div class="step-dot active" id="add-dot-1"></div>
                                <div class="step-dot" id="add-dot-2"></div>
                                <div class="step-dot" id="add-dot-3"></div>
                                <div class="step-dot" id="add-dot-4"></div>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;align-items:center;">
                            <button type="button" class="btn-step-prev" id="add-prev-btn" style="display:none;"><i class="fas fa-arrow-left"></i> Back</button>
                            <button type="button" class="btn-secondary cancel-btn" id="add-cancel-btn" style="font-size:.84rem;border-radius:12px;">Cancel</button>
                            <button type="button" class="btn-step-next" id="add-next-btn">Next <i class="fas fa-arrow-right"></i></button>
                            <button type="submit" class="btn-save-final" id="add-save-btn" style="display:none;"><i class="fas fa-check"></i> Save Student</button>
                        </div>
                    </div>

                </form>
            </div><!-- /modal-inner -->
        </div>
    </div>

    <!-- ══ EDIT STUDENT MODAL ══ -->
    <div id="edit-student-modal" class="modal">
        <div class="modal-box modal-lg">
            <div class="modal-head">
                <h3><i class="fas fa-user-edit"></i> Edit Student</h3>
                <button class="modal-close close-modal"><i class="fas fa-times"></i></button>
            </div>
            <form id="edit-student-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" id="edit-student-id" name="student_id">
                <div class="modal-body">
                    <div id="grad-warning" class="grad-warning" style="display:none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>This student has a <strong>Completers</strong> status. Please confirm before making changes.</span>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name <span class="req">*</span></label>
                            <input type="text" id="edit-student-name" name="student_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Grade Level <span class="req">*</span></label>
                            <select id="edit-student-grade" name="grade_section" class="form-select" required>
                                <option value="">Select Grade</option>
                                <option value="Grade 7">Grade 7</option>
                                <option value="Grade 8">Grade 8</option>
                                <option value="Grade 9">Grade 9</option>
                                <option value="Grade 10">Grade 10</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Section</label>
                            <select id="edit-student-section" name="section" class="form-select">
                                <option value="">Select Section</option>
                                <?php foreach ($allSections as $sec): ?>
                                    <option value="<?php echo htmlspecialchars($sec); ?>"><?php echo htmlspecialchars($sec); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select id="edit-student-status" name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Transferred">Transferred</option>
                                <option value="Completers">Completers</option>
                                <option value="Dropped">Dropped</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender <span class="req">*</span></label>
                            <select id="edit-student-gender" name="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Age <span class="req">*</span></label>
                            <input type="number" id="edit-student-age" name="age" class="form-input" required min="10" max="25">
                        </div>
                        <div class="form-group form-grid-full">
                            <label class="form-label">Profile Image</label>
                            <div class="img-preview-row">
                                <img id="edit-student-profile-img" src="../assets/img/person/unknown.jpg" alt="Preview" class="img-preview">
                                <label for="edit-student-image" class="img-upload-btn"><i class="fas fa-camera"></i> Change Photo</label>
                                <input type="file" id="edit-student-image" name="student_image" accept="image/*" style="display:none;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary cancel-btn">Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ CSV IMPORT MODAL ══ -->
    <!-- ══ EXPORT REPORT MODAL ══ -->
    <div id="export-report-modal" class="modal">
        <div class="modal-box">
            <div class="modal-head">
                <h3><i class="fas fa-file-export"></i> Export Student Report</h3>
                <button class="modal-close close-modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p style="font-size:.83rem;color:#5a5870;margin-bottom:18px;">Choose a format to download the current student records as a report document.</p>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <a href="?export=pdf<?php echo !empty($_GET['grade']) ? '&grade=' . urlencode($_GET['grade']) : ''; ?><?php echo !empty($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?><?php echo !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="export-format-btn" id="export-pdf-btn">
                        <div class="export-icon" style="background:#fef2f2;color:#dc2626;"><i class="fas fa-file-pdf"></i></div>
                        <div>
                            <div class="export-format-title">PDF Document</div>
                            <div class="export-format-sub">Printable report · Best for sharing</div>
                        </div>
                        <i class="fas fa-download" style="margin-left:auto;color:#9190a0;"></i>
                    </a>
                    <a href="?export=docx<?php echo !empty($_GET['grade']) ? '&grade=' . urlencode($_GET['grade']) : ''; ?><?php echo !empty($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?><?php echo !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="export-format-btn" id="export-docx-btn">
                        <div class="export-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-file-word"></i></div>
                        <div>
                            <div class="export-format-title">Word Document (.docx)</div>
                            <div class="export-format-sub">Editable report · Best for editing</div>
                        </div>
                        <i class="fas fa-download" style="margin-left:auto;color:#9190a0;"></i>
                    </a>
                </div>
                <p style="font-size:.74rem;color:#a0a0b0;margin-top:16px;"><i class="fas fa-info-circle"></i> Exports all students matching your current filters.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary cancel-btn">Close</button>
            </div>
        </div>
    </div>

    <style>
        .export-format-btn {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border: 1.5px solid #e8e6e0;
            border-radius: 14px;
            background: #fafaf8;
            cursor: pointer;
            text-decoration: none;
            color: #0f0e17;
            transition: border-color .15s, background .15s, box-shadow .15s;
        }

        .export-format-btn:hover {
            border-color: #3c785a;
            background: #f0f9f4;
            box-shadow: 0 2px 12px rgba(60, 120, 90, .1);
        }

        .export-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .export-format-title {
            font-size: .86rem;
            font-weight: 700;
            color: #0f0e17;
        }

        .export-format-sub {
            font-size: .74rem;
            color: #9190a0;
            margin-top: 2px;
        }
    </style>

    <!-- Hidden Delete Form -->
    <form id="delete-student-form" method="post" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        <input type="hidden" name="student_id" id="delete-student-id">
    </form>

    <script src="admin_assets/js/admin_script.js"></script>
    <script src="admin_assets/js/student.js"></script>
    <script>
        /* ── Stat counter animation ── */
        document.querySelectorAll('.stat-value[data-target]').forEach(el => {
            const target = +el.getAttribute('data-target');
            let current = 0;
            const step = Math.max(1, Math.ceil(target / 40));
            const timer = setInterval(() => {
                current = Math.min(current + step, target);
                el.textContent = current;
                if (current >= target) clearInterval(timer);
            }, 30);
        });

        /* ── Filter redirects ── */
        function applyFilters() {
            const params = new URLSearchParams(window.location.search);
            params.set('search', document.getElementById('search-input').value.trim());
            params.set('grade', document.getElementById('grade-filter').value);
            params.set('gender', document.getElementById('gender-filter').value);
            params.set('section', document.getElementById('section-filter').value);
            params.set('status', document.getElementById('status-filter').value);
            params.set('page', '1');
            window.location.search = params.toString();
        }
        let searchTimer;
        document.getElementById('search-input').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 500);
        });
        ['grade-filter', 'gender-filter', 'section-filter', 'status-filter'].forEach(id => document.getElementById(id).addEventListener('change', applyFilters));

        /* ── Profile modal ── */
        const profileModal = document.getElementById('profile-modal');

        function openProfile(row) {
            // Prefer the student's own photo (uploaded via profile.php) over admin-uploaded photo
            const profilePhoto = row.dataset.profilePhoto || '';
            const adminPhoto = row.dataset.image || '';
            const img = profilePhoto || adminPhoto || '../assets/img/person/unknown.jpg';
            document.getElementById('pm-photo').src = img;
            document.getElementById('pm-name').textContent = row.dataset.name || '';
            document.getElementById('pm-id').textContent = row.dataset.id || '';
            document.getElementById('pm-grade').textContent = row.dataset.grade || '—';
            document.getElementById('pm-section').textContent = row.dataset.section || '—';
            document.getElementById('pm-age').textContent = row.dataset.age || '—';
            document.getElementById('pm-gender').textContent = row.dataset.gender || '—';
            document.getElementById('pm-added').textContent = row.dataset.added || '—';
            const status = row.dataset.status || 'Active';
            const cls = {
                Active: 'badge-active',
                Transferred: 'badge-transferred',
                Completers: 'badge-completers',
                Dropped: 'badge-dropped'
            } [status] || 'badge-active';
            document.getElementById('pm-badge-wrap').innerHTML = `<span class="badge ${cls}">${status}</span>`;
            document.getElementById('pm-edit-btn').onclick = () => {
                closeProfile();
                row.querySelector('a.edit')?.click();
            };
            profileModal.classList.add('open');
        }

        function closeProfile() {
            profileModal.classList.remove('open');
        }
        document.getElementById('profile-close').onclick = closeProfile;
        document.getElementById('pm-close-btn').onclick = closeProfile;
        profileModal.addEventListener('click', e => {
            if (e.target === profileModal) closeProfile();
        });
        document.querySelectorAll('.student-row').forEach(row => {
            row.addEventListener('click', e => {
                if (e.target.closest('.actions')) return;
                openProfile(row);
            });
        });

        /* ── Modal open/close ── */
        document.getElementById('add-student-btn').addEventListener('click', () => document.getElementById('add-student-modal').style.display = 'flex');
        document.getElementById('export-report-btn').addEventListener('click', () => document.getElementById('export-report-modal').style.display = 'flex');
        document.querySelectorAll('.close-modal, .cancel-btn').forEach(btn => btn.addEventListener('click', () => btn.closest('.modal').style.display = 'none'));
        document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => {
            if (e.target === m) m.style.display = 'none';
        }));

        /* ── Birthdate → auto age ── */
        document.getElementById('add-birthdate').addEventListener('change', function() {
            if (!this.value) return;
            const bd = new Date(this.value),
                today = new Date();
            let age = today.getFullYear() - bd.getFullYear();
            const m = today.getMonth() - bd.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < bd.getDate())) age--;
            document.getElementById('add-age').value = age;
        });

        /* ── Add Student Multi-Step Wizard ── */
        (function() {
            let currentStep = 1;
            const totalSteps = 4;
            const progressPcts = [25, 50, 75, 100];

            function goToStep(step) {
                // hide current
                document.getElementById('add-step-' + currentStep).classList.remove('active');
                document.getElementById('add-dot-' + currentStep).classList.remove('active');
                document.querySelectorAll('#add-step-tabs .step-tab')[currentStep - 1].classList.remove('active');

                // mark done
                if (step > currentStep) {
                    document.querySelectorAll('#add-step-tabs .step-tab')[currentStep - 1].classList.add('done');
                    document.querySelectorAll('#add-step-tabs .step-tab')[currentStep - 1].querySelector('.step-num').innerHTML = '<i class="fas fa-check" style="font-size:.6rem"></i>';
                } else {
                    document.querySelectorAll('#add-step-tabs .step-tab')[step - 1].classList.remove('done');
                    document.querySelectorAll('#add-step-tabs .step-tab')[step - 1].querySelector('.step-num').textContent = step;
                }

                currentStep = step;

                document.getElementById('add-step-' + currentStep).classList.add('active');
                document.getElementById('add-dot-' + currentStep).classList.add('active');
                document.querySelectorAll('#add-step-tabs .step-tab')[currentStep - 1].classList.add('active');

                // Update progress
                document.getElementById('add-progress-bar').style.width = progressPcts[currentStep - 1] + '%';

                // Buttons
                document.getElementById('add-prev-btn').style.display = currentStep > 1 ? '' : 'none';
                document.getElementById('add-next-btn').style.display = currentStep < totalSteps ? '' : 'none';
                document.getElementById('add-save-btn').style.display = currentStep === totalSteps ? '' : 'none';

                // Scroll to top of scroll area
                document.querySelector('#add-student-modal .modal-scroll').scrollTop = 0;

                // On step 4 update summary
                if (currentStep === 4) updateSummary();
            }

            function updateSummary() {
                const fn = document.querySelector('[name="first_name"]').value;
                const ln = document.querySelector('[name="last_name"]').value;
                const mi = document.querySelector('[name="middle_initial"]').value;
                const sfx = document.querySelector('[name="suffix"]').value;
                document.getElementById('sum-name').textContent = [fn, mi, ln, sfx].filter(Boolean).join(' ') || '—';
                document.getElementById('sum-id').textContent = document.querySelector('[name="student_id"]').value || '—';
                document.getElementById('sum-grade').textContent = document.querySelector('[name="grade_section"]').value || '—';
                document.getElementById('sum-status').textContent = document.querySelector('[name="status"]').value || '—';
                const g = document.querySelector('[name="gender"]:checked');
                document.getElementById('sum-gender').textContent = g ? g.value : '—';
                document.getElementById('sum-phone').textContent = document.querySelector('[name="phone_number"]').value || '—';
            }

            document.getElementById('add-next-btn').addEventListener('click', function() {
                if (currentStep < totalSteps) goToStep(currentStep + 1);
            });
            document.getElementById('add-prev-btn').addEventListener('click', function() {
                if (currentStep > 1) goToStep(currentStep - 1);
            });

            // Tab click
            document.querySelectorAll('#add-step-tabs .step-tab').forEach((tab, i) => {
                tab.addEventListener('click', () => goToStep(i + 1));
            });

            // Reset on modal close
            function resetWizard() {
                // reset all tabs
                document.querySelectorAll('#add-step-tabs .step-tab').forEach((tab, i) => {
                    tab.classList.remove('active', 'done');
                    tab.querySelector('.step-num').textContent = i + 1;
                });
                document.querySelectorAll('#add-student-modal .step-panel').forEach(p => p.classList.remove('active'));
                document.querySelectorAll('#add-student-modal .step-dot').forEach(d => d.classList.remove('active'));
                currentStep = 1;
                goToStep(1);
            }

            document.querySelectorAll('#add-student-modal .close-modal, #add-cancel-btn').forEach(b => {
                b.addEventListener('click', resetWizard);
            });

            // Photo preview
            document.getElementById('add-photo-input').addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const r = new FileReader();
                    r.onload = e => {
                        const preview = document.getElementById('add-photo-preview');
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        document.getElementById('add-photo-icon').style.display = 'none';
                        document.getElementById('add-photo-title').textContent = this.files[0].name;
                        document.getElementById('add-photo-sub').textContent = 'Click to change photo';
                        document.getElementById('add-photo-zone').classList.add('has-photo');
                    };
                    r.readAsDataURL(this.files[0]);
                }
            });
        })();

        /* ── Edit modal populate ── */
        document.querySelectorAll('a.edit').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const row = this.closest('tr');
                const status = this.dataset.status || 'Active';
                document.getElementById('grad-warning').style.display = status === 'Completers' ? 'flex' : 'none';
                document.getElementById('edit-student-id').value = this.dataset.id;
                document.getElementById('edit-student-name').value = row.dataset.name || '';
                document.getElementById('edit-student-grade').value = row.dataset.grade || '';
                document.getElementById('edit-student-section').value = row.dataset.section || '';
                document.getElementById('edit-student-gender').value = row.dataset.gender || '';
                document.getElementById('edit-student-age').value = row.dataset.age || '';
                document.getElementById('edit-student-status').value = status;
                // Prefer student's own photo (from profile.php) over admin-uploaded photo
                const editProfilePhoto = row.dataset.profilePhoto || '';
                const editAdminPhoto = this.dataset.image || '';
                document.getElementById('edit-student-profile-img').src = editProfilePhoto || editAdminPhoto || '../assets/img/person/unknown.jpg';
                document.getElementById('edit-student-modal').style.display = 'flex';
            });
        });
        document.getElementById('edit-student-image').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const r = new FileReader();
                r.onload = e => document.getElementById('edit-student-profile-img').src = e.target.result;
                r.readAsDataURL(this.files[0]);
            }
        });

        /* ── Delete ── */
        document.querySelectorAll('a.delete').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('Delete this student? This cannot be undone.')) return;
                document.getElementById('delete-student-id').value = this.dataset.id;
                document.getElementById('delete-student-form').submit();
            });
        });
    </script>
</body>

</html>