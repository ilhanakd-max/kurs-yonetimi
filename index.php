<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
if ($isSecure) {
    ini_set('session.cookie_secure', '1');
}
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function clean_string($value, $maxLength = 255) {
    $value = is_string($value) ? trim($value) : '';
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

function clean_int($value) {
    return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : null;
}

function clean_float($value): ?float {
    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false ? (float)$value : null;
}

function clean_date($value) {
    $value = clean_string($value, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function clean_year_month($value): ?string {
    $value = clean_string($value, 7);
    return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : null;
}

function clean_email($value) {
    $value = clean_string($value, 255);
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
}

function clean_password($value, $maxLength = 255) {
    $value = is_string($value) ? $value : '';
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

const ATTENDANCE_STATUS_PRESENT = 1;
const ATTENDANCE_STATUS_ABSENT = 2;
const ATTENDANCE_STATUS_EXCUSED = 3;

function normalize_attendance_status($value): ?int {
    if (is_int($value)) {
        return in_array($value, [ATTENDANCE_STATUS_PRESENT, ATTENDANCE_STATUS_ABSENT, ATTENDANCE_STATUS_EXCUSED], true)
            ? $value
            : null;
    }
    if (is_numeric($value)) {
        $intVal = (int)$value;
        return in_array($intVal, [ATTENDANCE_STATUS_PRESENT, ATTENDANCE_STATUS_ABSENT, ATTENDANCE_STATUS_EXCUSED], true)
            ? $intVal
            : null;
    }
    if (!is_string($value)) {
        return null;
    }
    $value = strtolower(trim($value));
    return match ($value) {
        'present' => ATTENDANCE_STATUS_PRESENT,
        'absent' => ATTENDANCE_STATUS_ABSENT,
        'excused' => ATTENDANCE_STATUS_EXCUSED,
        default => null
    };
}

function migrate_attendance_statuses(PDO $pdo, int $periodId): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE period_id=? AND status IN ('present','absent','excused')");
    $stmt->execute([$periodId]);
    if ((int)$stmt->fetchColumn() === 0) {
        return;
    }
    $pdo->prepare("UPDATE attendance SET status = CASE status WHEN 'present' THEN ? WHEN 'absent' THEN ? WHEN 'excused' THEN ? ELSE status END WHERE period_id=?")
        ->execute([ATTENDANCE_STATUS_PRESENT, ATTENDANCE_STATUS_ABSENT, ATTENDANCE_STATUS_EXCUSED, $periodId]);
}

function parse_time_range_minutes(?string $timeRange): ?array {
    if (!$timeRange) {
        return null;
    }
    if (!preg_match('/^\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})\s*$/', $timeRange, $matches)) {
        return null;
    }
    $startHour = (int)$matches[1];
    $startMinute = (int)$matches[2];
    $endHour = (int)$matches[3];
    $endMinute = (int)$matches[4];
    if ($startHour > 23 || $endHour > 23 || $startMinute > 59 || $endMinute > 59) {
        return null;
    }
    $startTotal = $startHour * 60 + $startMinute;
    $endTotal = $endHour * 60 + $endMinute;
    if ($endTotal <= $startTotal) {
        return null;
    }
    return [$startTotal, $endTotal];
}

function ranges_overlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool {
    if (!$startA || !$endA || !$startB || !$endB) {
        return true;
    }
    return $startA <= $endB && $endA >= $startB;
}

function check_course_conflicts(PDO $pdo, int $periodId, ?int $courseId, string $day, string $time, string $building, string $classroom, ?int $teacherId, ?string $startDate, ?string $endDate): array {
    $messages = [];
    $timeRange = parse_time_range_minutes($time);
    if (!$day || !$timeRange) {
        return $messages;
    }
    $clauses = [];
    $params = [$periodId, $day, $courseId ?: 0];
    if ($classroom) {
        $clauses[] = "(classroom=? AND building=?)";
        $params[] = $classroom;
        $params[] = $building;
    }
    if ($teacherId) {
        $clauses[] = "teacher_id=?";
        $params[] = $teacherId;
    }
    if (!$clauses) {
        return $messages;
    }
    $sql = "SELECT id, name, time, classroom, building, teacher_id, start_date, end_date FROM courses WHERE period_id=? AND day=? AND id<>? AND (" . implode(' OR ', $clauses) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classroomConflict = false;
    $teacherConflict = false;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!ranges_overlap($startDate, $endDate, $row['start_date'] ?? null, $row['end_date'] ?? null)) {
            continue;
        }
        $existingRange = parse_time_range_minutes($row['time'] ?? '');
        if (!$existingRange) {
            continue;
        }
        if ($timeRange[0] < $existingRange[1] && $timeRange[1] > $existingRange[0]) {
            if ($classroom && $row['classroom'] === $classroom && $row['building'] === $building) {
                $classroomConflict = true;
            }
            if ($teacherId && (int)$row['teacher_id'] === $teacherId) {
                $teacherConflict = true;
            }
        }
    }
    if ($classroomConflict) {
        $messages[] = 'Seçilen sınıf için bu gün ve saat aralığında başka bir kurs bulunmaktadır.';
    }
    if ($teacherConflict) {
        $messages[] = 'Seçilen öğretmenin bu gün ve saat aralığında başka bir dersi bulunmaktadır.';
    }
    return $messages;
}

function json_response($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function require_auth() {
    if (empty($_SESSION['user'])) {
        json_response(['status' => 'error', 'message' => 'Yetkisiz erişim'], 401);
    }
    return $_SESSION['user'];
}

function require_admin($user) {
    if (($user['role'] ?? '') !== 'admin') {
        json_response(['status' => 'error', 'message' => 'Yetkisiz erişim'], 403);
    }
}

function require_course_access(PDO $pdo, array $user, int $courseId, ?int $periodId = null) {
    if (($user['role'] ?? '') === 'admin') {
        return;
    }
    if (($user['role'] ?? '') !== 'teacher') {
        json_response(['status' => 'error', 'message' => 'Yetkisiz erişim'], 403);
    }
    $sql = "SELECT id FROM courses WHERE id=? AND teacher_id=?";
    $params = [$courseId, $user['id'] ?? 0];
    if ($periodId) {
        $sql .= " AND period_id=?";
        $params[] = $periodId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if (!$stmt->fetchColumn()) {
        json_response(['status' => 'error', 'message' => 'Yetkisiz erişim'], 403);
    }
}

function get_active_period(PDO $pdo, bool &$createdDefault = false) {
    $stmt = $pdo->query("SELECT id, name, start_date, end_date, is_active FROM course_periods WHERE is_active=1 AND is_deleted=0 ORDER BY id DESC LIMIT 1");
    $period = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($period) {
        return $period;
    }
    $createdDefault = true;
    $pdo->prepare("INSERT INTO course_periods (name, is_active) VALUES (?, 1)")->execute(['Varsayılan Kurs Dönemi']);
    $periodId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE course_periods SET is_active=0 WHERE id<>?")->execute([$periodId]);
    return ['id' => $periodId, 'name' => 'Varsayılan Kurs Dönemi', 'start_date' => null, 'end_date' => null, 'is_active' => 1];
}

function ensure_period_defaults(PDO $pdo, int $periodId, bool $seedStudents = false): void {
    $pdo->prepare("UPDATE courses SET period_id=? WHERE period_id IS NULL")->execute([$periodId]);
    $pdo->prepare("UPDATE student_courses SET period_id=? WHERE period_id IS NULL")->execute([$periodId]);
    $pdo->prepare("UPDATE attendance SET period_id=? WHERE period_id IS NULL")->execute([$periodId]);
    if ($seedStudents) {
        $pdo->prepare("INSERT IGNORE INTO student_periods (student_id, period_id, reg_date) SELECT id, ?, COALESCE(reg_date, CURDATE()) FROM students")->execute([$periodId]);
    }
}

function get_certificate_settings(PDO $pdo, array $meta = []): array {
    $defaults = [
        'institution_name' => $meta['title'] ?? 'Kurum Adı',
        'logo_url' => '',
        'certificate_text' => 'Bu belge, ilgili kursu başarıyla tamamladığını gösterir.',
        'signature_primary_name' => '',
        'signature_primary_title' => '',
        'signature_secondary_name' => '',
        'signature_secondary_title' => '',
        'min_attendance' => 70,
        'min_score' => 70,
    ];
    $stmt = $pdo->query("SELECT institution_name, logo_url, certificate_text, signature_primary_name, signature_primary_title, signature_secondary_name, signature_secondary_title, min_attendance, min_score FROM certificate_settings ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $defaults;
    }
    foreach ($defaults as $key => $value) {
        if (!isset($row[$key]) || $row[$key] === null || $row[$key] === '') {
            $row[$key] = $value;
        }
    }
    return $row;
}

function calculate_attendance_stats(PDO $pdo, int $courseId, int $studentId, int $periodId): array {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM attendance WHERE course_id=? AND student_id=? AND period_id=? GROUP BY status");
    $stmt->execute([$courseId, $studentId, $periodId]);
    $present = 0;
    $excused = 0;
    $total = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = normalize_attendance_status($row['status']);
        $count = (int)$row['total'];
        $total += $count;
        if ($status === ATTENDANCE_STATUS_PRESENT) {
            $present += $count;
        } elseif ($status === ATTENDANCE_STATUS_EXCUSED) {
            $excused += $count;
        }
    }
    $attended = $present + $excused;
    $rate = $total > 0 ? round(($attended / $total) * 100, 2) : 0;
    return ['total' => $total, 'present' => $present, 'excused' => $excused, 'rate' => $rate];
}

function get_evaluation_average(PDO $pdo, int $courseId, int $studentId, int $periodId): ?float {
    $stmt = $pdo->prepare("SELECT AVG(score) FROM teacher_evaluations WHERE course_id=? AND student_id=? AND period_id=?");
    $stmt->execute([$courseId, $studentId, $periodId]);
    $avg = $stmt->fetchColumn();
    return $avg !== null ? (float)$avg : null;
}

function stream_database_backup(PDO $pdo, string $dbName) {
    $timestamp = date('Ymd_His');
    $fileName = 'backup_' . $dbName . '_' . $timestamp . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "-- Database Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "DROP TABLE IF EXISTS `" . $table . "`;\n";
        $createResult = $pdo->query("SHOW CREATE TABLE `" . $table . "`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createResult['Create Table'] ?? (is_array($createResult) ? array_values($createResult)[1] : '');
        if ($createSql) {
            echo $createSql . ";\n\n";
        }

        $rows = $pdo->query("SELECT * FROM `" . $table . "`");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(fn($column) => "`" . $column . "`", array_keys($row));
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = $pdo->quote($value);
                }
            }
            echo "INSERT INTO `" . $table . "` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}
// --- VERİTABANI BAĞLANTISI ---
$db_host = 'sql211.infinityfree.com';
$db_name = 'if0_40197167_test';
$db_user = 'if0_40197167';
$db_pass = 'TEST'; // PANEL ŞİFRENİZ

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

// --- API İŞLEMLERİ (AJAX Requests) ---
if (isset($_GET['action'])) {
    $action = clean_string($_GET['action'], 50);
    if ($action === 'download_backup') {
        $token = clean_string($_GET['token'] ?? '', 100);
        if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(400);
            exit;
        }
        $user = require_auth();
        require_admin($user);
        stream_database_backup($pdo, $db_name);
    }
    if ($action === 'download_certificate') {
        $token = clean_string($_GET['token'] ?? '', 100);
        if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(400);
            exit;
        }
        $user = require_auth();
        require_admin($user);
        $certificateId = clean_int($_GET['id'] ?? null);
        if (!$certificateId) {
            http_response_code(400);
            exit;
        }
        $stmt = $pdo->prepare("SELECT certificate_payload FROM certificates WHERE id=?");
        $stmt->execute([$certificateId]);
        $payloadJson = $stmt->fetchColumn();
        if (!$payloadJson) {
            http_response_code(404);
            exit;
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            http_response_code(500);
            exit;
        }
        $institutionName = clean_string($payload['institution_name'] ?? '', 150);
        $studentName = clean_string($payload['student_name'] ?? '', 150);
        $courseName = clean_string($payload['course_name'] ?? '', 150);
        $periodName = clean_string($payload['period_name'] ?? '', 150);
        $completionDate = clean_string($payload['completion_date'] ?? '', 20);
        $teacherName = clean_string($payload['teacher_name'] ?? '', 150);
        $certificateText = $payload['certificate_text'] ?? '';
        $logoUrl = $payload['logo_url'] ?? '';
        $signatures = $payload['signatures'] ?? [];
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Sertifika</title>';
        echo '<style>
            @page { size: A4; margin: 20mm; }
            body { font-family: "DejaVu Sans", Arial, sans-serif; color: #111; }
            .certificate { border: 3px solid #222; padding: 30px; height: 100%; box-sizing: border-box; }
            .certificate-header { text-align: center; margin-bottom: 30px; }
            .certificate-header img { max-height: 80px; margin-bottom: 10px; }
            .certificate-title { font-size: 28px; font-weight: bold; letter-spacing: 2px; }
            .certificate-body { text-align: center; margin-top: 30px; font-size: 18px; line-height: 1.6; }
            .certificate-body h2 { font-size: 24px; margin: 15px 0; }
            .certificate-meta { margin-top: 30px; font-size: 16px; }
            .certificate-footer { display: flex; justify-content: space-between; margin-top: 50px; }
            .signature { text-align: center; min-width: 180px; }
            .signature .line { margin-top: 40px; border-top: 1px solid #333; }
            .print-actions { margin-top: 20px; text-align: center; }
            @media print { .print-actions { display: none; } }
        </style></head><body>';
        echo '<div class="certificate">';
        echo '<div class="certificate-header">';
        if ($logoUrl) {
            echo '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Logo">';
        }
        echo '<div class="certificate-title">SERTİFİKA</div>';
        echo '<div style="margin-top:10px;font-size:18px;">' . htmlspecialchars($institutionName, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';
        echo '<div class="certificate-body">';
        echo '<p>Bu belge,</p>';
        echo '<h2>' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . '</h2>';
        echo '<p><strong>' . htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8') . '</strong> kursunu</p>';
        echo '<p>' . htmlspecialchars($periodName, ENT_QUOTES, 'UTF-8') . ' döneminde başarıyla tamamladığını gösterir.</p>';
        if ($certificateText) {
            echo '<p>' . nl2br(htmlspecialchars($certificateText, ENT_QUOTES, 'UTF-8')) . '</p>';
        }
        echo '<div class="certificate-meta">';
        echo '<div>Tamamlanma Tarihi: <strong>' . htmlspecialchars($completionDate, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        echo '<div>Öğretmen: <strong>' . htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="certificate-footer">';
        $sigSlots = 0;
        foreach ($signatures as $signature) {
            $sigName = clean_string($signature['name'] ?? '', 150);
            $sigTitle = clean_string($signature['title'] ?? '', 150);
            if (!$sigName && !$sigTitle) {
                continue;
            }
            $sigSlots++;
            echo '<div class="signature">';
            echo '<div class="line"></div>';
            echo '<div>' . htmlspecialchars($sigName, ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div style="font-size:14px;color:#555;">' . htmlspecialchars($sigTitle, ENT_QUOTES, 'UTF-8') . '</div>';
            echo '</div>';
        }
        if ($sigSlots === 0) {
            echo '<div class="signature"><div class="line"></div><div>&nbsp;</div></div>';
        }
        echo '</div></div>';
        echo '<div class="print-actions"><button onclick="window.print()">🖨️ Yazdır / PDF</button></div>';
        echo '</body></html>';
        exit;
    }
    header('Content-Type: application/json');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = [];
    }
    if ($method === 'POST') {
        $token = $data['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
    }

    // LOGIN
    if ($action === 'login') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $u = clean_string($data['username'] ?? '', 100);
        $p = clean_password($data['password'] ?? '', 255);
        
        // Admin Kontrolü
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$u]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // İlk kurulum için varsayılan admin
        if (!$user && $u === 'admin' && $p === 'admin123') {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)")
                ->execute(['Sistem Yöneticisi', 'admin', $hash, 'admin']);
            $user = ['id' => 1, 'username' => 'admin', 'role' => 'admin', 'name' => 'Sistem Yöneticisi', 'password' => $hash];
        }

        if ($user && password_verify($p, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$user['id'], 'username' => $user['username'], 'role' => $user['role'], 'name' => $user['name']];
            unset($user['password']);
            echo json_encode(['status' => 'success', 'user' => $user]);
            exit;
        }

        // Öğretmen Kontrolü
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE username = ?");
        $stmt->execute([$u]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $auth = false;
        if($teacher) {
            if(password_verify($p, $teacher['password'])) {
                $auth = true;
            } elseif ($p === $teacher['password']) {
                $auth = true; 
            }
        }

        if ($auth) {
             session_regenerate_id(true);
             $_SESSION['user'] = ['id' => (int)$teacher['id'], 'username' => $teacher['username'], 'role' => 'teacher', 'name' => $teacher['name']];
             $tUser = ['id' => $teacher['id'], 'username' => $teacher['username'], 'role' => 'teacher', 'name' => $teacher['name']];
             echo json_encode(['status' => 'success', 'user' => $tUser]);
             exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Hatalı kullanıcı adı veya şifre']);
        exit;
    }

    // ŞİFRE DEĞİŞTİRME
    if ($action === 'logout') {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        json_response(['status' => 'success']);
    }

    $user = require_auth();
    $createdDefaultPeriod = false;
    $activePeriod = get_active_period($pdo, $createdDefaultPeriod);
    $activePeriodId = (int)($activePeriod['id'] ?? 0);
    if ($activePeriodId > 0) {
        ensure_period_defaults($pdo, $activePeriodId, $createdDefaultPeriod);
        migrate_attendance_statuses($pdo, $activePeriodId);
    }

    if ($action === 'change_password') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $id = clean_int($data['id'] ?? null);
        $role = clean_string($data['role'] ?? '', 20);
        $newPass = clean_password($data['newPass'] ?? '', 255);
        if (!$id || !$newPass || $role !== ($user['role'] ?? '') || $id !== (int)($user['id'] ?? 0)) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $hash = password_hash($newPass, PASSWORD_DEFAULT);

        if ($role === 'admin') {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
        } else {
            $pdo->prepare("UPDATE teachers SET password=? WHERE id=?")->execute([$hash, $id]);
        }
        echo json_encode(['status' => 'success']);
        exit;
    }

    // VERİLERİ ÇEK
    if ($action === 'get_all_data') {
        if ($method !== 'GET') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $rangeStart = clean_date($_GET['start'] ?? null);
        $rangeEnd = clean_date($_GET['end'] ?? null);
        if ($rangeStart && $rangeEnd && $rangeStart > $rangeEnd) {
            $rangeStart = null;
            $rangeEnd = null;
        }
        if (!$rangeStart || !$rangeEnd) {
            $today = new DateTimeImmutable('now');
            $rangeStart = $today->modify('monday this week')->format('Y-m-d');
            $rangeEnd = $today->modify('sunday this week')->format('Y-m-d');
        }
        $response = [];
        $stmt = $pdo->query("SELECT id, name, username, role FROM users");
        $response['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT * FROM teachers");
        $response['teachers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM courses WHERE period_id=?");
        $stmt->execute([$activePeriodId]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($courses as &$c) {
            // JS uyumluluğu için teacherId alanını ekliyoruz
            $c['teacherId'] = $c['teacher_id'];
            $c['startDate'] = $c['start_date'] ?? null;
            $c['endDate'] = $c['end_date'] ?? null;
            
            $c['cancelledDates'] = json_decode($c['cancelled_dates']) ?: [];
            $c['modifications'] = json_decode($c['modifications']) ?: (object)[];
            unset($c['cancelled_dates'], $c['modifications_json']);
        }
        $response['courses'] = $courses;

        $stmt = $pdo->prepare("SELECT s.* FROM students s INNER JOIN student_periods sp ON sp.student_id = s.id WHERE sp.period_id=?");
        $stmt->execute([$activePeriodId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($students as &$s) {
            $stmt2 = $pdo->prepare("SELECT course_id FROM student_courses WHERE student_id = ? AND period_id=?");
            $stmt2->execute([$s['id'], $activePeriodId]);
            $s['courses'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        }
        $response['students'] = $students;

        $stmt = $pdo->prepare("SELECT course_id, student_id, date, status FROM attendance WHERE period_id=? AND date BETWEEN ? AND ?");
        $stmt->execute([$activePeriodId, $rangeStart, $rangeEnd]);
        $att = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cleanAtt = [];
        foreach($att as $a) {
            $normalizedStatus = normalize_attendance_status($a['status']);
            if ($normalizedStatus === null) {
                continue;
            }
            $cleanAtt[] = ['courseId' => (int)$a['course_id'], 'studentId' => (int)$a['student_id'], 'date' => $a['date'], 'status' => $normalizedStatus];
        }
        $response['attendance'] = $cleanAtt;
        $response['attendanceRange'] = ['start' => $rangeStart, 'end' => $rangeEnd];

        $stmt = $pdo->query("SELECT date, name FROM holidays");
        $response['holidays'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT id, title, message, start_date, end_date FROM announcements WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY COALESCE(start_date, '1970-01-01') DESC, id DESC");
        $response['announcements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (($user['role'] ?? '') === 'admin') {
            $stmt = $pdo->query("SELECT id, title, message, start_date, end_date, is_active FROM announcements ORDER BY id DESC");
            $response['announcementsAll'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $pdo->query("SELECT * FROM meta_data");
        $meta = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $response['settings'] = ['title' => $meta['title'] ?? 'Çeşme Belediyesi Kültür Müdürlüğü'];
        $response['certificateSettings'] = get_certificate_settings($pdo, $meta);
        $response['buildings'] = json_decode($meta['buildings'] ?? '[]');
        $response['classes'] = json_decode($meta['classes'] ?? '[]');
        $response['activePeriod'] = $activePeriod;
        if (($user['role'] ?? '') === 'admin') {
            $stmt = $pdo->query("SELECT id, name, start_date, end_date, is_active FROM course_periods WHERE is_deleted=0 ORDER BY is_active DESC, id DESC");
            $response['periods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $response['periods'] = [$activePeriod];
        }

        echo json_encode($response);
        exit;
    }

    if ($action === 'get_attendance_range') {
        if ($method !== 'GET') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $rangeStart = clean_date($_GET['start'] ?? null);
        $rangeEnd = clean_date($_GET['end'] ?? null);
        if (!$rangeStart || !$rangeEnd || $rangeStart > $rangeEnd) {
            json_response(['status' => 'error', 'message' => 'Geçersiz tarih aralığı'], 400);
        }
        $stmt = $pdo->prepare("SELECT course_id, student_id, date, status FROM attendance WHERE period_id=? AND date BETWEEN ? AND ?");
        $stmt->execute([$activePeriodId, $rangeStart, $rangeEnd]);
        $att = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cleanAtt = [];
        foreach ($att as $a) {
            $normalizedStatus = normalize_attendance_status($a['status']);
            if ($normalizedStatus === null) {
                continue;
            }
            $cleanAtt[] = ['courseId' => (int)$a['course_id'], 'studentId' => (int)$a['student_id'], 'date' => $a['date'], 'status' => $normalizedStatus];
        }
        echo json_encode(['status' => 'success', 'attendance' => $cleanAtt, 'attendanceRange' => ['start' => $rangeStart, 'end' => $rangeEnd]]);
        exit;
    }

    if ($action === 'check_copy_course_conflicts') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $days = $data['days'] ?? [];
        $time = clean_string($data['time'] ?? '', 20);
        $building = clean_string($data['building'] ?? '', 150);
        $classroom = clean_string($data['classroom'] ?? '', 150);
        $teacherId = clean_int($data['teacherId'] ?? null);
        $startDate = clean_date($data['startDate'] ?? null);
        $endDate = clean_date($data['endDate'] ?? null);
        if (!is_array($days) || !$days) {
            json_response(['status' => 'error', 'message' => 'Lütfen en az bir gün seçiniz.'], 400);
        }
        $messages = [];
        foreach ($days as $dayValue) {
            $day = clean_string($dayValue ?? '', 20);
            if (!$day) {
                continue;
            }
            $conflicts = check_course_conflicts($pdo, $activePeriodId, null, $day, $time, $building, $classroom, $teacherId, $startDate, $endDate);
            foreach ($conflicts as $conflict) {
                $messages[] = $day . ': ' . $conflict;
            }
        }
        if ($messages) {
            $messages[] = 'Lütfen farklı bir saat veya gün seçiniz.';
            json_response(['status' => 'error', 'message' => implode("\n", $messages)], 400);
        }
        echo json_encode(['status' => 'success']);
        exit;
    }

    // KAYDETME İŞLEMLERİ
    if ($action === 'save_course') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $c = $data;
        $cancelled = json_encode(is_array($c['cancelledDates'] ?? null) ? $c['cancelledDates'] : []);
        $mods = json_encode(is_array($c['modifications'] ?? null) ? $c['modifications'] : []);
        $courseId = $c['id'] ?? null;
        $courseIdStr = is_string($courseId) ? $courseId : '';
        $courseIdInt = clean_int($courseId);
        $name = clean_string($c['name'] ?? '', 150);
        $color = clean_string($c['color'] ?? '', 20);
        $day = clean_string($c['day'] ?? '', 20);
        $time = clean_string($c['time'] ?? '', 20);
        $building = clean_string($c['building'] ?? '', 150);
        $classroom = clean_string($c['classroom'] ?? '', 150);
        $teacherId = clean_int($c['teacherId'] ?? null);
        $startDate = clean_date($c['startDate'] ?? null);
        $endDate = clean_date($c['endDate'] ?? null);
        $conflicts = check_course_conflicts($pdo, $activePeriodId, $courseIdInt, $day, $time, $building, $classroom, $teacherId, $startDate, $endDate);
        if ($conflicts) {
            json_response(['status' => 'error', 'message' => implode(' ', $conflicts)], 400);
        }
        
        if ($courseIdInt && $courseIdInt > 0 && !str_starts_with($courseIdStr, 'new')) {
            $sql = "UPDATE courses SET name=?, color=?, day=?, time=?, building=?, classroom=?, teacher_id=?, start_date=?, end_date=?, cancelled_dates=?, modifications=? WHERE id=? AND period_id=?";
            $pdo->prepare($sql)->execute([
                $name,
                $color,
                $day,
                $time,
                $building,
                $classroom,
                $teacherId,
                $startDate,
                $endDate,
                $cancelled,
                $mods,
                $courseIdInt,
                $activePeriodId
            ]);
        } else {
            $baseCourseId = clean_int($c['baseCourseId'] ?? null);
            try {
                $pdo->beginTransaction();
                $sql = "INSERT INTO courses (name, color, day, time, building, classroom, teacher_id, start_date, end_date, cancelled_dates, modifications, period_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
                $pdo->prepare($sql)->execute([
                    $name,
                    $color,
                    $day,
                    $time,
                    $building,
                    $classroom,
                    $teacherId,
                    $startDate,
                    $endDate,
                    $cancelled,
                    $mods,
                    $activePeriodId
                ]);
                $newCourseId = (int)$pdo->lastInsertId();
                if ($baseCourseId) {
                    $copyStmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_id, period_id) SELECT student_id, ?, period_id FROM student_courses WHERE course_id=? AND period_id=?");
                    $copyStmt->execute([$newCourseId, $baseCourseId, $activePeriodId]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'delete_course') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $id = clean_int($data['id'] ?? null);
        if (!$id) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $pdo->prepare("DELETE FROM courses WHERE id=? AND period_id=?")->execute([$id, $activePeriodId]);
        $pdo->prepare("DELETE FROM student_courses WHERE course_id=? AND period_id=?")->execute([$id, $activePeriodId]);
        $pdo->prepare("DELETE FROM attendance WHERE course_id=? AND period_id=?")->execute([$id, $activePeriodId]);
        $pdo->prepare("DELETE FROM teacher_evaluations WHERE course_id=? AND period_id=?")->execute([$id, $activePeriodId]);
        $pdo->prepare("DELETE FROM certificates WHERE course_id=? AND period_id=?")->execute([$id, $activePeriodId]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_student') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $s = $data;
        $sid = $s['id'] ?? null;
        $sidStr = is_string($sid) ? $sid : '';
        $sidInt = clean_int($sid);
        if ($sidInt && $sidInt > 0 && !str_starts_with($sidStr, 'new')) {
            $sql = "UPDATE students SET name=?, surname=?, phone=?, email=?, tc=?, date_of_birth=?, education=?, parent_name=?, parent_phone=? WHERE id=?";
            $pdo->prepare($sql)->execute([
                clean_string($s['name'] ?? '', 100),
                clean_string($s['surname'] ?? '', 100),
                clean_string($s['phone'] ?? '', 30),
                clean_email($s['email'] ?? ''),
                clean_string($s['tc'] ?? '', 20),
                clean_date($s['date_of_birth'] ?? null),
                clean_string($s['education'] ?? '', 150),
                clean_string($s['parent_name'] ?? '', 150),
                clean_string($s['parent_phone'] ?? '', 30),
                $sidInt
            ]);
        } else {
            $sql = "INSERT INTO students (name, surname, phone, email, tc, date_of_birth, education, parent_name, parent_phone, reg_date) VALUES (?,?,?,?,?,?,?,?,?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                clean_string($s['name'] ?? '', 100),
                clean_string($s['surname'] ?? '', 100),
                clean_string($s['phone'] ?? '', 30),
                clean_email($s['email'] ?? ''),
                clean_string($s['tc'] ?? '', 20),
                clean_date($s['date_of_birth'] ?? null),
                clean_string($s['education'] ?? '', 150),
                clean_string($s['parent_name'] ?? '', 150),
                clean_string($s['parent_phone'] ?? '', 30)
            ]);
            $sid = $pdo->lastInsertId();
        }

        $sidInt = clean_int($sid);
        if (!$sidInt) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $pdo->prepare("DELETE FROM student_courses WHERE student_id=? AND period_id=?")->execute([$sidInt, $activePeriodId]);
        if (!empty($s['courses']) && is_array($s['courses'])) {
            $insert = $pdo->prepare("INSERT INTO student_courses (student_id, course_id, period_id) VALUES (?, ?, ?)");
            foreach($s['courses'] as $cid) {
                $cidInt = clean_int($cid);
                if ($cidInt) {
                    $insert->execute([$sidInt, $cidInt, $activePeriodId]);
                }
            }
        }
        $pdo->prepare("INSERT IGNORE INTO student_periods (student_id, period_id, reg_date) SELECT id, ?, COALESCE(reg_date, CURDATE()) FROM students WHERE id=?")->execute([$activePeriodId, $sidInt]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'delete_student') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $id = clean_int($data['id'] ?? null);
        if (!$id) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM student_courses WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM attendance WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM student_periods WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM teacher_evaluations WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM certificates WHERE student_id=?")->execute([$id]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_teacher') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $t = $data;
        $branch = clean_string($t['branch'] ?? '', 150);
        $tid = $t['id'] ?? null;
        $tidStr = is_string($tid) ? $tid : '';
        $tidInt = clean_int($tid);
        if ($tidInt && $tidInt > 0 && !str_starts_with($tidStr, 'new')) {
            if(!empty($t['password'])) {
                 $hash = password_hash(clean_password($t['password'], 255), PASSWORD_DEFAULT);
                 $pdo->prepare("UPDATE teachers SET name=?, phone=?, email=?, username=?, password=?, branch=? WHERE id=?")
                ->execute([clean_string($t['name'] ?? '', 150), clean_string($t['phone'] ?? '', 30), clean_email($t['email'] ?? ''), clean_string($t['username'] ?? '', 100), $hash, $branch ?: null, $tidInt]);
            } else {
                 $pdo->prepare("UPDATE teachers SET name=?, phone=?, email=?, username=?, branch=? WHERE id=?")
                ->execute([clean_string($t['name'] ?? '', 150), clean_string($t['phone'] ?? '', 30), clean_email($t['email'] ?? ''), clean_string($t['username'] ?? '', 100), $branch ?: null, $tidInt]);
            }
        } else {
            $hash = password_hash(clean_password($t['password'] ?? '', 255), PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO teachers (name, phone, email, username, password, branch) VALUES (?,?,?,?,?,?)")
                ->execute([clean_string($t['name'] ?? '', 150), clean_string($t['phone'] ?? '', 30), clean_email($t['email'] ?? ''), clean_string($t['username'] ?? '', 100), $hash, $branch ?: null]);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'delete_teacher') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $id = clean_int($data['id'] ?? null);
        if (!$id) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $pdo->prepare("DELETE FROM teachers WHERE id=?")->execute([$id]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_user') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $u = $data;
        $hash = password_hash(clean_password($u['password'] ?? '', 255), PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?,?,?,?)")
            ->execute([clean_string($u['name'] ?? '', 150), clean_string($u['username'] ?? '', 100), $hash, 'admin']);
        echo json_encode(['status'=>'success']); exit;
    }
    
    if ($action === 'delete_user') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $id = clean_int($data['id'] ?? null);
        if (!$id) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'create_period') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $name = clean_string($data['name'] ?? '', 150);
        $startDate = clean_date($data['start_date'] ?? null);
        $endDate = clean_date($data['end_date'] ?? null);
        if (!$name) {
            json_response(['status' => 'error', 'message' => 'Dönem adı zorunludur'], 400);
        }
        if ($startDate && $endDate && $startDate > $endDate) {
            json_response(['status' => 'error', 'message' => 'Geçersiz tarih aralığı'], 400);
        }
        $pdo->prepare("INSERT INTO course_periods (name, start_date, end_date, is_active, is_deleted) VALUES (?, ?, ?, 0, 0)")
            ->execute([$name, $startDate, $endDate]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'update_period') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $id = clean_int($data['id'] ?? null);
        $name = clean_string($data['name'] ?? '', 150);
        $startDate = clean_date($data['start_date'] ?? null);
        $endDate = clean_date($data['end_date'] ?? null);
        if (!$id || !$name) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        if ($startDate && $endDate && $startDate > $endDate) {
            json_response(['status' => 'error', 'message' => 'Geçersiz tarih aralığı'], 400);
        }
        $stmt = $pdo->prepare("UPDATE course_periods SET name=?, start_date=?, end_date=? WHERE id=? AND is_deleted=0");
        $stmt->execute([$name, $startDate, $endDate, $id]);
        if ($stmt->rowCount() === 0) {
            json_response(['status' => 'error', 'message' => 'Dönem bulunamadı'], 404);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'delete_period') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $id = clean_int($data['id'] ?? null);
        if (!$id) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        if ($id === $activePeriodId) {
            json_response(['status' => 'error', 'message' => 'Aktif dönem silinemez. Önce başka bir dönemi aktif yapın.'], 400);
        }
        $stmt = $pdo->prepare("SELECT is_active, is_deleted FROM course_periods WHERE id=?");
        $stmt->execute([$id]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$period || (int)$period['is_deleted'] === 1) {
            json_response(['status' => 'error', 'message' => 'Dönem bulunamadı'], 404);
        }
        if ((int)$period['is_active'] === 1) {
            json_response(['status' => 'error', 'message' => 'Aktif dönem silinemez. Önce başka bir dönemi aktif yapın.'], 400);
        }
        $pdo->prepare("UPDATE course_periods SET is_deleted=1, is_active=0 WHERE id=?")->execute([$id]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'activate_period') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $id = clean_int($data['id'] ?? null);
        if (!$id) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE course_periods SET is_active=0 WHERE is_deleted=0")->execute();
            $stmt = $pdo->prepare("UPDATE course_periods SET is_active=1 WHERE id=? AND is_deleted=0");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Dönem bulunamadı');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_response(['status' => 'error', 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'İşlem başarısız'], 400);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'get_report_data') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $periodId = clean_int($data['period_id'] ?? null);
        if (!$periodId) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        migrate_attendance_statuses($pdo, $periodId);
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE period_id=?");
        $stmt->execute([$periodId]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($courses as &$c) {
            $c['teacherId'] = $c['teacher_id'];
            $c['cancelledDates'] = json_decode($c['cancelled_dates']) ?: [];
            $c['modifications'] = json_decode($c['modifications']) ?: (object)[];
            unset($c['cancelled_dates'], $c['modifications_json']);
        }
        $stmt = $pdo->prepare("SELECT s.* FROM students s INNER JOIN student_periods sp ON sp.student_id = s.id WHERE sp.period_id=?");
        $stmt->execute([$periodId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($students as &$s) {
            $stmt2 = $pdo->prepare("SELECT course_id FROM student_courses WHERE student_id = ? AND period_id=?");
            $stmt2->execute([$s['id'], $periodId]);
            $s['courses'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        }
        $stmt = $pdo->prepare("SELECT course_id, student_id, date, status FROM attendance WHERE period_id=?");
        $stmt->execute([$periodId]);
        $att = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cleanAtt = [];
        foreach($att as $a) {
            $normalizedStatus = normalize_attendance_status($a['status']);
            if ($normalizedStatus === null) {
                continue;
            }
            $cleanAtt[] = ['courseId' => (int)$a['course_id'], 'studentId' => (int)$a['student_id'], 'date' => $a['date'], 'status' => $normalizedStatus];
        }
        echo json_encode(['status' => 'success', 'courses' => $courses, 'students' => $students, 'attendance' => $cleanAtt]);
        exit;
    }

    if ($action === 'reset_data') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $type = clean_string($data['type'] ?? '', 50);
        $valid = ['student_registrations', 'courses', 'attendance', 'all'];
        if (!in_array($type, $valid, true)) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        try {
            $pdo->beginTransaction();
            if ($type === 'attendance') {
                $pdo->prepare("DELETE FROM attendance WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM teacher_evaluations WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM certificates WHERE period_id=?")->execute([$activePeriodId]);
            } elseif ($type === 'courses') {
                $pdo->prepare("DELETE FROM attendance WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM student_courses WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM courses WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM teacher_evaluations WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM certificates WHERE period_id=?")->execute([$activePeriodId]);
            } elseif ($type === 'student_registrations') {
                $pdo->prepare("DELETE FROM attendance WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM student_courses WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM student_periods WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM teacher_evaluations WHERE period_id=?")->execute([$activePeriodId]);
                $pdo->prepare("DELETE FROM certificates WHERE period_id=?")->execute([$activePeriodId]);
            } elseif ($type === 'all') {
                $pdo->prepare("DELETE FROM attendance")->execute();
                $pdo->prepare("DELETE FROM student_courses")->execute();
                $pdo->prepare("DELETE FROM courses")->execute();
                $pdo->prepare("DELETE FROM student_periods")->execute();
                $pdo->prepare("DELETE FROM students")->execute();
                $pdo->prepare("DELETE FROM teacher_evaluations")->execute();
                $pdo->prepare("DELETE FROM certificates")->execute();
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_attendance') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $a = $data;
        $courseId = clean_int($a['courseId'] ?? null);
        $studentId = clean_int($a['studentId'] ?? null);
        $date = clean_date($a['date'] ?? null);
        $status = normalize_attendance_status($a['status'] ?? null);
        if (!$courseId || !$studentId || !$date || $status === null) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        require_course_access($pdo, $user, $courseId, $activePeriodId);
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE course_id=? AND student_id=? AND date=? AND period_id=?");
        $stmt->execute([$courseId, $studentId, $date, $activePeriodId]);
        $exist = $stmt->fetch();

        if ($exist) {
            $pdo->prepare("UPDATE attendance SET status=? WHERE id=?")->execute([$status, $exist['id']]);
        } else {
            $pdo->prepare("INSERT INTO attendance (course_id, student_id, date, status, period_id) VALUES (?,?,?,?,?)")
                ->execute([$courseId, $studentId, $date, $status, $activePeriodId]);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'add_course_student_new') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $courseId = clean_int($data['courseId'] ?? null);
        if (!$courseId) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        require_course_access($pdo, $user, $courseId, $activePeriodId);
        $name = clean_string($data['name'] ?? '', 100);
        $surname = clean_string($data['surname'] ?? '', 100);
        if (!$name || !$surname) {
            json_response(['status' => 'error', 'message' => 'Ad ve soyad zorunludur'], 400);
        }
        $phone = clean_string($data['phone'] ?? '', 30);
        $email = clean_email($data['email'] ?? '');
        $tc = clean_string($data['tc'] ?? '', 20);
        $dob = clean_date($data['date_of_birth'] ?? null);
        $education = clean_string($data['education'] ?? '', 150);
        $parentName = clean_string($data['parent_name'] ?? '', 150);
        $parentPhone = clean_string($data['parent_phone'] ?? '', 30);
        try {
            $pdo->beginTransaction();
            $sql = "INSERT INTO students (name, surname, phone, email, tc, date_of_birth, education, parent_name, parent_phone, reg_date) VALUES (?,?,?,?,?,?,?,?,?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $surname, $phone, $email, $tc, $dob, $education, $parentName, $parentPhone]);
            $studentId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO student_periods (student_id, period_id, reg_date) VALUES (?, ?, CURDATE())")->execute([$studentId, $activePeriodId]);
            $pdo->prepare("INSERT INTO student_courses (student_id, course_id, period_id) VALUES (?, ?, ?)")->execute([$studentId, $courseId, $activePeriodId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'add_course_student_existing') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $courseId = clean_int($data['courseId'] ?? null);
        $studentId = clean_int($data['studentId'] ?? null);
        if (!$courseId || !$studentId) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        require_course_access($pdo, $user, $courseId, $activePeriodId);
        $stmt = $pdo->prepare("SELECT 1 FROM student_courses WHERE student_id=? AND course_id=? AND period_id=?");
        $stmt->execute([$studentId, $courseId, $activePeriodId]);
        if (!$stmt->fetchColumn()) {
            $pdo->prepare("INSERT IGNORE INTO student_periods (student_id, period_id, reg_date) SELECT id, ?, COALESCE(reg_date, CURDATE()) FROM students WHERE id=?")->execute([$activePeriodId, $studentId]);
            $pdo->prepare("INSERT INTO student_courses (student_id, course_id, period_id) VALUES (?, ?, ?)")->execute([$studentId, $courseId, $activePeriodId]);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'add_course_student_existing_bulk') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $courseId = clean_int($data['courseId'] ?? null);
        $studentIds = $data['studentIds'] ?? [];
        if (!$courseId || !is_array($studentIds)) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $cleanStudentIds = [];
        foreach ($studentIds as $studentId) {
            $cleanId = clean_int($studentId);
            if ($cleanId) {
                $cleanStudentIds[] = $cleanId;
            }
        }
        $cleanStudentIds = array_values(array_unique($cleanStudentIds));
        if (!$cleanStudentIds) {
            json_response(['status' => 'error', 'message' => 'Öğrenci seçiniz.'], 400);
        }
        require_course_access($pdo, $user, $courseId, $activePeriodId);
        $placeholders = implode(',', array_fill(0, count($cleanStudentIds), '?'));
        $params = array_merge([$courseId, $activePeriodId], $cleanStudentIds);
        $stmt = $pdo->prepare("SELECT student_id FROM student_courses WHERE course_id=? AND period_id=? AND student_id IN ($placeholders)");
        $stmt->execute($params);
        $existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $existingLookup = array_fill_keys($existingIds ?: [], true);
        $newIds = array_values(array_filter($cleanStudentIds, fn($sid) => empty($existingLookup[$sid])));
        if ($newIds) {
            try {
                $pdo->beginTransaction();
                $periodStmt = $pdo->prepare("INSERT IGNORE INTO student_periods (student_id, period_id, reg_date) VALUES (?, ?, CURDATE())");
                $courseStmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_id, period_id) VALUES (?, ?, ?)");
                foreach ($newIds as $studentId) {
                    $periodStmt->execute([$studentId, $activePeriodId]);
                    $courseStmt->execute([$studentId, $courseId, $activePeriodId]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'remove_course_student') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $courseId = clean_int($data['courseId'] ?? null);
        $studentId = clean_int($data['studentId'] ?? null);
        if (!$courseId || !$studentId) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        require_course_access($pdo, $user, $courseId, $activePeriodId);
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM student_courses WHERE student_id=? AND course_id=? AND period_id=?")->execute([$studentId, $courseId, $activePeriodId]);
            $pdo->prepare("DELETE FROM attendance WHERE student_id=? AND course_id=? AND period_id=?")->execute([$studentId, $courseId, $activePeriodId]);
            $pdo->prepare("DELETE FROM teacher_evaluations WHERE student_id=? AND course_id=? AND period_id=?")->execute([$studentId, $courseId, $activePeriodId]);
            $pdo->prepare("DELETE FROM certificates WHERE student_id=? AND course_id=? AND period_id=?")->execute([$studentId, $courseId, $activePeriodId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_meta') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $k = clean_string($data['key'] ?? '', 50);
        $value = $data['value'] ?? '';
        $v = is_array($value) ? json_encode($value) : clean_string((string)$value, 500);
        $pdo->prepare("REPLACE INTO meta_data (item_key, item_value) VALUES (?, ?)")->execute([$k, $v]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_certificate_settings') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $institutionName = clean_string($data['institution_name'] ?? '', 150);
        $logoUrl = clean_string($data['logo_url'] ?? '', 500);
        $certificateText = clean_string($data['certificate_text'] ?? '', 2000);
        $signaturePrimaryName = clean_string($data['signature_primary_name'] ?? '', 150);
        $signaturePrimaryTitle = clean_string($data['signature_primary_title'] ?? '', 150);
        $signatureSecondaryName = clean_string($data['signature_secondary_name'] ?? '', 150);
        $signatureSecondaryTitle = clean_string($data['signature_secondary_title'] ?? '', 150);
        $minAttendance = clean_float($data['min_attendance'] ?? null);
        $minScore = clean_float($data['min_score'] ?? null);
        if ($minAttendance === null || $minScore === null || $minAttendance < 0 || $minAttendance > 100 || $minScore < 0 || $minScore > 100) {
            json_response(['status' => 'error', 'message' => 'Geçersiz kriter değerleri'], 400);
        }
        $stmt = $pdo->query("SELECT id FROM certificate_settings ORDER BY id DESC LIMIT 1");
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            $pdo->prepare("UPDATE certificate_settings SET institution_name=?, logo_url=?, certificate_text=?, signature_primary_name=?, signature_primary_title=?, signature_secondary_name=?, signature_secondary_title=?, min_attendance=?, min_score=? WHERE id=?")
                ->execute([
                    $institutionName,
                    $logoUrl,
                    $certificateText,
                    $signaturePrimaryName,
                    $signaturePrimaryTitle,
                    $signatureSecondaryName,
                    $signatureSecondaryTitle,
                    $minAttendance,
                    $minScore,
                    $existingId
                ]);
        } else {
            $pdo->prepare("INSERT INTO certificate_settings (institution_name, logo_url, certificate_text, signature_primary_name, signature_primary_title, signature_secondary_name, signature_secondary_title, min_attendance, min_score) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $institutionName,
                    $logoUrl,
                    $certificateText,
                    $signaturePrimaryName,
                    $signaturePrimaryTitle,
                    $signatureSecondaryName,
                    $signatureSecondaryTitle,
                    $minAttendance,
                    $minScore
                ]);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'get_evaluations') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $courseId = clean_int($data['courseId'] ?? null);
        $evaluationPeriod = clean_year_month($data['evaluationPeriod'] ?? null);
        if (!$courseId || !$evaluationPeriod) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        require_course_access($pdo, $user, $courseId, $activePeriodId);
        $stmt = $pdo->prepare("SELECT student_id, score FROM teacher_evaluations WHERE course_id=? AND period_id=? AND evaluation_period=?");
        $stmt->execute([$courseId, $activePeriodId, $evaluationPeriod]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'evaluations' => $rows]); exit;
    }

    if ($action === 'save_evaluation') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        $courseId = clean_int($data['courseId'] ?? null);
        $studentId = clean_int($data['studentId'] ?? null);
        $evaluationPeriod = clean_year_month($data['evaluationPeriod'] ?? null);
        if (!$courseId || !$studentId || !$evaluationPeriod || $activePeriodId <= 0) {
            json_response(['status' => 'error', 'message' => 'Gerekli bilgiler eksik'], 400);
        }
        $rawScore = $data['score'] ?? null;
        if (is_string($rawScore)) {
            $rawScore = trim($rawScore);
        } elseif (is_numeric($rawScore)) {
            $rawScore = (string)$rawScore;
        } else {
            $rawScore = '';
        }
        if ($rawScore === '') {
            json_response(['status' => 'success', 'message' => 'Puan boş olduğu için kayıt yapılmadı']);
        }
        $score = clean_float($rawScore);
        if ($score === null) {
            json_response(['status' => 'success', 'message' => 'Geçersiz puan için kayıt yapılmadı']);
        }
        if ($score < 0 || $score > 100) {
            json_response(['status' => 'error', 'message' => 'Puan aralığı geçersiz'], 400);
        }
        require_course_access($pdo, $user, $courseId, $activePeriodId);
        $stmt = $pdo->prepare("SELECT id FROM student_courses WHERE student_id=? AND course_id=? AND period_id=?");
        $stmt->execute([$studentId, $courseId, $activePeriodId]);
        if (!$stmt->fetchColumn()) {
            json_response(['status' => 'error', 'message' => 'Öğrenci kursa kayıtlı değil'], 400);
        }
        $teacherId = $user['role'] === 'teacher' ? (int)($user['id'] ?? 0) : null;
        if (!$teacherId) {
            $courseStmt = $pdo->prepare("SELECT teacher_id FROM courses WHERE id=? AND period_id=?");
            $courseStmt->execute([$courseId, $activePeriodId]);
            $teacherId = (int)($courseStmt->fetchColumn() ?: 0);
        }
        $existing = $pdo->prepare("SELECT id FROM teacher_evaluations WHERE course_id=? AND student_id=? AND period_id=? AND evaluation_period=?");
        $existing->execute([$courseId, $studentId, $activePeriodId, $evaluationPeriod]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $pdo->prepare("UPDATE teacher_evaluations SET score=?, teacher_id=? WHERE id=?")->execute([$score, $teacherId ?: null, $existingId]);
        } else {
            $pdo->prepare("INSERT INTO teacher_evaluations (course_id, student_id, period_id, evaluation_period, score, teacher_id) VALUES (?,?,?,?,?,?)")
                ->execute([$courseId, $studentId, $activePeriodId, $evaluationPeriod, $score, $teacherId ?: null]);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'get_certificates') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $courseId = clean_int($data['courseId'] ?? null);
        if (!$courseId) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $stmt = $pdo->prepare("SELECT c.id, c.student_id, c.issued_at, c.completion_date, s.name, s.surname FROM certificates c INNER JOIN students s ON s.id=c.student_id WHERE c.course_id=? AND c.period_id=? ORDER BY s.name, s.surname");
        $stmt->execute([$courseId, $activePeriodId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'certificates' => $rows]); exit;
    }

    if ($action === 'generate_certificates') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $courseId = clean_int($data['courseId'] ?? null);
        $studentId = clean_int($data['studentId'] ?? null);
        $force = !empty($data['force']) ? true : false;
        if (!$courseId) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $courseStmt = $pdo->prepare("SELECT c.name, c.teacher_id, p.name AS period_name FROM courses c INNER JOIN course_periods p ON p.id=c.period_id WHERE c.id=? AND c.period_id=?");
        $courseStmt->execute([$courseId, $activePeriodId]);
        $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) {
            json_response(['status' => 'error', 'message' => 'Kurs bulunamadı'], 404);
        }
        $teacherName = '';
        if (!empty($course['teacher_id'])) {
            $teacherStmt = $pdo->prepare("SELECT name FROM teachers WHERE id=?");
            $teacherStmt->execute([$course['teacher_id']]);
            $teacherName = (string)$teacherStmt->fetchColumn();
        }
        $meta = $pdo->query("SELECT * FROM meta_data")->fetchAll(PDO::FETCH_KEY_PAIR);
        $certSettings = get_certificate_settings($pdo, $meta);
        $studentsQuery = "SELECT s.id, s.name, s.surname FROM students s INNER JOIN student_courses sc ON sc.student_id=s.id WHERE sc.course_id=? AND sc.period_id=?";
        $params = [$courseId, $activePeriodId];
        if ($studentId) {
            $studentsQuery .= " AND s.id=?";
            $params[] = $studentId;
        }
        $stmt = $pdo->prepare($studentsQuery);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$students) {
            json_response(['status' => 'error', 'message' => 'Öğrenci bulunamadı'], 404);
        }
        $minAttendance = (float)$certSettings['min_attendance'];
        $minScore = (float)$certSettings['min_score'];
        $generated = 0;
        $skippedExisting = 0;
        $skippedIneligible = 0;
        $completionDate = date('Y-m-d');
        foreach ($students as $student) {
            $studentIdInt = (int)$student['id'];
            $attendanceStats = calculate_attendance_stats($pdo, $courseId, $studentIdInt, $activePeriodId);
            $evaluationAvg = get_evaluation_average($pdo, $courseId, $studentIdInt, $activePeriodId);
            $eligible = $attendanceStats['total'] > 0
                && $attendanceStats['rate'] >= $minAttendance
                && $evaluationAvg !== null
                && $evaluationAvg >= $minScore;
            if (!$eligible) {
                $skippedIneligible++;
                continue;
            }
            $payload = [
                'institution_name' => $certSettings['institution_name'],
                'logo_url' => $certSettings['logo_url'],
                'certificate_text' => $certSettings['certificate_text'],
                'student_name' => trim($student['name'] . ' ' . $student['surname']),
                'course_name' => $course['name'],
                'period_name' => $course['period_name'] ?? '',
                'completion_date' => $completionDate,
                'teacher_name' => $teacherName,
                'signatures' => [
                    ['name' => $certSettings['signature_primary_name'], 'title' => $certSettings['signature_primary_title']],
                    ['name' => $certSettings['signature_secondary_name'], 'title' => $certSettings['signature_secondary_title']],
                ],
                'attendance_rate' => $attendanceStats['rate'],
                'evaluation_average' => $evaluationAvg
            ];
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $existingStmt = $pdo->prepare("SELECT id FROM certificates WHERE student_id=? AND course_id=? AND period_id=?");
            $existingStmt->execute([$studentIdInt, $courseId, $activePeriodId]);
            $existingId = $existingStmt->fetchColumn();
            if ($existingId) {
                if ($force) {
                    $pdo->prepare("UPDATE certificates SET completion_date=?, certificate_payload=?, created_by=? WHERE id=?")
                        ->execute([$completionDate, $payloadJson, $user['id'] ?? null, $existingId]);
                    $generated++;
                } else {
                    $skippedExisting++;
                }
            } else {
                $pdo->prepare("INSERT INTO certificates (student_id, course_id, period_id, completion_date, certificate_payload, created_by) VALUES (?,?,?,?,?,?)")
                    ->execute([$studentIdInt, $courseId, $activePeriodId, $completionDate, $payloadJson, $user['id'] ?? null]);
                $generated++;
            }
        }
        echo json_encode([
            'status' => 'success',
            'generated' => $generated,
            'skipped_existing' => $skippedExisting,
            'skipped_ineligible' => $skippedIneligible
        ]);
        exit;
    }

    if ($action === 'save_announcement') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $id = clean_int($data['id'] ?? null);
        $title = clean_string($data['title'] ?? '', 150);
        $message = clean_string($data['message'] ?? '', 2000);
        $startDate = clean_date($data['start_date'] ?? null);
        $endDate = clean_date($data['end_date'] ?? null);
        $isActive = !empty($data['is_active']) ? 1 : 0;
        if (!$title || !$message) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        if ($startDate && $endDate && $startDate > $endDate) {
            json_response(['status' => 'error', 'message' => 'Geçersiz tarih aralığı'], 400);
        }

        if ($id) {
            $pdo->prepare("UPDATE announcements SET title=?, message=?, start_date=?, end_date=?, is_active=? WHERE id=?")
                ->execute([$title, $message, $startDate, $endDate, $isActive, $id]);
        } else {
            $pdo->prepare("INSERT INTO announcements (title, message, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?)")
                ->execute([$title, $message, $startDate, $endDate, $isActive]);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'delete_announcement') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $id = clean_int($data['id'] ?? null);
        if (!$id) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'add_holiday') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $date = clean_date($data['date'] ?? null);
        $name = clean_string($data['name'] ?? '', 150);
        if (!$date || !$name) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $pdo->prepare("INSERT INTO holidays (date, name) VALUES (?, ?)")->execute([$date, $name]);
        echo json_encode(['status'=>'success']); exit;
    }
    if ($action === 'delete_holiday') {
        if ($method !== 'POST') {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 405);
        }
        require_admin($user);
        $date = clean_date($data['date'] ?? null);
        if (!$date) {
            json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
        }
        $pdo->prepare("DELETE FROM holidays WHERE date=?")->execute([$date]);
        echo json_encode(['status'=>'success']); exit;
    }
    
    json_response(['status' => 'error', 'message' => 'Geçersiz istek'], 400);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Çeşme Belediyesi Kültür Müdürlüğü - Kurs Takip</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f4f8;min-height:100vh;position:relative;padding-bottom:30px;}
.header{background:linear-gradient(135deg,#1e3a5f,#2d5a87);color:#fff;padding:15px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.header h1{font-size:1.2em;margin:0}
.header span{font-size:0.9em;opacity:0.9}
.nav{background:#2d5a87;padding:10px;display:flex;flex-wrap:wrap;gap:5px}
.nav button{background:#fff;border:none;padding:8px 15px;border-radius:5px;cursor:pointer;font-size:0.85em;transition:all 0.2s}
.nav button:hover,.nav button.active{background:#ffd700;color:#1e3a5f}
.container{padding:15px;max-width:1400px;margin:0 auto}
.card{background:#fff;border-radius:10px;padding:15px;margin-bottom:15px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
.card h2{color:#1e3a5f;margin-bottom:15px;font-size:1.1em;border-bottom:2px solid #ffd700;padding-bottom:5px}
.form-group{margin-bottom:12px}
.form-group label{display:block;margin-bottom:5px;font-weight:600;color:#333;font-size:0.9em}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px;font-size:0.9em}
.form-group input[type="color"] {padding: 2px; height: 40px; cursor: pointer;}
.checkbox-group {max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #fafafa;}
.checkbox-item {display: flex; align-items: center; margin-bottom: 5px;}
.checkbox-item input {width: auto; margin-right: 10px;}
.btn{padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-size:0.9em;margin:2px;display:inline-flex;align-items:center;justify-content:center;gap:5px}
.btn-primary{background:#1e3a5f;color:#fff}
.btn-success{background:#28a745;color:#fff}
.btn-danger{background:#dc3545;color:#fff}
.btn-warning{background:#ffc107;color:#333}
.btn-info{background:#17a2b8;color:#fff}
.btn-secondary{background:#6c757d;color:#fff}
.btn:hover{opacity:0.9;transform:scale(1.02)}
/* Calendar Styles */
.calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}
.cal-header{background:#1e3a5f;color:#fff;padding:10px;text-align:center;font-weight:600;font-size:0.85em}
.cal-cell{background:#fff;min-height:120px;border:1px solid #ddd;padding:5px;cursor:pointer;font-size:0.8em;position:relative;transition:all 0.2s}
.calendar.month-view .cal-cell { min-height: 90px; }
.cal-cell:hover{filter:brightness(0.95)}
.cal-cell.today{border:3px solid #1e3a5f;z-index:2}
.cal-cell.weekend{background-color:#fff3e0;}
.cal-cell.holiday{background-color:#f8d7da;border:1px solid #f5c6cb;}
.cal-cell.other-month{background:#f9f9f9;opacity:0.5;color:#999}
.cal-date{font-weight:600;color:#1e3a5f;margin-bottom:5px;display:flex;justify-content:space-between}
.cal-holiday-label{color:#dc3545;font-size:0.75em;font-weight:bold;display:block;margin-bottom:3px}
.course-tag{padding:4px 6px;border-radius:4px;margin:3px 0;font-size:0.75em;cursor:pointer;display:block;border-left:4px solid rgba(0,0,0,0.2);font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,0.1)}
.course-tag:hover{filter:brightness(0.9)}
.course-tag.cancelled{background:#ffcdd2!important;color:#333!important;text-decoration:line-through;border-left-color:#dc3545}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;overflow-y:auto}
.modal-content{background:#fff;margin:20px auto;padding:20px;border-radius:10px;max-width:600px;width:95%;max-height:90vh;overflow-y:auto}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
.modal-close{font-size:1.5em;cursor:pointer;color:#666}
.table-responsive {width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;}
table{width:100%;border-collapse:collapse;font-size:0.85em;min-width: 600px;}
th,td{padding:10px;border:1px solid #ddd;text-align:left}
th{background:#1e3a5f;color:#fff}
tr:nth-child(even){background:#f9f9f9}
.login-container{max-width:400px;margin:50px auto;padding:20px}
.hidden{display:none!important}
.week-nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap;gap:10px}
.view-toggle{display:flex;gap:5px}
.attendance-list{max-height:300px;overflow-y:auto}
.attendance-item{display:flex;justify-content:space-between;align-items:center;padding:8px;border-bottom:1px solid #eee;flex-wrap: wrap; gap:10px;}
.attendance-actions{display:flex;gap:5px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:20px}
.stat-card{background:linear-gradient(135deg,#1e3a5f,#2d5a87);color:#fff;padding:15px;border-radius:10px;text-align:center}
.stat-card h3{font-size:1.8em}
.stat-card p{font-size:0.85em;opacity:0.9}
.conflict{background:#fff3cd;padding:10px;border-radius:5px;border-left:4px solid #ffc107;margin:10px 0}
.tabs{display:flex;gap:5px;margin-bottom:15px;flex-wrap:wrap}
.tab{padding:10px 20px;background:#e9ecef;border:none;cursor:pointer;border-radius:5px 5px 0 0;flex:1;min-width: 100px;}
.tab.active{background:#1e3a5f;color:#fff}
.tab-content{display:none}
.tab-content.active{display:block}
.filter-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;align-items:flex-end;background:#f8f9fa;padding:15px;border-radius:10px;border:1px solid #e9ecef}
.filter-row > .form-group {flex:1;min-width:180px;margin-bottom:0}
.export-buttons {margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;}
footer {position: absolute; bottom: 5px; width: 100%; text-align: center; font-size: 0.8em; color: rgba(0,0,0,0.3); font-style: italic;}

@media(max-width:768px){
    .calendar.month-view {grid-template-columns:repeat(1,1fr)!important;}
    .calendar.month-view .cal-header{display:none!important;}
    .calendar.month-view .cal-cell::before{content:attr(data-day);font-weight:600;display:block;margin-bottom:5px;color:#1e3a5f}
    .calendar:not(.month-view) {grid-template-columns:repeat(1,1fr)}
    .cal-header{display:none}
    .calendar:not(.month-view) .cal-cell::before{content:attr(data-day);font-weight:600;display:block;margin-bottom:5px;color:#1e3a5f}
    .header h1{font-size:1em}
    .nav button {flex: 1 0 45%; text-align: center;}
    .filter-row {flex-direction: column; align-items: stretch;}
    .filter-row > .form-group {width: 100%;}
    .week-nav {justify-content: center;}
    .week-nav h2 {font-size: 1.1em; margin: 10px 0; width: 100%; text-align: center;}
}
</style>
</head>
<body>
<div id="loginPage" class="login-container">
<div class="card">
<h2>🏛️ Çeşme Belediyesi Kültür Müdürlüğü</h2>
<p style="margin-bottom:20px;color:#666">Kurs Takip Sistemi (Web v1.6)</p>
<div class="form-group"><label>Kullanıcı Adı</label><input type="text" id="loginUser"></div>
<div class="form-group"><label>Şifre</label><input type="password" id="loginPass"></div>
<div class="form-group" style="display:flex;align-items:center;gap:10px">
    <input type="checkbox" id="rememberMe" style="width:auto;margin:0;">
    <label for="rememberMe" style="margin:0;font-weight:normal;cursor:pointer">Beni Hatırla</label>
</div>
<button class="btn btn-primary" style="width:100%" onclick="apiLogin()">Giriş Yap</button>
</div>
</div>
<div id="mainApp" class="hidden">
<div class="header">
<div><h1>🏛️ Çeşme Belediyesi Kültür Müdürlüğü</h1><span>Kurs Takip Sistemi</span></div>
<div>
    <span id="currentUser"></span> 
    <button class="btn btn-info btn-sm" onclick="openChangePasswordModal()">🔑 Şifre Değiştir</button>
    <button class="btn btn-danger btn-sm" onclick="logout()">Çıkış</button>
</div>
</div>
<div class="nav" id="navBar"></div>
<div class="container" id="mainContent"></div>
</div>
<div class="modal" id="modal"><div class="modal-content" id="modalContent"></div></div>
<footer>Created by İlhan Akdeniz</footer>
<script>
const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;
const SERVER_NOW = <?php echo json_encode(date('c')); ?>;
const SERVER_TIME_OFFSET = new Date(SERVER_NOW).getTime() - Date.now();
const DAYS=['Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi','Pazar'];
const ATT_STATUS_PRESENT = 1;
const ATT_STATUS_ABSENT = 2;
const ATT_STATUS_EXCUSED = 3;
const ATT_STATUS_LABELS = {
    [ATT_STATUS_PRESENT]: 'Geldi',
    [ATT_STATUS_ABSENT]: 'Gelmedi',
    [ATT_STATUS_EXCUSED]: 'Mazeretli'
};
const INITIAL_MOVABLE_HOLIDAYS=[
{date:'2025-03-30',name:'Ramazan Bayramı 1. Gün'},{date:'2025-03-31',name:'Ramazan Bayramı 2. Gün'},
{date:'2025-04-01',name:'Ramazan Bayramı 3. Gün'},{date:'2025-06-06',name:'Kurban Bayramı 1. Gün'},
{date:'2025-06-07',name:'Kurban Bayramı 2. Gün'},{date:'2025-06-08',name:'Kurban Bayramı 3. Gün'},
{date:'2025-06-09',name:'Kurban Bayramı 4. Gün'}
];

let data={users:[],teachers:[],courses:[],students:[],attendance:[],attendanceRange:null,holidays:[],buildings:[],classes:[],settings:{},certificateSettings:{},announcements:[],announcementsAll:[],periods:[],activePeriod:null};
let reportData=null;
let reportPeriodId=null;
let currentUser=null;
let currentViewDate=new Date();
let viewMode = 'week'; 
let currentBuildingFilter = localStorage.getItem('lastBuilding') || "";
let currentCustomRange = {start: '', end: ''};
let studentCourseFilterGroups = new Map();

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/`/g, '&#x60;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}

function getServerNow() {
    return new Date(Date.now() + SERVER_TIME_OFFSET);
}

function sanitizeColor(value) {
    const color = String(value ?? '').trim();
    return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(color) ? color : '#e3f2fd';
}

function getAnnouncementStorageKey(id) {
    const userKey = currentUser?.id ? String(currentUser.id) : 'guest';
    return `announcement_hidden_${userKey}_${id}`;
}

function isAnnouncementHidden(id) {
    return localStorage.getItem(getAnnouncementStorageKey(id)) === '1';
}

function hideAnnouncement(id) {
    localStorage.setItem(getAnnouncementStorageKey(id), '1');
    showCalendar();
}

function formatAnnouncementMessage(message) {
    return escapeHtml(message).replace(/\n/g, '<br>');
}

async function apiCall(action, payload = null, queryParams = null) {
    try {
        let url = '?action=' + action;
        if (!payload && queryParams) {
            const query = Object.entries(queryParams)
                .filter(([, value]) => value !== null && value !== undefined && value !== '')
                .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
                .join('&');
            if (query) {
                url += '&' + query;
            }
        }
        const options = payload ? {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({...payload, csrf_token: CSRF_TOKEN})
        } : undefined;
        const res = await fetch(url, options);
        if(!res.ok) throw new Error("Sunucu hatası");
        return await res.json();
    } catch(e) {
        alert("Bir hata oluştu: " + e.message);
        return null;
    }
}

async function refreshData(options = {}) {
    const range = getVisibleDateRange();
    const res = await apiCall('get_all_data', null, range);
    if(res) {
        data = res;
        if (data.attendance && Array.isArray(data.attendance)) {
            data.attendance = data.attendance.map(a => ({...a, status: Number(a.status)}));
        }
        if(!data.certificateSettings) data.certificateSettings = {};
        data.attendanceRange = res.attendanceRange || range;
        reportData = null;
        reportPeriodId = data.activePeriod ? data.activePeriod.id : null;
        if(!data.holidays || data.holidays.length === 0) data.holidays = [...INITIAL_MOVABLE_HOLIDAYS];
        if(!data.announcements) data.announcements = [];
        if(!data.announcementsAll) data.announcementsAll = [];
        if(currentUser && !options.skipRender) showApp(false); 
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const savedUser = localStorage.getItem('savedUser');
    const savedPass = localStorage.getItem('savedPass');
    if(savedUser && savedPass) {
        document.getElementById('loginUser').value = savedUser;
        document.getElementById('loginPass').value = atob(savedPass);
        document.getElementById('rememberMe').checked = true;
    }
});

async function apiLogin() {
    const u = document.getElementById('loginUser').value;
    const p = document.getElementById('loginPass').value;
    const remember = document.getElementById('rememberMe').checked;

    const res = await apiCall('login', {username:u, password:p});
    if(res && res.status === 'success') {
        currentUser = res.user;
        
        if(remember) {
            localStorage.setItem('savedUser', u);
            localStorage.setItem('savedPass', btoa(p));
        } else {
            localStorage.removeItem('savedUser');
            localStorage.removeItem('savedPass');
        }

        await refreshData();
        showApp(true);
    } else {
        alert(res ? res.message : 'Giriş başarısız');
    }
}

function logout(){
    apiCall('logout', {});
    currentUser=null;
    document.getElementById('loginPage').classList.remove('hidden');
    document.getElementById('mainApp').classList.add('hidden');
}

function showApp(firstTime){
    document.getElementById('loginPage').classList.add('hidden');
    document.getElementById('mainApp').classList.remove('hidden');
    document.getElementById('currentUser').textContent=String(currentUser.name)+' ('+String(currentUser.role)+')';
    renderNav();
    if(firstTime || document.querySelector('.calendar')) showCalendar();
}

function renderNav(){
    const isAdmin=currentUser.role==='admin';
    let html=`<button class="active" onclick="showCalendar()">📅 Takvim</button>`;
    if(isAdmin)html+=`<button onclick="showCourses()">📚 Kurslar</button><button onclick="showTeachers()">👨‍🏫 Öğretmenler</button>`;
    html+=`<button onclick="showStudents()">👨‍🎓 Öğrenciler</button>`;
    html+=`<button onclick="showReports()">📊 Raporlar</button>`;
    if(isAdmin)html+=`<button onclick="showAdmin()">⚙️ Ayarlar</button>`;
    document.getElementById('navBar').innerHTML=html;
}
function setActiveNav(idx){document.querySelectorAll('.nav button').forEach((b,i)=>b.classList.toggle('active',i===idx))}
function formatDate(d){
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getVisibleDateRange() {
    if (viewMode === 'custom' && currentCustomRange.start && currentCustomRange.end) {
        return {start: currentCustomRange.start, end: currentCustomRange.end};
    }
    if (viewMode === 'month') {
        const year = currentViewDate.getFullYear();
        const month = currentViewDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const startDayIndex = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - startDayIndex);
        const endDate = new Date(startDate);
        endDate.setDate(endDate.getDate() + 41);
        return {start: formatDate(startDate), end: formatDate(endDate)};
    }
    const day = currentViewDate.getDay();
    const diff = currentViewDate.getDate() - day + (day === 0 ? -6 : 1);
    const startOfWeek = new Date(currentViewDate);
    startOfWeek.setDate(diff);
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);
    return {start: formatDate(startOfWeek), end: formatDate(endOfWeek)};
}

async function loadAttendanceRange(range) {
    if (!range?.start || !range?.end) {
        return;
    }
    const res = await apiCall('get_attendance_range', null, range);
    if (res && res.status === 'success') {
        data.attendance = res.attendance || [];
        data.attendanceRange = res.attendanceRange || range;
    }
}

async function ensureAttendanceRangeForView() {
    const range = getVisibleDateRange();
    if (!data.attendanceRange || data.attendanceRange.start !== range.start || data.attendanceRange.end !== range.end) {
        await loadAttendanceRange(range);
    }
}
function getContrastYIQ(hexcolor){
    if(!hexcolor) return 'black';
    hexcolor = hexcolor.replace("#", "");
    var r = parseInt(hexcolor.substr(0,2),16);
    var g = parseInt(hexcolor.substr(2,2),16);
    var b = parseInt(hexcolor.substr(4,2),16);
    var yiq = ((r*299)+(g*587)+(b*114))/1000;
    return (yiq >= 128) ? 'black' : 'white';
}
function getHoliday(dateStr){
    const [y, m, d] = dateStr.split('-').map(Number);
    if(m===1&&d===1)return 'Yılbaşı';
    if(m===4&&d===23)return '23 Nisan Ulusal Egemenlik ve Çocuk Bayramı';
    if(m===5&&d===1)return '1 Mayıs Emek ve Dayanışma Günü';
    if(m===5&&d===19)return '19 Mayıs Atatürk\'ü Anma, Gençlik ve Spor Bayramı';
    if(m===7&&d===15)return '15 Temmuz Demokrasi ve Milli Birlik Günü';
    if(m===8&&d===30)return '30 Ağustos Zafer Bayramı';
    if(m===10&&d===29)return '29 Ekim Cumhuriyet Bayramı';
    const custom = data.holidays.find(h=>h.date===dateStr);
    if(custom) return custom.name;
    return null;
}

// --- ŞİFRE DEĞİŞTİRME ---
function openChangePasswordModal() {
    let html=`<div class="modal-header"><h2>🔑 Şifre Değiştir</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Yeni Şifre</label><input type="password" id="newPass"></div>
    <div class="form-group"><label>Yeni Şifre (Tekrar)</label><input type="password" id="newPass2"></div>
    <button class="btn btn-primary" onclick="changePassword()">Değiştir</button>`;
    showModal(html);
}

async function changePassword() {
    const p1 = document.getElementById('newPass').value;
    const p2 = document.getElementById('newPass2').value;
    if(p1 !== p2) return alert("Şifreler uyuşmuyor!");
    if(p1.length < 4) return alert("Şifre en az 4 karakter olmalı!");

    await apiCall('change_password', {id: currentUser.id, role: currentUser.role, newPass: p1});
    alert("Şifreniz başarıyla güncellendi.");
    closeModal();
}

// --- TAKVİM ---
function showCalendar(){
    setActiveNav(0);
    let dates=[];
    let title="";

    let customStart = document.getElementById('calStart')?.value;
    let customEnd = document.getElementById('calEnd')?.value;

    if(viewMode==='week'){
        const day=currentViewDate.getDay(),diff=currentViewDate.getDate()-day+(day===0?-6:1);
        const startOfWeek=new Date(currentViewDate); startOfWeek.setDate(diff);
        dates = Array.from({length:7},(_,i)=>{const dt=new Date(startOfWeek);dt.setDate(startOfWeek.getDate()+i);return dt});
        title = `${dates[0].toLocaleDateString('tr-TR')} - ${dates[6].toLocaleDateString('tr-TR')}`;
    } else if(viewMode === 'month') {
        const year=currentViewDate.getFullYear(), month=currentViewDate.getMonth();
        const firstDay=new Date(year,month,1);
        let startDayIndex = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1; 
        let iterDate = new Date(firstDay);
        iterDate.setDate(iterDate.getDate() - startDayIndex);
        title = firstDay.toLocaleDateString('tr-TR',{month:'long', year:'numeric'});
        for(let i=0; i<42; i++){dates.push(new Date(iterDate));iterDate.setDate(iterDate.getDate()+1);}
    } else if (viewMode === 'custom' && customStart && customEnd) {
        let d = new Date(customStart);
        const e = new Date(customEnd);
        while(d <= e) {
            dates.push(new Date(d));
            d.setDate(d.getDate() + 1);
        }
        title = `${new Date(customStart).toLocaleDateString('tr-TR')} - ${new Date(customEnd).toLocaleDateString('tr-TR')}`;
    }

    const visibleAnnouncements = (data.announcements || []).filter(a => !isAnnouncementHidden(a.id));
    let announcementsHtml = '';
    if (visibleAnnouncements.length) {
        announcementsHtml = `<div class="conflict" style="margin-bottom:10px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <strong>📣 Duyurular</strong>
        </div>
        ${visibleAnnouncements.map(a => `
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:6px 0;border-top:1px solid rgba(0,0,0,0.08);">
                <div>
                    <div style="font-weight:600;margin-bottom:4px;">${escapeHtml(a.title)}</div>
                    <div>${formatAnnouncementMessage(a.message)}</div>
                </div>
                <button class="btn btn-secondary" style="padding:4px 8px;font-size:0.75em" onclick="hideAnnouncement(${a.id})">Kapat</button>
            </div>
        `).join('')}
        </div>`;
    }

    let html=`<div class="card">
    ${announcementsHtml}
    <div class="filter-row" style="margin-bottom:10px; padding:10px; background:#e3f2fd; align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>Tesis Filtrele</label>
            <select id="calFilterBuilding" onchange="applyCalendarFilter()">
                <option value="">Tümü</option>
                ${data.buildings.map(b=>`<option value="${escapeAttr(b)}" ${currentBuildingFilter===b?'selected':''}>${escapeHtml(b)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Başlangıç</label>
            <input type="date" id="calStart" value="${escapeAttr(customStart||'')}">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Bitiş</label>
            <input type="date" id="calEnd" value="${escapeAttr(customEnd||'')}">
        </div>
        <div class="form-group" style="margin:0;">
             <button class="btn btn-primary" onclick="applyCalendarFilter()">Uygula</button>
        </div>
    </div>

    <div class="week-nav">
        <div><button class="btn btn-primary" onclick="changeDate(-1)">◀ Önceki</button>
        <button class="btn btn-primary" onclick="goToToday()">Bugün</button>
        <button class="btn btn-primary" onclick="changeDate(1)">Sonraki ▶</button></div>
        <h2 style="margin:0;border:none">${title}</h2>
        <div class="view-toggle">
            <button class="btn btn-outline ${viewMode==='week'?'active':''}" onclick="toggleView('week')">Hafta</button>
            <button class="btn btn-outline ${viewMode==='month'?'active':''}" onclick="toggleView('month')">Ay</button>
        </div>
    </div>
    
    <div class="calendar ${viewMode==='month'?'month-view':''}"><div class="cal-header">Pazartesi</div><div class="cal-header">Salı</div><div class="cal-header">Çarşamba</div>
    <div class="cal-header">Perşembe</div><div class="cal-header">Cuma</div><div class="cal-header">Cumartesi</div><div class="cal-header">Pazar</div>`;
    
    dates.forEach((dt,i)=>{
        const ds=formatDate(dt),today=formatDate(new Date())===ds,holidayName=getHoliday(ds);
        const isOtherMonth = viewMode==='month' && dt.getMonth() !== currentViewDate.getMonth();
        let classes = "cal-cell";
        if(today) classes += " today";
        if(isOtherMonth) classes += " other-month";
        if(holidayName) classes += " holiday"; else if(dt.getDay()===0 || dt.getDay()===6) classes += " weekend";
        
        const courses=getCoursesForDate(ds);
        
        html+=`<div class="${classes}" data-day="${DAYS[dt.getDay()===0?6:dt.getDay()-1]}" onclick="openDayModal('${ds}')">
        <div class="cal-date"><span>${dt.getDate()} ${viewMode==='week'?dt.toLocaleDateString('tr-TR',{month:'short'}):''}</span>
        ${holidayName ? '<span>🇹🇷</span>' : ''}</div>`;
        if(holidayName)html+=`<span class="cal-holiday-label">${escapeHtml(holidayName)}</span>`;
        courses.forEach(c=>{
            const cancelled=c.cancelledDates&&c.cancelledDates.includes(ds);
            const mod=c.modifications&&c.modifications[ds];
            const bgColor = sanitizeColor(c.color);
            const txtColor = getContrastYIQ(bgColor);
            html+=`<div class="course-tag${cancelled?' cancelled':''}" style="background-color:${bgColor};color:${txtColor}" 
            onclick="event.stopPropagation();openCourseDetail('${escapeAttr(c.id)}','${escapeAttr(ds)}')">
            ${escapeHtml(c.name)} <small>${escapeHtml(mod?mod.time:c.time)}</small></div>`;
        });
        html+=`</div>`;
    });
    html+=`</div></div>`;
    document.getElementById('mainContent').innerHTML=html;
    ensureAttendanceRangeForView();
}

function applyCalendarFilter(){
    currentBuildingFilter = document.getElementById('calFilterBuilding').value;
    localStorage.setItem('lastBuilding', currentBuildingFilter); 

    const s = document.getElementById('calStart').value;
    const e = document.getElementById('calEnd').value;
    if(s && e) {
        viewMode = 'custom';
        currentCustomRange = {start: s, end: e};
    } else if (viewMode === 'custom') {
        viewMode = 'week';
        currentCustomRange = {start: '', end: ''};
    }
    showCalendar();
    document.getElementById('calStart').value = s;
    document.getElementById('calEnd').value = e;
}

function toggleView(mode){viewMode=mode;showCalendar()}
function changeDate(dir){if(viewMode==='week') currentViewDate.setDate(currentViewDate.getDate()+dir*7);else currentViewDate.setMonth(currentViewDate.getMonth()+dir);showCalendar()}
function goToToday(){currentViewDate=new Date();showCalendar()}

function getCourseStartMinutes(timeStr){
    if(!timeStr) return Number.MAX_SAFE_INTEGER;
    const start = String(timeStr).split('-')[0].trim();
    const match = start.match(/^(\d{1,2})(?::(\d{2}))?/);
    if(!match) return Number.MAX_SAFE_INTEGER;
    const h = parseInt(match[1],10);
    const m = parseInt(match[2] || '0',10);
    if(Number.isNaN(h) || Number.isNaN(m)) return Number.MAX_SAFE_INTEGER;
    return h * 60 + m;
}

function getCoursesForDate(ds){
    const dt=new Date(ds),dayName=DAYS[dt.getDay()===0?6:dt.getDay()-1];
    const filtered = data.courses.filter(c=>{
        if(ds<c.startDate||ds>c.endDate) return false;
        if(c.day!==dayName) return false;
        if(currentBuildingFilter && c.building !== currentBuildingFilter) return false;
        if(currentUser && currentUser.role === 'teacher' && c.teacherId != currentUser.id) return false;
        return true;
    });
    return filtered
        .map((c, index) => {
            const mod = c.modifications && c.modifications[ds];
            const timeValue = getCourseStartMinutes(mod ? mod.time : c.time);
            return {course: c, index, timeValue};
        })
        .sort((a, b) => (a.timeValue - b.timeValue) || (a.index - b.index))
        .map(item => item.course);
}

function openDayModal(ds){
    const isAdmin=currentUser.role==='admin',courses=getCoursesForDate(ds);
    const holidayName=getHoliday(ds);
    let html=`<div class="modal-header"><h2>📅 ${new Date(ds).toLocaleDateString('tr-TR',{weekday:'long',day:'numeric',month:'long',year:'numeric'})}</h2>
    <span class="modal-close" onclick="closeModal()">×</span></div>`;
    if(holidayName)html+=`<div class="conflict" style="background:#f8d7da;border-left-color:#dc3545">🎉 Resmi Tatil: ${escapeHtml(holidayName)}</div>`;
    html+=`<h3 style="margin:15px 0">Bu Günün Kursları</h3>`;
    if(courses.length===0)html+=`<p style="color:#888">Bu gün için kurs bulunmuyor.</p>`;
    courses.forEach(c=>{
        const cancelled=c.cancelledDates&&c.cancelledDates.includes(ds);
        const mod=c.modifications&&c.modifications[ds];
        html+=`<div class="card" style="margin:10px 0;${cancelled?'opacity:0.5':''}">
        <strong style="color:${sanitizeColor(c.color||'#333')}">● ${escapeHtml(c.name)}</strong> ${cancelled?'(İPTAL)':''}<br>
        <small>⏰ ${escapeHtml(mod?mod.time:c.time)} | 📍 ${escapeHtml(mod?mod.classroom:c.classroom)} | 🏢 ${escapeHtml(mod?mod.building:c.building)}</small><br>
        <small>👨‍🏫 ${escapeHtml(data.teachers.find(t=>t.id==c.teacherId)?.name||'Atanmamış')}</small><br>`;
        if(isAdmin){
            if(!cancelled){
                html+=`<button class="btn btn-warning btn-sm" onclick="modifyCourse(${c.id},'${escapeAttr(ds)}')">Değiştir</button>
                <button class="btn btn-danger btn-sm" onclick="cancelCourse(${c.id},'${escapeAttr(ds)}')">İptal Et</button>`;
            } else {
                html+=`<button class="btn btn-success btn-sm" onclick="activateCourse(${c.id},'${escapeAttr(ds)}')">✅ Tekrar Aktif Et</button>`;
            }
        }
        if(!cancelled){
        html+=`<button class="btn btn-success btn-sm" onclick="openAttendance(${c.id},'${escapeAttr(ds)}')">Yoklama</button>`;
        }
        html+=`</div>`;
    });
    if(isAdmin){html+=`<hr style="margin:20px 0"><button class="btn btn-primary" onclick="openNewCourseModal('${ds}')">+ Bu Güne Kurs Ekle</button>`;}
    showModal(html);
}
function openCourseDetail(cid,ds){openAttendance(cid,ds)}

async function openAttendance(cid,ds){
    await ensureAttendanceRangeForView();
    const course=data.courses.find(c=>c.id==cid);
    if(!course)return;
    const students=data.students.filter(s=>s.courses && s.courses.includes(parseInt(cid)));
    const att=data.attendance.filter(a=>a.courseId==cid&&a.date===ds);
    const canManage = currentUser.role === 'admin' || (currentUser.role === 'teacher' && course.teacherId == currentUser.id);
    let html=`<div class="modal-header"><h2>📋 Yoklama: ${escapeHtml(course.name)}</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <p><strong>Tarih:</strong> ${new Date(ds).toLocaleDateString('tr-TR')}</p>
    ${canManage ? `<div class="attendance-actions" style="margin-bottom:10px">
        <button class="btn btn-primary btn-sm" onclick="openAttendanceNewStudent(${cid},'${escapeAttr(ds)}')">➕ Yeni Öğrenci</button>
        <button class="btn btn-info btn-sm" onclick="openAttendanceExistingStudent(${cid},'${escapeAttr(ds)}')">➕ Kayıtlı Öğrenci</button>
    </div>` : ''}
    <div class="attendance-list" style="margin-top:15px">`;
    if(students.length===0)html+=`<p style="color:#888">Bu kursa kayıtlı öğrenci yok.</p>`;
    students.forEach(s=>{
        const present=att.find(a=>a.studentId===s.id);
        html+=`<div class="attendance-item" data-course-id="${escapeAttr(cid)}" data-date="${escapeAttr(ds)}" data-student-id="${escapeAttr(s.id)}"><span style="cursor:pointer;text-decoration:underline" onclick="openStudentInfo(${s.id})">${escapeHtml(s.name)} ${escapeHtml(s.surname)}</span>
        <div class="attendance-actions">
        <button type="button" class="btn ${present?.status===ATT_STATUS_PRESENT?'btn-success':'btn-secondary'}" data-att-status="${ATT_STATUS_PRESENT}" onclick="markAttendance(event,${cid},'${escapeAttr(ds)}',${s.id},${ATT_STATUS_PRESENT})">✓</button>
        <button type="button" class="btn ${present?.status===ATT_STATUS_ABSENT?'btn-danger':'btn-secondary'}" data-att-status="${ATT_STATUS_ABSENT}" onclick="markAttendance(event,${cid},'${escapeAttr(ds)}',${s.id},${ATT_STATUS_ABSENT})">✗</button>
        <button type="button" class="btn ${present?.status===ATT_STATUS_EXCUSED?'btn-info':'btn-secondary'}" data-att-status="${ATT_STATUS_EXCUSED}" onclick="markAttendance(event,${cid},'${escapeAttr(ds)}',${s.id},${ATT_STATUS_EXCUSED})">M</button>
        ${canManage ? `<button type="button" class="btn btn-danger btn-sm" onclick="removeStudentFromCourse(${cid},${s.id},'${escapeAttr(ds)}')">Kaldır</button>` : ''}
        </div></div>`;
    });
    html+=`</div>`;
    showModal(html);
}

function openAttendanceNewStudent(cid,ds){
    let html=`<div class="modal-header"><h2>➕ Yoklamadan Yeni Öğrenci</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="filter-row" style="background:none; border:none; padding:0; margin:0;">
        <div class="form-group"><label>Ad</label><input type="text" id="attNewName"></div>
        <div class="form-group"><label>Soyad</label><input type="text" id="attNewSurname"></div>
    </div>
    <div class="filter-row" style="background:none; border:none; padding:0; margin:0;">
        <div class="form-group"><label>TC Kimlik</label><input type="text" id="attNewTc"></div>
        <div class="form-group"><label>Doğum Tarihi</label><input type="date" id="attNewDob"></div>
    </div>
    <div class="form-group"><label>Eğitim Durumu</label><input type="text" id="attNewEducation"></div>
    <div class="form-group"><label>Kendi Telefonu</label><input type="text" id="attNewPhone"></div>
    <div class="form-group"><label>E-posta</label><input type="email" id="attNewEmail"></div>
    <hr>
    <div class="filter-row" style="background:none; border:none; padding:0; margin:0;">
        <div class="form-group"><label>Veli Adı</label><input type="text" id="attNewParentName"></div>
        <div class="form-group"><label>Veli Telefonu</label><input type="text" id="attNewParentPhone"></div>
    </div>
    <button class="btn btn-primary" onclick="saveAttendanceNewStudent(${cid},'${escapeAttr(ds)}')">Kaydet</button>`;
    showModal(html);
}

async function saveAttendanceNewStudent(cid,ds){
    const payload = {
        courseId: cid,
        name: document.getElementById('attNewName').value,
        surname: document.getElementById('attNewSurname').value,
        tc: document.getElementById('attNewTc').value,
        date_of_birth: document.getElementById('attNewDob').value,
        education: document.getElementById('attNewEducation').value,
        phone: document.getElementById('attNewPhone').value,
        email: document.getElementById('attNewEmail').value,
        parent_name: document.getElementById('attNewParentName').value,
        parent_phone: document.getElementById('attNewParentPhone').value
    };
    const res = await apiCall('add_course_student_new', payload);
    if(res && res.status === 'success') {
        await refreshData({skipRender: true});
        openAttendance(cid,ds);
    }
}

function openAttendanceExistingStudent(cid,ds){
    const available = data.students.filter(s => !(s.courses && s.courses.includes(parseInt(cid))));
    let options = '<option value="">Seçiniz</option>';
    options += available.map(s => `<option value="${escapeAttr(s.id)}">${escapeHtml(s.name)} ${escapeHtml(s.surname)}</option>`).join('');
    const rows = available.map(s => `
        <tr>
            <td><input type="checkbox" class="att-existing-student" value="${escapeAttr(s.id)}"></td>
            <td>${escapeHtml(s.name)} ${escapeHtml(s.surname)}</td>
        </tr>
    `).join('');
    let html=`<div class="modal-header"><h2>➕ Kayıtlı Öğrenci Ekle</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Öğrenci</label>
    <select id="attExistingStudent">${options}</select></div>
    <button class="btn btn-primary" onclick="saveAttendanceExistingStudent(${cid},'${escapeAttr(ds)}')">Ekle</button>
    <hr>
    <div class="table-responsive">
        <table>
            <tr>
                <th><input type="checkbox" id="attExistingSelectAll" onclick="toggleAttendanceExistingStudents(this)"></th>
                <th>Öğrenci</th>
            </tr>
            ${rows || '<tr><td colspan="2">Eklenebilecek öğrenci bulunamadı.</td></tr>'}
        </table>
    </div>
    <button class="btn btn-primary" onclick="saveAttendanceExistingStudentsBulk(${cid},'${escapeAttr(ds)}')">Seçilen Öğrencileri Kursa Ekle</button>`;
    showModal(html);
}

async function saveAttendanceExistingStudent(cid,ds){
    const studentId = parseInt(document.getElementById('attExistingStudent').value);
    if(!studentId){alert('Öğrenci seçiniz.');return;}
    const res = await apiCall('add_course_student_existing', {courseId: cid, studentId});
    if(res && res.status === 'success') {
        await refreshData({skipRender: true});
        openAttendance(cid,ds);
    }
}

function toggleAttendanceExistingStudents(source){
    document.querySelectorAll('.att-existing-student').forEach(cb => {
        cb.checked = source.checked;
    });
}

async function saveAttendanceExistingStudentsBulk(cid,ds){
    const selected = Array.from(document.querySelectorAll('.att-existing-student:checked')).map(cb => parseInt(cb.value));
    if(!selected.length){alert('Öğrenci seçiniz.');return;}
    const res = await apiCall('add_course_student_existing_bulk', {courseId: cid, studentIds: selected});
    if(res && res.status === 'success') {
        await refreshData({skipRender: true});
        openAttendance(cid,ds);
    }
}

async function removeStudentFromCourse(cid,sid,ds){
    if(!confirm('Öğrenciyi bu kurstan kaldırmak istiyor musunuz?')) return;
    const res = await apiCall('remove_course_student', {courseId: cid, studentId: sid});
    if(res && res.status === 'success') {
        await refreshData({skipRender: true});
        openAttendance(cid,ds);
    }
}
function openStudentInfo(sid){
    const s = data.students.find(st => st.id === sid);
    if(!s) return;
    const courseNames = s.courses ? s.courses.map(cid => {
        const c = data.courses.find(x => x.id == cid);
        return c ? escapeHtml(c.name) : '';
    }).filter(Boolean).join(', ') : '';
    const birthDate = s.date_of_birth ? new Date(s.date_of_birth).toLocaleDateString('tr-TR') : null;
    const age = calculateStudentAge(s.date_of_birth);
    const ageLine = age !== null ? `Yaş: ${age}` : 'Yaş bilgisi bulunamadı';
    let html=`<div class="modal-header"><h2>👨‍🎓 Öğrenci Bilgileri</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div id="studentInfoPanel">
    <div class="form-group"><label>Ad Soyad</label><div>${escapeHtml(s.name)} ${escapeHtml(s.surname)}</div></div>
    <div class="form-group"><label>TC Kimlik</label><div>${escapeHtml(s.tc||'-')}</div></div>
    <div class="form-group"><label>Doğum Tarihi</label><div>${escapeHtml(birthDate || '-')}<br>${escapeHtml(ageLine)}</div></div>
    <div class="form-group"><label>Eğitim Durumu</label><div>${escapeHtml(s.education||'-')}</div></div>
    <div class="form-group"><label>Kendi Telefonu</label><div>${escapeHtml(s.phone||'-')}</div></div>
    <div class="form-group"><label>E-posta</label><div>${escapeHtml(s.email||'-')}</div></div>
    <div class="form-group"><label>Veli Adı</label><div>${escapeHtml(s.parent_name||'-')}</div></div>
    <div class="form-group"><label>Veli Telefonu</label><div>${escapeHtml(s.parent_phone||'-')}</div></div>
    <div class="form-group"><label>Kurslar</label><div>${courseNames||'-'}</div></div>
    </div>`;
    showModal(html);
}
function calculateStudentAge(dateString){
    if(!dateString) return null;
    const birthDate = new Date(dateString);
    if(Number.isNaN(birthDate.getTime())) return null;
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1;
    }
    return age;
}
function updateAttendanceButtonState(cid, ds, sid, status) {
    const row = document.querySelector(`.attendance-item[data-course-id="${cid}"][data-date="${ds}"][data-student-id="${sid}"]`);
    if (!row) return;
    const statusClasses = {
        [ATT_STATUS_PRESENT]: 'btn-success',
        [ATT_STATUS_ABSENT]: 'btn-danger',
        [ATT_STATUS_EXCUSED]: 'btn-info'
    };
    row.querySelectorAll('button[data-att-status]').forEach(btn => {
        const btnStatus = Number(btn.dataset.attStatus);
        btn.classList.remove('btn-success', 'btn-danger', 'btn-info', 'btn-secondary');
        if (btnStatus === status) {
            btn.classList.add(statusClasses[btnStatus] || 'btn-secondary');
        } else {
            btn.classList.add('btn-secondary');
        }
    });
}

async function markAttendance(event, cid, ds, sid, status){
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
        event.stopPropagation();
    }
    const res = await apiCall('save_attendance', {courseId:cid, date:ds, studentId:sid, status:status});
    if (res && res.status === 'success') {
        await refreshData({skipRender: true});
        updateAttendanceButtonState(cid, ds, sid, status);
    }
}
async function cancelCourse(cid,ds){
    if(!confirm('Bu günün dersini iptal etmek istiyor musunuz?'))return;
    const course=data.courses.find(c=>c.id==cid);
    if(!course.cancelledDates)course.cancelledDates=[];
    course.cancelledDates.push(ds);
    await apiCall('save_course', course);
    await refreshData();
    closeModal();
    showCalendar();
}
async function activateCourse(cid,ds){
    if(!confirm('Bu dersi tekrar aktif etmek istiyor musunuz?')) return;
    const course=data.courses.find(c=>c.id==cid);
    if(course && course.cancelledDates){
        course.cancelledDates = course.cancelledDates.filter(d => d !== ds);
        await apiCall('save_course', course);
        await refreshData();
        closeModal();
        showCalendar();
    }
}

function modifyCourse(cid,ds){
    const course=data.courses.find(c=>c.id==cid);
    const mod=course.modifications&&course.modifications[ds]||{};
    let html=`<div class="modal-header"><h2>✏️ Ders Değişikliği</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Yeni Saat</label><input type="text" id="modTime" value="${escapeAttr(mod.time||course.time)}" placeholder="15:00-17:00"></div>
    <div class="form-group"><label>Yeni Tesis</label><select id="modBuilding">${data.buildings.map(b=>`<option ${(mod.building||course.building)===b?'selected':''}>${escapeHtml(b)}</option>`).join('')}</select></div>
    <div class="form-group"><label>Yeni Sınıf</label><select id="modClass">${data.classes.map(c=>`<option ${(mod.classroom||course.classroom)===c?'selected':''}>${escapeHtml(c)}</option>`).join('')}</select></div>
    <div class="form-group"><label><input type="checkbox" id="modRange"> Tarih Aralığına Uygula</label></div>
    <div id="modRangeDates" class="hidden">
    <div class="form-group"><label>Başlangıç</label><input type="date" id="modStart" value="${escapeAttr(ds)}"></div>
    <div class="form-group"><label>Bitiş</label><input type="date" id="modEnd" value="${escapeAttr(ds)}"></div></div>
    <button class="btn btn-primary" onclick="saveModification(${cid},'${escapeAttr(ds)}')">Kaydet</button>`;
    showModal(html);
    document.getElementById('modRange').onchange=function(){document.getElementById('modRangeDates').classList.toggle('hidden',!this.checked)};
}
async function saveModification(cid,ds){
    const course=data.courses.find(c=>c.id==cid);
    if(!course.modifications)course.modifications={};
    const modData={time:document.getElementById('modTime').value,building:document.getElementById('modBuilding').value,classroom:document.getElementById('modClass').value};
    if(document.getElementById('modRange').checked){
        const start=document.getElementById('modStart').value,end=document.getElementById('modEnd').value;let d=new Date(start);
        while(formatDate(d)<=end){if(DAYS[d.getDay()===0?6:d.getDay()-1]===course.day)course.modifications[formatDate(d)]=modData;d.setDate(d.getDate()+1);}
    }else course.modifications[ds]=modData;
    await apiCall('save_course', course);
    await refreshData();
    closeModal();
    showCalendar();
}

function openNewCourseModal(ds, isEdit=false){
    let defaultBuilding = data.buildings[0];
    if(currentBuildingFilter && currentBuildingFilter !== "") {
        defaultBuilding = currentBuildingFilter;
    }

    const defaults = {name:'',day:'Pazartesi',time:'',building:defaultBuilding,classroom:data.classes[0],teacherId:'',start:'',end:'',color:'#e3f2fd'};
    if(ds){const dt=new Date(ds);defaults.day=DAYS[dt.getDay()===0?6:dt.getDay()-1];defaults.start=ds;}
    const templateOptions = data.courses.map(c=>`<option value="${escapeAttr(c.id)}">${escapeHtml(c.name)} (${escapeHtml(c.day)} ${escapeHtml(c.time||'')})</option>`).join('');
    let html=`<div class="modal-header"><h2>➕ Yeni Kurs</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Mevcut kurstan ayarları al</label><select id="cTemplate" onchange="applyCourseTemplate()" ${isEdit ? 'disabled' : ''}><option value="">Seçiniz</option>${templateOptions}</select></div>
    <div class="form-group"><label>Kurs Adı</label><input type="text" id="cName"></div>
    <div class="form-group"><label>Renk</label><input type="color" id="cColor" value="${escapeAttr(defaults.color)}"></div>
    <div class="form-group"><label>Gün</label><select id="cDay">${DAYS.map(d=>`<option ${d===defaults.day?'selected':''}>${escapeHtml(d)}</option>`).join('')}</select></div>
    <div class="form-group"><label>Saat (Örn: 15:30-22:30)</label><input type="text" id="cTime" placeholder="15:30-22:30"></div>
    <div class="form-group"><label>Tesis</label><select id="cBuilding">${data.buildings.map(b=>`<option ${b===defaults.building?'selected':''}>${escapeHtml(b)}</option>`).join('')}</select></div>
    <div class="form-group"><label>Sınıf/Atölye</label><select id="cClass">${data.classes.map(c=>`<option>${escapeHtml(c)}</option>`).join('')}</select></div>
    <div class="form-group"><label>Öğretmen</label><select id="cTeacher"><option value="">Seçiniz</option>${data.teachers.map(t=>`<option value="${escapeAttr(t.id)}">${escapeHtml(t.name)}</option>`).join('')}</select></div>
    <div class="form-group"><label>Başlangıç Tarihi</label><input type="date" id="cStart" value="${escapeAttr(defaults.start)}"></div>
    <div class="form-group"><label>Bitiş Tarihi</label><input type="date" id="cEnd"></div>
    <button class="btn btn-primary" onclick="saveCourse()">Kaydet</button>`;
    showModal(html);
}
function openCopyCourseModal(courseId){
    const course = data.courses.find(c => c.id == courseId);
    if(!course) return;
    const timeParts = parseCourseTime(course.time || '');
    const dayCheckboxes = DAYS.map(d => {
        const isChecked = d !== course.day;
        return `<label style="margin-right:10px;display:inline-flex;align-items:center;gap:6px;">
            <input type="checkbox" name="copyDay" value="${escapeAttr(d)}" ${isChecked ? 'checked' : ''}> ${escapeHtml(d)}
        </label>`;
    }).join('');
    let html=`<div class="modal-header"><h2>📋 Kurs Kopyala</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Kaynak Kurs</label><div>${escapeHtml(course.name)} (${escapeHtml(course.day)} ${escapeHtml(course.time || '')})</div></div>
    <div class="form-group"><label>Başlangıç Saati</label><input type="time" id="copyStartTime" value="${escapeAttr(timeParts.start)}"></div>
    <div class="form-group"><label>Bitiş Saati</label><input type="time" id="copyEndTime" value="${escapeAttr(timeParts.end)}"></div>
    <div class="form-group"><label>Hedef Günler</label><div>${dayCheckboxes}</div></div>
    <button class="btn btn-primary" onclick="copyCourseDays(${course.id})">Kopyala</button>`;
    showModal(html);
}
function parseCourseTime(timeRange){
    if(!timeRange) return {start:'', end:''};
    const match = timeRange.match(/^\s*(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})\s*$/);
    if(!match) return {start:'', end:''};
    return {start: match[1], end: match[2]};
}
function applyCourseTemplate(){
    const templateId = document.getElementById('cTemplate')?.value;
    if(!templateId) return;
    const template = data.courses.find(c=>c.id==templateId);
    if(!template) return;
    document.getElementById('cName').value = template.name || '';
    document.getElementById('cColor').value = template.color || '#e3f2fd';
    document.getElementById('cDay').value = template.day || document.getElementById('cDay').value;
    document.getElementById('cTime').value = template.time || '';
    document.getElementById('cBuilding').value = template.building || document.getElementById('cBuilding').value;
    document.getElementById('cClass').value = template.classroom || document.getElementById('cClass').value;
    document.getElementById('cTeacher').value = template.teacherId || '';
    if(!document.getElementById('cStart').value){
        document.getElementById('cStart').value = template.startDate || '';
    }
    if(!document.getElementById('cEnd').value){
        document.getElementById('cEnd').value = template.endDate || '';
    }
}
async function saveCourse(){
    const c={id:document.getElementById('cName').getAttribute('data-id') || 'new',
        name:document.getElementById('cName').value,color:document.getElementById('cColor').value,
        day:document.getElementById('cDay').value,time:document.getElementById('cTime').value,
        building:document.getElementById('cBuilding').value,classroom:document.getElementById('cClass').value,
        teacherId:document.getElementById('cTeacher').value,startDate:document.getElementById('cStart').value,
        endDate:document.getElementById('cEnd').value,cancelledDates:[],modifications:{},
        baseCourseId:document.getElementById('cTemplate')?.value || ''};
    await apiCall('save_course', c);
    await refreshData();
    closeModal();
    showCalendar();
}
async function copyCourseDays(courseId){
    const course = data.courses.find(c => c.id == courseId);
    if(!course) return;
    const selectedDays = Array.from(document.querySelectorAll('input[name="copyDay"]:checked')).map(cb => cb.value);
    if(selectedDays.length === 0){
        alert('Lütfen en az bir gün seçiniz.');
        return;
    }
    const startInput = document.getElementById('copyStartTime')?.value || '';
    const endInput = document.getElementById('copyEndTime')?.value || '';
    let timeRange = course.time || '';
    if (startInput || endInput) {
        if (!startInput || !endInput) {
            alert('Lütfen başlangıç ve bitiş saatini birlikte giriniz.');
            return;
        }
        timeRange = `${startInput}-${endInput}`;
    }
    const startDate = course.startDate ?? course.start_date ?? '';
    const endDate = course.endDate ?? course.end_date ?? '';
    const conflictCheck = await apiCall('check_copy_course_conflicts', {
        days: selectedDays,
        time: timeRange,
        building: course.building || '',
        classroom: course.classroom || '',
        teacherId: course.teacherId || '',
        startDate,
        endDate
    });
    if (!conflictCheck || conflictCheck.status !== 'success') {
        if (conflictCheck && conflictCheck.message) {
            alert(conflictCheck.message);
        }
        return;
    }
    for (const day of selectedDays) {
        const newCourse = {
            id: 'new',
            name: course.name || '',
            color: course.color || '#e3f2fd',
            day,
            time: timeRange,
            building: course.building || '',
            classroom: course.classroom || '',
            teacherId: course.teacherId || '',
            startDate,
            endDate,
            cancelledDates: [],
            modifications: {},
            baseCourseId: course.id
        };
        await apiCall('save_course', newCourse);
    }
    await refreshData();
    closeModal();
    showCourses();
}

function showCourses(){
    setActiveNav(1);
    let html=`<div class="card"><h2>📚 Kurs Yönetimi</h2>
    
    <div class="filter-row" style="background:#e3f2fd; padding:10px; margin-bottom:15px; border-radius:5px;">
        <div class="form-group" style="margin:0; flex:0 0 200px;">
            <label>Tesis Filtrele</label>
            <select id="courseFilterBuilding" onchange="filterCourses()">
                <option value="">Tümü</option>
                ${data.buildings.map(b=>`<option>${escapeHtml(b)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group" style="margin:0; align-self:flex-end;">
             <button class="btn btn-primary" onclick="openNewCourseModal()">+ Yeni Kurs</button>
        </div>
    </div>

    <div class="table-responsive"><table id="courseTable" style="margin-top:15px">
    <thead><tr><th>Kurs</th><th>Renk</th><th>Gün</th><th>Saat</th><th>Tesis</th><th>Sınıf</th><th>Öğretmen</th><th>İşlem</th></tr></thead>
    <tbody>`;
    data.courses.forEach(c=>{
        const t=data.teachers.find(x=>x.id==c.teacherId);
        html+=`<tr data-building="${escapeAttr(c.building)}"><td>${escapeHtml(c.name)}</td><td><span style="display:inline-block;width:20px;height:20px;background:${sanitizeColor(c.color)};border:1px solid #ccc;border-radius:3px"></span></td>
        <td>${escapeHtml(c.day)}</td><td>${escapeHtml(c.time)}</td><td>${escapeHtml(c.building)}</td><td>${escapeHtml(c.classroom)}</td><td>${escapeHtml(t?.name||'-')}</td>
        <td><button class="btn btn-warning" onclick="editCourse(${c.id})">✏️</button>
        <button class="btn btn-primary" onclick="openCopyCourseModal(${c.id})">📋</button>
        <button class="btn btn-danger" onclick="deleteCourse(${c.id})">🗑️</button></td></tr>`;
    });
    html+=`</tbody></table></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
}

function filterCourses(){
    const val = document.getElementById('courseFilterBuilding').value;
    const rows = document.querySelectorAll('#courseTable tbody tr');
    rows.forEach(r => {
        if(val === "" || r.getAttribute('data-building') === val) {
            r.style.display = "";
        } else {
            r.style.display = "none";
        }
    });
}

function editCourse(id){
    const c=data.courses.find(x=>x.id===id);
    openNewCourseModal(null, true);
    setTimeout(()=>{
        document.getElementById('cName').value=c.name;document.getElementById('cName').setAttribute('data-id', c.id);
        document.getElementById('cColor').value=c.color||'#e3f2fd';document.getElementById('cDay').value=c.day;
        document.getElementById('cTime').value=c.time;document.getElementById('cBuilding').value=c.building;
        document.getElementById('cClass').value=c.classroom;document.getElementById('cTeacher').value=c.teacherId;
        document.getElementById('cStart').value=c.startDate;document.getElementById('cEnd').value=c.endDate;
    }, 50);
}
async function deleteCourse(id){
    if(confirm('Kursu silmek istiyor musunuz?')){
        await apiCall('delete_course', {id});
        await refreshData();
        showCourses();
    }
}

function showTeachers(){
    setActiveNav(2);
    let html=`<div class="card"><h2>👨‍🏫 Öğretmen Yönetimi</h2>
    <div class="table-responsive"><button class="btn btn-primary" onclick="openTeacherModal()">+ Yeni Öğretmen</button>
    <table style="margin-top:15px"><tr><th>Ad Soyad</th><th>Telefon</th><th>E-posta</th><th>Kullanıcı Adı</th><th>Branş</th><th>İşlem</th></tr>`;
    data.teachers.forEach(t=>{
        html+=`<tr><td>${escapeHtml(t.name)}</td><td>${escapeHtml(t.phone||'-')}</td><td>${escapeHtml(t.email||'-')}</td><td>${escapeHtml(t.username)}</td><td>${escapeHtml(t.branch||'-')}</td>
        <td><button class="btn btn-warning" onclick="editTeacher(${t.id})">✏️</button>
        <button class="btn btn-danger" onclick="deleteTeacher(${t.id})">🗑️</button></td></tr>`;
    });
    html+=`</table></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
}
function openTeacherModal(t){
    let html=`<div class="modal-header"><h2>${t?'✏️ Düzenle':'➕ Yeni Öğretmen'}</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Ad Soyad</label><input type="text" id="tName" value="${escapeAttr(t?.name||'')}"></div>
    <div class="form-group"><label>Telefon</label><input type="text" id="tPhone" value="${escapeAttr(t?.phone||'')}"></div>
    <div class="form-group"><label>E-posta</label><input type="email" id="tEmail" value="${escapeAttr(t?.email||'')}"></div>
    <div class="form-group"><label>Kullanıcı Adı</label><input type="text" id="tUser" value="${escapeAttr(t?.username||'')}"></div>
    <div class="form-group"><label>Branş</label><input type="text" id="tBranch" value="${escapeAttr(t?.branch||'')}"></div>
    <div class="form-group"><label>Şifre</label><input type="password" id="tPass" value="${escapeAttr(t?.password||'')}"></div>
    <button class="btn btn-primary" onclick="saveTeacher(${t?.id||0})">${t?'Güncelle':'Kaydet'}</button>`;
    showModal(html);
}
function editTeacher(id){openTeacherModal(data.teachers.find(x=>x.id===id))}
async function saveTeacher(id){
    const t={id:id||'new',name:document.getElementById('tName').value,phone:document.getElementById('tPhone').value,
    email:document.getElementById('tEmail').value,username:document.getElementById('tUser').value,branch:document.getElementById('tBranch').value,password:document.getElementById('tPass').value};
    await apiCall('save_teacher', t);
    await refreshData();
    closeModal();
    showTeachers();
}
async function deleteTeacher(id){
    if(confirm('Silmek istiyor musunuz?')){
        await apiCall('delete_teacher', {id});
        await refreshData();
        showTeachers();
    }
}

// --- ÖĞRENCİLER ---
function showStudents(){
    setActiveNav(currentUser.role==='admin'?3:4);
    
    // YENİ EKLENEN KISIM: Öğretmen Filtresi
    const isTeacher = currentUser.role === 'teacher';
    let availableCourses = data.courses;
    
    // Eğer öğretmense sadece kendi kurslarını filtreye koyacağız
    if(isTeacher){
        availableCourses = data.courses.filter(c => c.teacherId == currentUser.id);
    }

    const courseFilterGroups = buildStudentCourseFilterGroups(availableCourses, data.students);
    studentCourseFilterGroups = new Map(courseFilterGroups.map(group => [group.key, group.courseIds]));

    let html=`<div class="card"><h2>👨‍🎓 Öğrenci Yönetimi</h2>
    <button class="btn btn-primary" onclick="openStudentModal()">+ Yeni Öğrenci</button>
    <div class="form-group" style="margin-top:15px"><label>Kurs Filtrele</label>
    <select id="studentFilter" onchange="filterStudents()">
        <option value="">Tümü</option>
        ${courseFilterGroups.map(group=>`<option value="${escapeAttr(group.key)}">${escapeHtml(group.name)}</option>`).join('')}
    </select></div>
    <div class="table-responsive"><table style="margin-top:15px"><tr><th>Ad</th><th>Soyad</th><th>Veli</th><th>Telefon</th><th>Kurslar</th><th>İşlem</th></tr>`;
    
    data.students.forEach(s=>{
        // EĞER ÖĞRETMENSE VE BU ÖĞRENCİ ÖĞRETMENİN HİÇBİR KURSUNA KAYITLI DEĞİLSE TABLOYA EKLEME
        if(isTeacher) {
            // Öğrencinin aldığı kurslardan en az biri öğretmenin kursları içinde var mı?
            const hasTeacherCourse = s.courses && s.courses.some(cid => availableCourses.find(ac => ac.id == cid));
            if(!hasTeacherCourse) return;
        }

        const courseNames = s.courses ? s.courses.map(cid => {const c = data.courses.find(x => x.id == cid); return c ? escapeHtml(c.name) : '';}).filter(n=>n).join(', ') : '';
        const courseIds = s.courses ? s.courses.join(',') : '';
        html+=`<tr data-courses="${escapeAttr(courseIds)}"><td>${escapeHtml(s.name)}</td><td>${escapeHtml(s.surname)}</td>
        <td>${escapeHtml(s.parent_name||'-')} (${escapeHtml(s.parent_phone||'-')})</td>
        <td>${escapeHtml(s.phone||'-')}</td>
        <td>${courseNames||'-'}</td>
        <td><button class="btn btn-warning" onclick="editStudent(${s.id})">✏️</button>
        <button class="btn btn-danger" onclick="deleteStudent(${s.id})">🗑️</button></td></tr>`;
    });
    html+=`</table></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
}
function filterStudents(){
    const v=document.getElementById('studentFilter').value;
    document.querySelectorAll('tr[data-courses]').forEach(r=>{
        if(!v) {
            r.style.display='';
            return;
        }
        const studentCourses = r.dataset.courses ? r.dataset.courses.split(',') : [];
        const groupCourseIds = studentCourseFilterGroups.get(v) || [];
        const matchesGroup = groupCourseIds.some(id => studentCourses.includes(String(id)));
        r.style.display = matchesGroup ? '' : 'none';
    });
}
function buildStudentCourseFilterGroups(availableCourses, students) {
    const studentIdsByCourse = new Map();
    students.forEach(student => {
        (student.courses || []).forEach(courseId => {
            if (!studentIdsByCourse.has(courseId)) {
                studentIdsByCourse.set(courseId, []);
            }
            studentIdsByCourse.get(courseId).push(student.id);
        });
    });

    const groups = new Map();
    availableCourses.forEach(course => {
        const studentIds = (studentIdsByCourse.get(course.id) || []).slice().sort((a, b) => a - b);
        const studentKey = studentIds.join(',');
        const teacherKey = course.teacherId ?? '';
        const periodKey = course.period_id ?? '';
        const nameKey = course.name ?? '';
        const key = `${nameKey}||${teacherKey}||${periodKey}||${studentKey}`;
        if (!groups.has(key)) {
            groups.set(key, {key, name: course.name, courseIds: [course.id]});
        } else {
            groups.get(key).courseIds.push(course.id);
        }
    });

    return Array.from(groups.values()).sort((a, b) => a.name.localeCompare(b.name, 'tr'));
}
function openStudentModal(s){
    let html=`<div class="modal-header"><h2>${s?'✏️ Düzenle':'➕ Yeni Öğrenci'}</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="filter-row" style="background:none; border:none; padding:0; margin:0;">
        <div class="form-group"><label>Ad</label><input type="text" id="sName" value="${escapeAttr(s?.name||'')}"></div>
        <div class="form-group"><label>Soyad</label><input type="text" id="sSurname" value="${escapeAttr(s?.surname||'')}"></div>
    </div>
    <div class="filter-row" style="background:none; border:none; padding:0; margin:0;">
        <div class="form-group"><label>TC Kimlik</label><input type="text" id="sTc" value="${escapeAttr(s?.tc||'')}"></div>
        <div class="form-group"><label>Doğum Tarihi</label><input type="date" id="sDob" value="${escapeAttr(s?.date_of_birth||'')}"></div>
    </div>
    <div class="form-group"><label>Eğitim Durumu</label><input type="text" id="sEducation" value="${escapeAttr(s?.education||'')}"></div>
    <div class="form-group"><label>Kendi Telefonu</label><input type="text" id="sPhone" value="${escapeAttr(s?.phone||'')}"></div>
    <div class="form-group"><label>E-posta</label><input type="email" id="sEmail" value="${escapeAttr(s?.email||'')}"></div>
    <hr>
    <div class="filter-row" style="background:none; border:none; padding:0; margin:0;">
        <div class="form-group"><label>Veli Adı</label><input type="text" id="sParentName" value="${escapeAttr(s?.parent_name||'')}"></div>
        <div class="form-group"><label>Veli Telefonu</label><input type="text" id="sParentPhone" value="${escapeAttr(s?.parent_phone||'')}"></div>
    </div>
    <div class="form-group"><label>Kurslar</label><div class="checkbox-group">`;
    data.courses.forEach(c => {
        const isChecked = s && s.courses && s.courses.includes(c.id);
        html += `<div class="checkbox-item"><input type="checkbox" name="courseSelect" value="${escapeAttr(c.id)}" ${isChecked ? 'checked' : ''}><span>${escapeHtml(c.name)}</span></div>`;
    });
    html+=`</div></div><button class="btn btn-primary" onclick="saveStudent(${s?.id||0})">${s?'Güncelle':'Kaydet'}</button>`;
    showModal(html);
}
function editStudent(id){openStudentModal(data.students.find(x=>x.id===id))}
async function saveStudent(id){
    const selectedCourses = Array.from(document.querySelectorAll('input[name="courseSelect"]:checked')).map(cb => parseInt(cb.value));
    const s={
        id:id||'new',
        name:document.getElementById('sName').value,
        surname:document.getElementById('sSurname').value,
        phone:document.getElementById('sPhone').value,
        email:document.getElementById('sEmail').value,
        tc:document.getElementById('sTc').value,
        date_of_birth:document.getElementById('sDob').value,
        education:document.getElementById('sEducation').value,
        parent_name:document.getElementById('sParentName').value,
        parent_phone:document.getElementById('sParentPhone').value,
        courses: selectedCourses
    };
    await apiCall('save_student', s);
    await refreshData();
    closeModal();
    showStudents();
}
async function deleteStudent(id){
    if(confirm('Silmek istiyor musunuz?')){
        await apiCall('delete_student', {id});
        await refreshData();
        showStudents();
    }
}

// --- RAPORLAR ---
function showReports(){
    setActiveNav(4);
    const source = reportData || data;
    const isTeacher = currentUser.role === 'teacher';
    const isAdmin = currentUser.role === 'admin';
    
    // Öğretmen ise sadece kendi adını seçili getir, disabled yap
    let teacherSelect = '';
    // Kurs seçimi için sadece kendi kurslarını göster
    let courseOptions = '<option value="">Tümü</option>';
    let studentOptions = '<option value="">Tümü</option>';

    let availableCourses = source.courses;
    let availableStudents = source.students;

    if(isTeacher) {
        teacherSelect = `<select id="rTeacher" disabled><option value="${escapeAttr(currentUser.id)}">${escapeHtml(currentUser.name)}</option></select>`;
        availableCourses = source.courses.filter(c => c.teacherId == currentUser.id);
        
        // Sadece bu kurslara kayıtlı öğrencileri bul
        const teacherCourseIds = availableCourses.map(c => c.id);
        availableStudents = source.students.filter(s => {
            return s.courses && s.courses.some(cid => teacherCourseIds.includes(cid));
        });

    } else {
        teacherSelect = `<select id="rTeacher"><option value="">Tümü</option>${data.teachers.map(t=>`<option value="${escapeAttr(t.id)}">${escapeHtml(t.name)}</option>`).join('')}</select>`;
    }

    courseOptions += availableCourses.map(c=>`<option value="${escapeAttr(c.id)}">${escapeHtml(c.name)}</option>`).join('');
    studentOptions += availableStudents.map(s=>`<option value="${escapeAttr(s.id)}">${escapeHtml(s.name)} ${escapeHtml(s.surname)}</option>`).join('');

    let periodSelect = '';
    if(isAdmin && data.periods && data.periods.length > 0) {
        const currentPeriodId = reportPeriodId || (data.activePeriod ? data.activePeriod.id : null);
        periodSelect = `<div class="form-group"><label>Kurs Dönemi</label><select id="rPeriod" onchange="changeReportPeriod(this.value)">${data.periods.map(p=>`<option value="${escapeAttr(p.id)}" ${currentPeriodId == p.id ? 'selected' : ''}>${escapeHtml(p.name)}</option>`).join('')}</select></div>`;
    }

    let evaluationButton = '';
    if(isTeacher || isAdmin) {
        evaluationButton = `<div style="margin-top:10px"><button class="btn btn-info" onclick="openEvaluationModal()">📝 Değerlendirme Girişi</button></div>`;
    }
    let html=`<div class="card"><h2>📊 Raporlar</h2>
    <div class="stats"><div class="stat-card"><h3>${source.courses.length}</h3><p>Toplam Kurs</p></div>
    <div class="stat-card"><h3>${data.teachers.length}</h3><p>Öğretmen</p></div>
    <div class="stat-card"><h3>${source.students.length}</h3><p>Öğrenci</p></div>
    <div class="stat-card"><h3>${source.attendance.filter(a=>a.status===ATT_STATUS_ABSENT).length}</h3><p>Devamsızlık</p></div>
    <div class="stat-card"><h3>${source.attendance.filter(a=>a.status===ATT_STATUS_EXCUSED).length}</h3><p>Mazeretli</p></div></div>

    <div class="filter-row">
    ${periodSelect}
    <div class="form-group"><label>Kurs</label><select id="rCourse">${courseOptions}</select></div>
    <div class="form-group"><label>Öğretmen</label>${teacherSelect}</div>
    <div class="form-group"><label>Öğrenci</label><select id="rStudent">${studentOptions}</select></div>
    <div class="form-group"><label>Durum</label><select id="rStatus"><option value="">Tümü</option><option value="${ATT_STATUS_PRESENT}">Geldi</option><option value="${ATT_STATUS_ABSENT}">Gelmedi</option><option value="${ATT_STATUS_EXCUSED}">Mazeretli</option></select></div>
    <div class="form-group"><label>Başlangıç</label><input type="date" id="rStart"></div>
    <div class="form-group"><label>Bitiş</label><input type="date" id="rEnd"></div>
    <div class="form-group" style="align-self: flex-end;"><button class="btn btn-primary" style="width:100%" onclick="generateReport()">Rapor Oluştur</button></div></div>
    ${evaluationButton}
    <div id="reportResult"></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
}
async function changeReportPeriod(periodId){
    reportPeriodId = periodId ? parseInt(periodId) : null;
    await loadReportPeriod(reportPeriodId);
}
async function loadReportPeriod(periodId){
    if(!periodId || currentUser.role !== 'admin') {
        reportData = null;
        showReports();
        return;
    }
    const res = await apiCall('get_report_data', {period_id: periodId});
    if(res && res.status === 'success') {
        const normalizedAttendance = (res.attendance || []).map(a => ({...a, status: Number(a.status)}));
        reportData = {courses: res.courses || [], students: res.students || [], attendance: normalizedAttendance};
    } else {
        reportData = null;
        alert(res ? res.message : 'Rapor verisi alınamadı');
    }
    showReports();
}
function generateReport(){
    const source = reportData || data;
    const cid=document.getElementById('rCourse').value;
    const sid=document.getElementById('rStudent').value, status=document.getElementById('rStatus').value,
    start=document.getElementById('rStart').value, end=document.getElementById('rEnd').value;
    
    let tid = "";
    if(currentUser.role === 'teacher') {
        tid = currentUser.id;
    } else {
        tid = document.getElementById('rTeacher').value;
    }

    let filtered=source.attendance;
    if(cid) filtered = filtered.filter(a => a.courseId == cid);
    if(sid) filtered = filtered.filter(a => a.studentId == sid);
    if(status) filtered = filtered.filter(a => a.status === Number(status));
    if(start) filtered = filtered.filter(a => a.date >= start);
    if(end) filtered = filtered.filter(a => a.date <= end);
    
    if(tid) { 
        const teacherCourseIds = source.courses.filter(c => c.teacherId == tid).map(c => c.id); 
        filtered = filtered.filter(a => teacherCourseIds.includes(parseInt(a.courseId))); 
    }

    const absent=filtered.filter(a=>a.status===ATT_STATUS_ABSENT), excused=filtered.filter(a=>a.status===ATT_STATUS_EXCUSED);
    let html=`<h3 style="margin:20px 0">Rapor Sonuçları</h3>
    <p>Toplam Kayıt: ${filtered.length} | Devamsızlık: ${absent.length} | Mazeretli: ${excused.length}</p>
    <div class="table-responsive"><table id="reportTable"><tr><th>Öğrenci</th><th>Kurs</th><th>Öğretmen</th><th>Tarih</th><th>Durum</th></tr>`;
    filtered.forEach(a=>{
        const s=source.students.find(x=>x.id===a.studentId), c=source.courses.find(x=>x.id==a.courseId), t=c?data.teachers.find(tr=>tr.id==c.teacherId):null;
        let statusText='?';
        if(a.status===ATT_STATUS_PRESENT) statusText='<span style="color:green">✓ Geldi</span>';
        else if(a.status===ATT_STATUS_ABSENT) statusText='<span style="color:red">✗ Gelmedi</span>';
        else if(a.status===ATT_STATUS_EXCUSED) statusText='<span style="color:#17a2b8">M Mazeretli</span>';
        html+=`<tr><td>${escapeHtml(s?.name)} ${escapeHtml(s?.surname)}</td><td>${escapeHtml(c?.name||'-')}</td><td>${escapeHtml(t?.name||'-')}</td><td>${escapeHtml(a.date)}</td><td>${statusText}</td></tr>`;
    });
    html+=`</table></div>
    <div class="export-buttons">
    <button class="btn btn-success" onclick="downloadExcel()">📊 Excel</button>
    <button class="btn btn-primary" onclick="downloadWord()">📄 Word</button>
    <button class="btn btn-info" onclick="downloadHTML()">🌐 HTML</button>
    <button class="btn btn-secondary" onclick="printReport()">🖨️ Yazdır</button>
    </div>`;
    document.getElementById('reportResult').innerHTML=html;
}

function openEvaluationModal(){
    const isTeacher = currentUser.role === 'teacher';
    let availableCourses = data.courses || [];
    if(isTeacher) {
        availableCourses = availableCourses.filter(c => c.teacherId == currentUser.id);
    }
    if(availableCourses.length === 0) {
        return alert('Değerlendirme yapılacak kurs bulunamadı.');
    }
    const currentMonth = new Date().toISOString().slice(0,7);
    let html=`<div class="modal-header"><h2>📝 Öğretmen Değerlendirmesi</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Kurs</label><select id="evalCourse" onchange="loadEvaluationEntries()">${availableCourses.map(c=>`<option value="${escapeAttr(c.id)}">${escapeHtml(c.name)}</option>`).join('')}</select></div>
    <div class="form-group"><label>Değerlendirme Dönemi</label><input type="month" id="evalPeriod" value="${escapeAttr(currentMonth)}" onchange="loadEvaluationEntries()"></div>
    <div id="evaluationMessage" style="margin:10px 0;color:#0062cc;"></div>
    <div id="evaluationList"></div>`;
    showModal(html);
    loadEvaluationEntries();
}

async function loadEvaluationEntries(){
    const courseId = document.getElementById('evalCourse')?.value;
    const evaluationPeriod = document.getElementById('evalPeriod')?.value;
    if(!courseId || !evaluationPeriod) {
        document.getElementById('evaluationList').innerHTML = '<p>Değerlendirme dönemi seçin.</p>';
        return;
    }
    const courseIdNum = Number(courseId);
    const students = (data.students || []).filter(s => Array.isArray(s.courses) && s.courses.some(cid => Number(cid) === courseIdNum));
    if(students.length === 0) {
        document.getElementById('evaluationList').innerHTML = '<p>Bu kurs için kayıtlı öğrenci bulunamadı.</p>';
        return;
    }
    const res = await apiCall('get_evaluations', {courseId, evaluationPeriod});
    const evaluationMap = {};
    if(res && res.status === 'success') {
        (res.evaluations || []).forEach(e => {
            evaluationMap[e.student_id] = e.score;
        });
    }
    let html = `<div class="table-responsive"><table><tr><th>Öğrenci</th><th>Puan</th><th>İşlem</th></tr>`;
    students.forEach(s => {
        const existingScore = evaluationMap[s.id] ?? '';
        html += `<tr>
            <td>${escapeHtml(s.name)} ${escapeHtml(s.surname)}</td>
            <td><input type="number" min="0" max="100" step="0.01" id="evalScore_${escapeAttr(s.id)}" value="${escapeAttr(existingScore)}" style="width:120px"></td>
            <td><button class="btn btn-primary btn-sm" onclick="saveEvaluation(${s.id})">Kaydet</button></td>
        </tr>`;
    });
    html += `</table></div>`;
    document.getElementById('evaluationList').innerHTML = html;
}

async function saveEvaluation(studentId){
    const courseId = document.getElementById('evalCourse')?.value;
    const evaluationPeriod = document.getElementById('evalPeriod')?.value;
    const scoreInput = document.getElementById(`evalScore_${studentId}`);
    const score = scoreInput ? scoreInput.value : '';
    if(!courseId || !evaluationPeriod) {
        return alert('Kurs ve dönem seçin.');
    }
    const res = await apiCall('save_evaluation', {courseId, studentId, evaluationPeriod, score});
    const message = document.getElementById('evaluationMessage');
    if(res && res.status === 'success') {
        if(message) message.textContent = 'Değerlendirme kaydedildi.';
    } else {
        if(message) message.textContent = res ? res.message : 'Kayıt başarısız.';
    }
}

// --- YÖNETİM ---
function showAdmin(){
    setActiveNav(5);
    let html=`<div class="card"><h2>⚙️ Yönetim Paneli</h2>
    <div class="tabs"><button class="tab active" onclick="showAdminTab(0)">Kullanıcılar</button>
    <button class="tab" onclick="showAdminTab(1)">Tesisler/Sınıflar</button>
    <button class="tab" onclick="showAdminTab(2)">Ek Tatiller</button>
    <button class="tab" onclick="showAdminTab(3)">Duyurular</button>
    <button class="tab" onclick="showAdminTab(4)">Kurs Dönemleri</button>
    <button class="tab" onclick="showAdminTab(5)">Uygulama Sıfırlama</button>
    <button class="tab" onclick="showAdminTab(6)">Genel</button></div>
    <div id="adminContent"></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
    showAdminTab(0);
}
function showAdminTab(idx){
    document.querySelectorAll('.tab').forEach((t,i)=>t.classList.toggle('active',i===idx));
    let html='';
    if(idx===0){
        html=`<h3>Yönetici Kullanıcılar</h3><div class="table-responsive"><button class="btn btn-primary" onclick="addUser()">+ Yeni Yönetici</button>
        <table style="margin-top:15px"><tr><th>Ad</th><th>Kullanıcı Adı</th><th>Rol</th><th>İşlem</th></tr>`;
        data.users.forEach(u=>{html+=`<tr><td>${escapeHtml(u.name)}</td><td>${escapeHtml(u.username)}</td><td>${escapeHtml(u.role)}</td><td>${u.id!==1?`<button class="btn btn-danger" onclick="deleteUser(${u.id})">🗑️</button>`:''}</td></tr>`;});
        html+=`</table></div>`;
    }else if(idx===1){
        html=`<h3>Tesisler</h3><div class="form-group"><input type="text" id="newBuilding" placeholder="Yeni tesis adı"><button class="btn btn-primary" onclick="addBuilding()">Ekle</button></div>
        <ul>${data.buildings.map((b,i)=>`<li>${escapeHtml(b)} <button class="btn btn-danger btn-sm" onclick="removeBuilding(${i})">×</button></li>`).join('')}</ul>
        <h3 style="margin-top:20px">Sınıflar/Atölyeler</h3><div class="form-group"><input type="text" id="newClass" placeholder="Yeni sınıf adı"><button class="btn btn-primary" onclick="addClass()">Ekle</button></div>
        <ul>${data.classes.map((c,i)=>`<li>${escapeHtml(c)} <button class="btn btn-danger btn-sm" onclick="removeClass(${i})">×</button></li>`).join('')}</ul>`;
    }else if(idx===2){
        html=`<h3>Resmi Tatil Yönetimi</h3><div class="form-group"><input type="date" id="newHolDate"><input type="text" id="newHolName" placeholder="Tatil adı"><button class="btn btn-primary" onclick="addHoliday()">Ekle</button></div>
        <div class="table-responsive"><table><tr><th>Tarih</th><th>Ad</th><th>İşlem</th></tr>`;
        const sortedHolidays = [...data.holidays].sort((a,b)=>a.date.localeCompare(b.date));
        sortedHolidays.forEach((h)=>{html+=`<tr><td>${escapeHtml(h.date)}</td><td>${escapeHtml(h.name)}</td><td><button class="btn btn-danger btn-sm" onclick="removeHoliday('${escapeAttr(h.date)}')">×</button></td></tr>`;});
        html+=`</table></div>`;
    }else if(idx===3){
        const announcements = data.announcementsAll || [];
        html=`<h3>Duyurular</h3><button class="btn btn-primary" onclick="openAnnouncementModal()">+ Yeni Duyuru</button>
        <div class="table-responsive" style="margin-top:15px"><table><tr><th>Başlık</th><th>Tarih Aralığı</th><th>Durum</th><th>İşlem</th></tr>`;
        if(announcements.length === 0) {
            html+=`<tr><td colspan="4">Duyuru bulunamadı.</td></tr>`;
        } else {
            announcements.forEach(a=>{
                const rangeText = a.start_date || a.end_date ? `${escapeHtml(a.start_date || 'Başlangıç yok')} - ${escapeHtml(a.end_date || 'Süresiz')}` : 'Süresiz';
                const statusText = a.is_active ? 'Aktif' : 'Pasif';
                html+=`<tr><td>${escapeHtml(a.title)}</td><td>${rangeText}</td><td>${statusText}</td>
                <td>
                    <button class="btn btn-info btn-sm" onclick="openAnnouncementModal(${a.id})">Düzenle</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteAnnouncement(${a.id})">Sil</button>
                </td></tr>`;
            });
        }
        html+=`</table></div>`;
    }else if(idx===4){
        const periods = data.periods || [];
        html=`<h3>Kurs Dönemleri</h3>
        <div class="form-group">
            <input type="text" id="newPeriodName" placeholder="Dönem adı">
            <input type="date" id="newPeriodStart">
            <input type="date" id="newPeriodEnd">
            <button class="btn btn-primary" onclick="addPeriod()">Ekle</button>
        </div>
        <div class="table-responsive"><table><tr><th>Dönem</th><th>Tarih</th><th>Durum</th><th>İşlem</th></tr>`;
        if(periods.length === 0) {
            html+=`<tr><td colspan="4">Dönem bulunamadı.</td></tr>`;
        } else {
            periods.forEach(p=>{
                const rangeText = p.start_date || p.end_date ? `${escapeHtml(p.start_date || 'Başlangıç yok')} - ${escapeHtml(p.end_date || 'Bitiş yok')}` : 'Tarih yok';
                const statusText = p.is_active ? 'Aktif' : 'Pasif';
                const actionButtons = `
                    <button class="btn btn-info btn-sm" onclick="openPeriodModal(${p.id})">Düzenle</button>
                    ${p.is_active ? '' : `<button class="btn btn-danger btn-sm" onclick="deletePeriod(${p.id})">Sil</button>`}
                    ${p.is_active ? '' : `<button class="btn btn-success btn-sm" onclick="activatePeriod(${p.id})">Aktif Yap</button>`}
                `;
                html+=`<tr><td>${escapeHtml(p.name)}</td><td>${rangeText}</td><td>${statusText}</td>
                <td>${actionButtons}</td></tr>`;
            });
        }
        html+=`</table></div>`;
    }else if(idx===5){
        html=`<h3>Uygulama Sıfırlama</h3>
        <p style="color:#b00;font-weight:bold;">Bu işlem geri alınamaz!</p>
        <div class="filter-row" style="background:none;border:none;padding:0;">
            <div class="form-group"><button class="btn btn-warning" onclick="confirmReset('student_registrations')">Sadece Öğrenci Kayıtlarını Sıfırla</button></div>
            <div class="form-group"><button class="btn btn-warning" onclick="confirmReset('courses')">Sadece Kursları Sıfırla</button></div>
            <div class="form-group"><button class="btn btn-warning" onclick="confirmReset('attendance')">Sadece Yoklamayı Sıfırla</button></div>
            <div class="form-group"><button class="btn btn-danger" onclick="confirmReset('all')">Her Şeyi Sıfırla</button></div>
        </div>`;
    }else{
        const certSettings = data.certificateSettings || {};
        html=`<h3>Genel Ayarlar</h3><div class="form-group"><label>Kurum Adı</label><input type="text" id="settingTitle" value="${escapeAttr(data.settings.title)}"></div><button class="btn btn-primary" onclick="saveSettings()">Kaydet</button>
        <hr style="margin:20px 0"><h3>Sertifika Ayarları</h3>
        <div class="form-group"><label>Kurum Adı (Sertifika)</label><input type="text" id="certInstitution" value="${escapeAttr(certSettings.institution_name || data.settings.title || '')}"></div>
        <div class="form-group"><label>Logo URL</label><input type="text" id="certLogo" value="${escapeAttr(certSettings.logo_url || '')}" placeholder="https://..."></div>
        <div class="form-group"><label>Sertifika Metni</label><textarea id="certText" rows="3">${escapeHtml(certSettings.certificate_text || '')}</textarea></div>
        <div class="form-group"><label>Minimum Devam Yüzdesi</label><input type="number" id="certMinAttendance" min="0" max="100" step="0.01" value="${escapeAttr(certSettings.min_attendance ?? 70)}"></div>
        <div class="form-group"><label>Minimum Değerlendirme Puanı</label><input type="number" id="certMinScore" min="0" max="100" step="0.01" value="${escapeAttr(certSettings.min_score ?? 70)}"></div>
        <div class="form-group"><label>İmza 1 - Ad Soyad</label><input type="text" id="certSigName1" value="${escapeAttr(certSettings.signature_primary_name || '')}"></div>
        <div class="form-group"><label>İmza 1 - Ünvan</label><input type="text" id="certSigTitle1" value="${escapeAttr(certSettings.signature_primary_title || '')}"></div>
        <div class="form-group"><label>İmza 2 - Ad Soyad</label><input type="text" id="certSigName2" value="${escapeAttr(certSettings.signature_secondary_name || '')}"></div>
        <div class="form-group"><label>İmza 2 - Ünvan</label><input type="text" id="certSigTitle2" value="${escapeAttr(certSettings.signature_secondary_title || '')}"></div>
        <button class="btn btn-primary" onclick="saveCertificateSettings()">Sertifika Ayarlarını Kaydet</button>
        <hr style="margin:20px 0"><h3>Sertifika Üretimi</h3>
        <div class="form-group"><label>Kurs</label><select id="certCourse" onchange="loadCertificateStudents()"><option value="">Kurs seçin</option>${data.courses.map(c=>`<option value="${escapeAttr(c.id)}">${escapeHtml(c.name)}</option>`).join('')}</select></div>
        <div class="form-group"><label>Öğrenci (opsiyonel)</label><select id="certStudent"><option value="">Tümü</option></select></div>
        <div class="form-group" style="display:flex;align-items:center;gap:10px;">
            <input type="checkbox" id="certForce" style="width:auto;margin:0;">
            <label for="certForce" style="margin:0;font-weight:normal;">Mevcut sertifikaları yeniden üret</label>
        </div>
        <button class="btn btn-success" onclick="generateCertificates()">Sertifika Oluştur</button>
        <button class="btn btn-info" style="margin-left:10px" onclick="loadCertificatesList()">Sertifikaları Listele</button>
        <div id="certificateResult" style="margin-top:15px;"></div>
        <hr style="margin:20px 0"><h3>Veri Yönetimi</h3><button class="btn btn-info" onclick="downloadDatabaseBackup()">💾 Veritabanı Yedeği Al</button>`;
    }
    document.getElementById('adminContent').innerHTML=html;
}
function addUser(){let html=`<div class="modal-header"><h2>➕ Yeni Yönetici</h2><span class="modal-close" onclick="closeModal()">×</span></div><div class="form-group"><label>Ad Soyad</label><input type="text" id="uName"></div><div class="form-group"><label>Kullanıcı Adı</label><input type="text" id="uUser"></div><div class="form-group"><label>Şifre</label><input type="password" id="uPass"></div><button class="btn btn-primary" onclick="saveUser()">Kaydet</button>`;showModal(html);}
async function saveUser(){
    await apiCall('save_user', {name:document.getElementById('uName').value,username:document.getElementById('uUser').value,password:document.getElementById('uPass').value});
    await refreshData(); closeModal(); showAdmin();
}
async function deleteUser(id){if(confirm('Silmek istiyor musunuz?')){await apiCall('delete_user', {id}); await refreshData(); showAdmin();}}
async function addBuilding(){const v=document.getElementById('newBuilding').value;if(v){const b=[...data.buildings,v];await apiCall('save_meta',{key:'buildings',value:b});await refreshData();showAdminTab(1)}}
async function removeBuilding(i){const b=[...data.buildings];b.splice(i,1);await apiCall('save_meta',{key:'buildings',value:b});await refreshData();showAdminTab(1)}
async function addClass(){const v=document.getElementById('newClass').value;if(v){const c=[...data.classes,v];await apiCall('save_meta',{key:'classes',value:c});await refreshData();showAdminTab(1)}}
async function removeClass(i){const c=[...data.classes];c.splice(i,1);await apiCall('save_meta',{key:'classes',value:c});await refreshData();showAdminTab(1)}
async function addHoliday(){await apiCall('add_holiday',{date:document.getElementById('newHolDate').value,name:document.getElementById('newHolName').value});await refreshData();showAdminTab(2)}
async function removeHoliday(d){if(confirm('Silmek istiyor musunuz?')){await apiCall('delete_holiday',{date:d});await refreshData();showAdminTab(2)}}
async function addPeriod(){
    const name = document.getElementById('newPeriodName').value;
    const startDate = document.getElementById('newPeriodStart').value;
    const endDate = document.getElementById('newPeriodEnd').value;
    if(!name) return alert('Dönem adı zorunludur.');
    const res = await apiCall('create_period', {name, start_date: startDate, end_date: endDate});
    if(res && res.status === 'success') {
        await refreshData({skipRender: true});
        showAdminTab(4);
    }
}
function openPeriodModal(id){
    const period = (data.periods || []).find(p => p.id == id);
    if(!period) return alert('Dönem bulunamadı.');
    let html=`<div class="modal-header"><h2>✏️ Dönem Düzenle</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Dönem adı</label><input type="text" id="editPeriodName" value="${escapeAttr(period.name || '')}"></div>
    <div class="form-group"><label>Başlangıç Tarihi</label><input type="date" id="editPeriodStart" value="${escapeAttr(period.start_date || '')}"></div>
    <div class="form-group"><label>Bitiş Tarihi</label><input type="date" id="editPeriodEnd" value="${escapeAttr(period.end_date || '')}"></div>
    <button class="btn btn-primary" onclick="savePeriod(${period.id})">Kaydet</button>`;
    showModal(html);
}
async function savePeriod(id){
    const name = document.getElementById('editPeriodName').value;
    const startDate = document.getElementById('editPeriodStart').value;
    const endDate = document.getElementById('editPeriodEnd').value;
    if(!name) return alert('Dönem adı zorunludur.');
    const res = await apiCall('update_period', {id, name, start_date: startDate, end_date: endDate});
    if(res && res.status === 'success') {
        await refreshData({skipRender: true});
        closeModal();
        showAdminTab(4);
    }
}
async function deletePeriod(id){
    const warning = "Bu dönem silindiğinde, bu döneme ait tüm kurslar,\nöğrenci kayıtları ve yoklama verileri de silinecektir.\nBu işlem geri alınamaz!";
    if(!confirm(warning)) return;
    const confirmText = prompt('Silme işlemini onaylamak için "SIL" yazın:');
    if(confirmText !== 'SIL') return;
    const res = await apiCall('delete_period', {id});
    if(res && res.status === 'success') {
        await refreshData({skipRender: true});
        showAdminTab(4);
    }
}
async function activatePeriod(id){
    if(!confirm('Aktif dönemi değiştirmek istiyor musunuz?')) return;
    const res = await apiCall('activate_period', {id});
    if(res && res.status === 'success') {
        await refreshData();
        showAdminTab(4);
    }
}
async function confirmReset(type){
    const warning = 'Bu işlem geri alınamaz!';
    const confirmText = prompt(`${warning}\nOnaylamak için "ONAYLA" yazın:`);
    if(confirmText !== 'ONAYLA') return;
    const res = await apiCall('reset_data', {type});
    if(res && res.status === 'success') {
        await refreshData();
        showAdminTab(5);
    }
}
function openAnnouncementModal(id=null){
    const announcement = (data.announcementsAll || []).find(a => a.id == id) || {title:'', message:'', start_date:'', end_date:'', is_active:1};
    let html=`<div class="modal-header"><h2>📣 ${id ? 'Duyuru Düzenle' : 'Yeni Duyuru'}</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Başlık</label><input type="text" id="aTitle" value="${escapeAttr(announcement.title || '')}"></div>
    <div class="form-group"><label>Mesaj</label><textarea id="aMessage" rows="4">${escapeHtml(announcement.message || '')}</textarea></div>
    <div class="form-group"><label>Başlangıç Tarihi</label><input type="date" id="aStart" value="${escapeAttr(announcement.start_date || '')}"></div>
    <div class="form-group"><label>Bitiş Tarihi</label><input type="date" id="aEnd" value="${escapeAttr(announcement.end_date || '')}" placeholder="Süresiz"></div>
    <div class="form-group" style="display:flex;align-items:center;gap:10px;">
        <input type="checkbox" id="aActive" ${announcement.is_active ? 'checked' : ''} style="width:auto;margin:0;">
        <label for="aActive" style="margin:0;font-weight:normal;">Aktif</label>
    </div>
    <button class="btn btn-primary" onclick="saveAnnouncement(${id ? announcement.id : 'null'})">Kaydet</button>`;
    showModal(html);
}
async function saveAnnouncement(id=null){
    const payload = {
        id,
        title: document.getElementById('aTitle').value,
        message: document.getElementById('aMessage').value,
        start_date: document.getElementById('aStart').value,
        end_date: document.getElementById('aEnd').value,
        is_active: document.getElementById('aActive').checked ? 1 : 0
    };
    const res = await apiCall('save_announcement', payload);
    if(res && res.status === 'success') {
        await refreshData();
        closeModal();
        showAdminTab(3);
    }
}
async function deleteAnnouncement(id){
    if(confirm('Silmek istiyor musunuz?')) {
        await apiCall('delete_announcement', {id});
        await refreshData();
        showAdminTab(3);
    }
}
async function saveSettings(){await apiCall('save_meta',{key:'title',value:document.getElementById('settingTitle').value});alert('Kaydedildi!');}
async function saveCertificateSettings(){
    const payload = {
        institution_name: document.getElementById('certInstitution').value,
        logo_url: document.getElementById('certLogo').value,
        certificate_text: document.getElementById('certText').value,
        min_attendance: document.getElementById('certMinAttendance').value,
        min_score: document.getElementById('certMinScore').value,
        signature_primary_name: document.getElementById('certSigName1').value,
        signature_primary_title: document.getElementById('certSigTitle1').value,
        signature_secondary_name: document.getElementById('certSigName2').value,
        signature_secondary_title: document.getElementById('certSigTitle2').value
    };
    const res = await apiCall('save_certificate_settings', payload);
    if(res && res.status === 'success') {
        await refreshData({skipRender: true});
        showAdminTab(6);
        alert('Sertifika ayarları kaydedildi!');
    } else {
        alert(res ? res.message : 'Kayıt başarısız');
    }
}
function loadCertificateStudents(){
    const courseId = document.getElementById('certCourse')?.value;
    const studentSelect = document.getElementById('certStudent');
    if(!studentSelect) return;
    if(!courseId) {
        studentSelect.innerHTML = '<option value="">Tümü</option>';
        return;
    }
    const courseIdNum = Number(courseId);
    const students = (data.students || []).filter(s => Array.isArray(s.courses) && s.courses.some(cid => Number(cid) === courseIdNum));
    let options = '<option value="">Tümü</option>';
    students.forEach(s => {
        options += `<option value="${escapeAttr(s.id)}">${escapeHtml(s.name)} ${escapeHtml(s.surname)}</option>`;
    });
    studentSelect.innerHTML = options;
}
async function generateCertificates(){
    const courseId = document.getElementById('certCourse')?.value;
    const studentId = document.getElementById('certStudent')?.value;
    const force = document.getElementById('certForce')?.checked;
    if(!courseId) return alert('Kurs seçin.');
    const res = await apiCall('generate_certificates', {courseId, studentId: studentId || null, force: force ? 1 : 0});
    const resultBox = document.getElementById('certificateResult');
    if(res && res.status === 'success') {
        resultBox.innerHTML = `<p>Oluşturulan: <strong>${res.generated}</strong> | Mevcut: <strong>${res.skipped_existing}</strong> | Uygun değil: <strong>${res.skipped_ineligible}</strong></p>`;
        await loadCertificatesList();
    } else {
        resultBox.innerHTML = `<p>${res ? res.message : 'İşlem başarısız'}</p>`;
    }
}
async function loadCertificatesList(){
    const courseId = document.getElementById('certCourse')?.value;
    if(!courseId) return;
    const res = await apiCall('get_certificates', {courseId});
    const resultBox = document.getElementById('certificateResult');
    if(res && res.status === 'success') {
        const certs = res.certificates || [];
        if(certs.length === 0) {
            resultBox.innerHTML = '<p>Sertifika bulunamadı.</p>';
            return;
        }
        let html = `<div class="table-responsive"><table><tr><th>Öğrenci</th><th>Tarih</th><th>İşlem</th></tr>`;
        certs.forEach(c => {
            const studentName = `${escapeHtml(c.name)} ${escapeHtml(c.surname)}`;
            const dateText = escapeHtml(c.completion_date || c.issued_at || '');
            const link = `?action=download_certificate&id=${encodeURIComponent(c.id)}&token=${encodeURIComponent(CSRF_TOKEN)}`;
            html += `<tr><td>${studentName}</td><td>${dateText}</td><td><a class="btn btn-secondary btn-sm" href="${link}" target="_blank">PDF / Yazdır</a></td></tr>`;
        });
        html += `</table></div>`;
        resultBox.innerHTML = html;
    } else {
        resultBox.innerHTML = `<p>${res ? res.message : 'Liste alınamadı'}</p>`;
    }
}
function downloadDatabaseBackup(){window.location.href='?action=download_backup&token='+encodeURIComponent(CSRF_TOKEN);}

// MODAL & EXPORT
function showModal(html){document.getElementById('modalContent').innerHTML=html;document.getElementById('modal').style.display='block'}
function closeModal(){document.getElementById('modal').style.display='none'}
document.getElementById('modal').onclick=function(e){if(e.target===this)closeModal()};

function downloadExcel() {
    const table = document.getElementById('reportTable'); if(!table) return alert("Rapor yok!");
    const html = `\uFEFF<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>${table.outerHTML}</body></html>`;
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'Rapor.xls'; a.click();
}
function downloadWord() {
    const table = document.getElementById('reportTable'); if(!table) return alert("Rapor yok!");
    const html = `\uFEFF<html><head><meta charset='utf-8'></head><body>${table.outerHTML}</body></html>`;
    const blob = new Blob([html], { type: 'application/msword' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'Rapor.doc'; a.click();
}
function downloadHTML() {
    const table = document.getElementById('reportTable'); if(!table) return alert("Rapor yok!");
    const html = `<html><head><meta charset="UTF-8"><style>table{width:100%;border-collapse:collapse;}th,td{border:1px solid black;padding:8px;}th{background:#f2f2f2}</style></head><body><h2>Rapor</h2>${table.outerHTML}</body></html>`;
    const blob = new Blob([html], { type: 'text/html' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'Rapor.html'; a.click();
}
function printReport(){window.print()}
</script>
</body>
</html>
