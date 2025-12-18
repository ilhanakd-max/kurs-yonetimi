<?php
session_start();

// Basic configuration
$DB_HOST = getenv('MYSQL_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('MYSQL_DATABASE') ?: 'kurs_yonetimi';
$DB_USER = getenv('MYSQL_USER') ?: 'root';
$DB_PASS = getenv('MYSQL_PASSWORD') ?: '';
$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";

// Establish database connection and bootstrap schema
function db() {
    static $pdo = null;
    global $dsn, $DB_USER, $DB_PASS;
    if ($pdo === null) {
        try {
            $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            echo '<h2>Veritabanına bağlanırken hata oluştu: ' . htmlspecialchars($e->getMessage()) . '</h2>';
            exit;
        }
    }
    return $pdo;
}

function bootstrapSchema() {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(160) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS buildings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        address VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        building_id INT NULL,
        name VARCHAR(120) NOT NULL,
        capacity INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(building_id) REFERENCES buildings(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        teacher_id INT NOT NULL,
        room_id INT NULL,
        day_of_week TINYINT NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(room_id) REFERENCES rooms(id) ON DELETE SET NULL,
        FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        session_date DATE NOT NULL,
        room_id INT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
        note VARCHAR(255) DEFAULT NULL,
        UNIQUE KEY uniq_session(course_id, session_date),
        FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY(room_id) REFERENCES rooms(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(200) NOT NULL,
        phone VARCHAR(50),
        email VARCHAR(160),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        student_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_enroll(course_id, student_id),
        FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        student_id INT NOT NULL,
        status ENUM('present','absent') NOT NULL,
        note VARCHAR(255) DEFAULT NULL,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_att(session_id, student_id),
        FOREIGN KEY(session_id) REFERENCES course_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        holiday_date DATE NOT NULL UNIQUE,
        name VARCHAR(200) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Create default admin if missing
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM users");
    $count = (int)$stmt->fetch()['c'];
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users(name,email,password_hash,role) VALUES('Sistem Yöneticisi','admin@example.com',?,'admin')")
            ->execute([$hash]);
    }
}

bootstrapSchema();

function currentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = db()->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function requireLogin() {
    if (!currentUser()) {
        header('Location: ?logout=1');
        exit;
    }
}

function handleLogin() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'login') return;
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php');
        exit;
    }
    $GLOBALS['login_error'] = 'E-posta veya şifre hatalı';
}

function handleLogout() {
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

handleLogin();
handleLogout();

$user = currentUser();

function ensureRole($role) {
    global $user;
    if (!$user || $user['role'] !== $role) {
        echo '<p class="alert">Bu işlemi yapma yetkiniz yok.</p>';
        return false;
    }
    return true;
}

function addBuilding() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'add_building') return;
    if (!ensureRole('admin')) return;
    $stmt = db()->prepare("INSERT INTO buildings(name,address) VALUES(?,?)");
    $stmt->execute([trim($_POST['name']), trim($_POST['address'])]);
}

function addRoom() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'add_room') return;
    if (!ensureRole('admin')) return;
    $stmt = db()->prepare("INSERT INTO rooms(building_id,name,capacity) VALUES(?,?,?)");
    $stmt->execute([
        $_POST['building_id'] ?: null,
        trim($_POST['name']),
        (int)($_POST['capacity'] ?: 0)
    ]);
}

function addUser() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'add_user') return;
    if (!ensureRole('admin')) return;
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = db()->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)");
    $stmt->execute([trim($_POST['name']), trim($_POST['email']), $hash, $_POST['role']]);
}

function addStudent() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'add_student') return;
    if (!($GLOBALS['user'])) return;
    $stmt = db()->prepare("INSERT INTO students(full_name,phone,email) VALUES(?,?,?)");
    $stmt->execute([trim($_POST['full_name']), trim($_POST['phone']), trim($_POST['email'])]);
}

function addEnrollment() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'add_enrollment') return;
    if (!($GLOBALS['user'])) return;
    $stmt = db()->prepare("INSERT IGNORE INTO enrollments(course_id,student_id) VALUES(?,?)");
    $stmt->execute([(int)$_POST['course_id'], (int)$_POST['student_id']]);
}

function detectConflicts($roomId, $teacherId, $sessionDate, $start, $end, $ignoreCourseId = null) {
    $pdo = db();
    $query = "SELECT cs.session_date, cs.start_time, cs.end_time, cs.room_id, c.id AS course_id, c.title, u.name AS teacher
              FROM course_sessions cs
              JOIN courses c ON cs.course_id=c.id
              LEFT JOIN users u ON c.teacher_id=u.id
              WHERE cs.session_date=? AND cs.status='scheduled'";
    $params = [$sessionDate];
    if ($ignoreCourseId) {
        $query .= " AND c.id<>?";
        $params[] = $ignoreCourseId;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $conflicts = [];
    foreach ($stmt->fetchAll() as $row) {
        $overlap = !($end <= $row['start_time'] || $start >= $row['end_time']);
        if ($overlap && $roomId && (int)$roomId === (int)$row['room_id']) {
            $conflicts[] = 'Sınıf çakışması: ' . $row['title'];
        }
    }
    // Teacher conflicts
    $stmt = $pdo->prepare("SELECT cs.start_time, cs.end_time, c.title
        FROM course_sessions cs JOIN courses c ON cs.course_id=c.id
        WHERE cs.session_date=? AND c.teacher_id=? AND cs.status='scheduled'" . ($ignoreCourseId ? " AND c.id<>?" : ""));
    $params = [$sessionDate, $teacherId];
    if ($ignoreCourseId) $params[] = $ignoreCourseId;
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $overlap = !($end <= $row['start_time'] || $start >= $row['end_time']);
        if ($overlap) $conflicts[] = 'Öğretmen çakışması: ' . $row['title'];
    }
    return $conflicts;
}

function generateSessions($courseId, $dayOfWeek, $startDate, $endDate, $startTime, $endTime, $roomId, $teacherId) {
    $pdo = db();
    $periodStart = new DateTime($startDate);
    $periodEnd = new DateTime($endDate);
    $interval = new DateInterval('P1D');
    for ($dt = clone $periodStart; $dt <= $periodEnd; $dt->add($interval)) {
        if ((int)$dt->format('N') !== (int)$dayOfWeek) continue;
        $sessionDate = $dt->format('Y-m-d');
        $conflicts = detectConflicts($roomId, $teacherId, $sessionDate, $startTime, $endTime, $courseId);
        if ($conflicts) {
            return $conflicts;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO course_sessions(course_id,session_date,room_id,start_time,end_time) VALUES(?,?,?,?,?)");
        $stmt->execute([$courseId, $sessionDate, $roomId ?: null, $startTime, $endTime]);
    }
    return [];
}

function addCourse() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'add_course') return;
    if (!ensureRole('admin')) return;
    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO courses(title,description,teacher_id,room_id,day_of_week,start_time,end_time,start_date,end_date,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        trim($_POST['title']),
        trim($_POST['description']),
        (int)$_POST['teacher_id'],
        $_POST['room_id'] ?: null,
        (int)$_POST['day_of_week'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['start_date'],
        $_POST['end_date'],
        $GLOBALS['user']['id']
    ]);
    $courseId = $pdo->lastInsertId();
    $conflicts = generateSessions($courseId, $_POST['day_of_week'], $_POST['start_date'], $_POST['end_date'], $_POST['start_time'], $_POST['end_time'], $_POST['room_id'], (int)$_POST['teacher_id']);
    if ($conflicts) {
        $pdo->rollBack();
        $GLOBALS['error'] = implode(' / ', $conflicts);
        return;
    }
    $pdo->commit();
}

function updateSessions() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'update_sessions') return;
    if (!ensureRole('admin')) return;
    $pdo = db();
    $courseId = (int)$_POST['course_id'];
    $applyRange = isset($_POST['apply_range']);
    $targetDate = $_POST['session_date'];
    $dateTo = $applyRange ? $_POST['range_end'] : $targetDate;
    $start = new DateTime($targetDate);
    $end = new DateTime($dateTo);
    $interval = new DateInterval('P1D');
    for ($dt = clone $start; $dt <= $end; $dt->add($interval)) {
        $date = $dt->format('Y-m-d');
        $status = isset($_POST['cancel']) ? 'cancelled' : 'scheduled';
        $roomId = $_POST['new_room_id'] ?: null;
        $startTime = $_POST['new_start_time'] ?: null;
        $endTime = $_POST['new_end_time'] ?: null;
        $stmt = $pdo->prepare("SELECT cs.*, c.teacher_id FROM course_sessions cs JOIN courses c ON cs.course_id=c.id WHERE cs.course_id=? AND cs.session_date=?");
        $stmt->execute([$courseId, $date]);
        $session = $stmt->fetch();
        if (!$session) continue;
        $st = $startTime ?: $session['start_time'];
        $et = $endTime ?: $session['end_time'];
        $conflicts = detectConflicts($roomId ?: $session['room_id'], $session['teacher_id'], $date, $st, $et, $courseId);
        if ($conflicts) {
            $GLOBALS['error'] = implode(' / ', $conflicts);
            return;
        }
        $upd = $pdo->prepare("UPDATE course_sessions SET status=?, room_id=?, start_time=?, end_time=?, note=? WHERE id=?");
        $upd->execute([$status, $roomId ?: $session['room_id'], $st, $et, trim($_POST['note']), $session['id']]);
    }
}

function addHoliday() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'add_holiday') return;
    if (!ensureRole('admin')) return;
    $stmt = db()->prepare("INSERT IGNORE INTO holidays(holiday_date,name) VALUES(?,?)");
    $stmt->execute([$_POST['holiday_date'], trim($_POST['holiday_name'])]);
}

function recordAttendance() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'record_attendance') return;
    $user = $GLOBALS['user'];
    if (!$user) return;
    $sessionId = (int)$_POST['session_id'];
    $pdo = db();
    // Ensure teacher owns course unless admin
    if ($user['role'] === 'teacher') {
        $stmt = $pdo->prepare("SELECT c.teacher_id FROM course_sessions cs JOIN courses c ON cs.course_id=c.id WHERE cs.id=?");
        $stmt->execute([$sessionId]);
        $owner = $stmt->fetchColumn();
        if ((int)$owner !== (int)$user['id']) return;
    }
    foreach ($_POST['attendance'] as $studentId => $status) {
        $stmt = $pdo->prepare("INSERT INTO attendance(session_id,student_id,status) VALUES(?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), note=VALUES(note)");
        $stmt->execute([$sessionId, (int)$studentId, $status]);
    }
}

function downloadReport() {
    if (!isset($_GET['download']) || $_GET['download'] !== 'report') return;
    requireLogin();
    $from = $_GET['from'];
    $to = $_GET['to'];
    $stmt = db()->prepare("SELECT c.title, cs.session_date, cs.start_time, cs.end_time, s.full_name, a.status
        FROM attendance a
        JOIN course_sessions cs ON a.session_id=cs.id
        JOIN courses c ON cs.course_id=c.id
        JOIN students s ON a.student_id=s.id
        WHERE cs.session_date BETWEEN ? AND ?
        ORDER BY cs.session_date ASC");
    $stmt->execute([$from, $to]);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="rapor.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Kurs', 'Tarih', 'Başlangıç', 'Bitiş', 'Öğrenci', 'Durum']);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

downloadReport();

addBuilding();
addRoom();
addUser();
addCourse();
updateSessions();
addStudent();
addEnrollment();
addHoliday();
recordAttendance();

function fetchOptions($table) {
    $stmt = db()->query("SELECT * FROM {$table} ORDER BY name");
    return $stmt->fetchAll();
}

function getWeekDates($offset = 0) {
    $monday = new DateTime();
    $monday->setISODate((int)$monday->format('o'), (int)$monday->format('W') + $offset, 1);
    $days = [];
    for ($i=0; $i<7; $i++) {
        $day = clone $monday;
        $day->modify("+{$i} day");
        $days[] = $day;
    }
    return $days;
}

function fetchSessionsForWeek($startDate, $endDate, $user) {
    $pdo = db();
    $query = "SELECT cs.*, c.title, c.teacher_id, u.name AS teacher, r.name AS room, b.name AS building
        FROM course_sessions cs
        JOIN courses c ON cs.course_id=c.id
        LEFT JOIN rooms r ON cs.room_id=r.id
        LEFT JOIN buildings b ON r.building_id=b.id
        LEFT JOIN users u ON c.teacher_id=u.id
        WHERE cs.session_date BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    if ($user && $user['role'] === 'teacher') {
        $query .= " AND c.teacher_id=?";
        $params[] = $user['id'];
    }
    $stmt = $pdo->prepare($query . " ORDER BY cs.session_date, cs.start_time");
    $stmt->execute($params);
    $sessions = [];
    foreach ($stmt->fetchAll() as $row) {
        $sessions[$row['session_date']][] = $row;
    }
    return $sessions;
}

function fetchEnrollmentsByCourse($courseId) {
    $stmt = db()->prepare("SELECT s.* FROM enrollments e JOIN students s ON e.student_id=s.id WHERE e.course_id=? ORDER BY s.full_name");
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}

$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$weekDays = getWeekDates($weekOffset);
$weekStart = $weekDays[0]->format('Y-m-d');
$weekEnd = end($weekDays)->format('Y-m-d');
$sessionsByDate = fetchSessionsForWeek($weekStart, $weekEnd, $user);
$buildings = fetchOptions('buildings');
$rooms = db()->query("SELECT rooms.*, buildings.name AS building_name FROM rooms LEFT JOIN buildings ON rooms.building_id=buildings.id ORDER BY rooms.name")->fetchAll();
$teachers = db()->query("SELECT * FROM users WHERE role='teacher' ORDER BY name")->fetchAll();
$courses = db()->query("SELECT c.*, u.name AS teacher, r.name AS room FROM courses c LEFT JOIN users u ON c.teacher_id=u.id LEFT JOIN rooms r ON c.room_id=r.id ORDER BY c.title")->fetchAll();
$students = db()->query("SELECT * FROM students ORDER BY full_name")->fetchAll();
$holidays = db()->query("SELECT * FROM holidays ORDER BY holiday_date")->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kurs Takip Uygulaması</title>
<style>
    :root { --primary:#1d4ed8; --secondary:#e0f2fe; --bg:#f8fafc; --danger:#dc2626; }
    body { font-family: Arial, sans-serif; margin:0; background: var(--bg); color:#0f172a; }
    header { background: var(--primary); color:white; padding: 12px 16px; display:flex; justify-content:space-between; align-items:center; }
    h1 { margin:0; font-size:20px; }
    .container { padding: 16px; display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:16px; }
    form { background:white; padding:12px; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.05); display:flex; flex-direction:column; gap:8px; }
    input, select, textarea, button { padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; width:100%; box-sizing:border-box; }
    button { background: var(--primary); color:white; border:none; cursor:pointer; transition: background .2s; }
    button:hover { background:#1e3a8a; }
    table.calendar { width:100%; border-collapse: collapse; }
    table.calendar th, table.calendar td { border:1px solid #e2e8f0; padding:8px; vertical-align: top; }
    table.calendar th { background:#f1f5f9; }
    .session { margin-bottom:6px; padding:6px; border-radius:8px; background:var(--secondary); }
    .session.cancelled { background:#fee2e2; color:#b91c1c; text-decoration: line-through; }
    .alert { background:#fee2e2; color:#b91c1c; padding:8px; border-radius:8px; }
    .chip { display:inline-block; padding:2px 6px; background: #e2e8f0; border-radius: 6px; margin-left:4px; font-size:12px; }
    .flex { display:flex; gap:8px; flex-wrap:wrap; }
    .holiday { background:#fef3c7; color:#92400e; padding:6px; border-radius:8px; }
    .section-title { font-size:16px; margin:0 0 8px 0; }
    .calendar-nav { display:flex; gap:8px; margin: 12px 0; align-items:center; }
    @media (max-width:600px) { table.calendar th, table.calendar td { font-size:12px; } }
</style>
</head>
<body>
<header>
    <h1>Çeşme Belediyesi Kültür Müdürlüğü - Kurs Takip</h1>
    <div>
        <?php if($user): ?>
            Hoş geldiniz, <?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['role']; ?>)
            | <a style="color:white" href="?logout=1">Çıkış</a>
        <?php endif; ?>
    </div>
</header>
<main style="padding:16px;">
<?php if(!$user): ?>
    <div class="container">
        <form method="post">
            <h2 class="section-title">Giriş</h2>
            <?php if(isset($GLOBALS['login_error'])) echo '<p class="alert">'.$GLOBALS['login_error'].'</p>'; ?>
            <input type="hidden" name="action" value="login">
            <input name="email" placeholder="E-posta" required>
            <input type="password" name="password" placeholder="Şifre" required>
            <button>Giriş Yap</button>
            <small>Varsayılan yönetici: admin@example.com / admin123</small>
        </form>
    </div>
<?php else: ?>
    <?php if(isset($GLOBALS['error'])) echo '<p class="alert">'.htmlspecialchars($GLOBALS['error']).'</p>'; ?>
    <section>
        <div class="calendar-nav">
            <a href="?week=<?php echo $weekOffset-1; ?>" style="text-decoration:none;">⬅️ Önceki Hafta</a>
            <strong><?php echo $weekDays[0]->format('d M Y'); ?> - <?php echo end($weekDays)->format('d M Y'); ?></strong>
            <a href="?week=<?php echo $weekOffset+1; ?>" style="text-decoration:none;">➡️ Sonraki Hafta</a>
        </div>
        <table class="calendar" id="calendar">
            <tr>
                <?php foreach($weekDays as $day): ?>
                    <th><?php echo $day->format('l d.m'); ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach($weekDays as $day): $date=$day->format('Y-m-d'); ?>
                    <td data-date="<?php echo $date; ?>" data-day="<?php echo $day->format('N'); ?>">
                        <?php foreach($holidays as $h){ if($h['holiday_date']===$date) echo '<div class="holiday">Resmi Tatil: '.htmlspecialchars($h['name']).'</div>'; }
                        if(isset($sessionsByDate[$date])): foreach($sessionsByDate[$date] as $s): ?>
                            <div class="session <?php echo $s['status']==='cancelled'?'cancelled':''; ?>">
                                <strong><?php echo htmlspecialchars($s['title']); ?></strong><br>
                                <?php echo substr($s['start_time'],0,5).' - '.substr($s['end_time'],0,5); ?><br>
                                <?php echo htmlspecialchars($s['room'] ?: 'Sınıf bilgisi yok'); ?>
                                <?php if($s['building']) echo ' - '.htmlspecialchars($s['building']); ?>
                                <div class="chip">Eğitmen: <?php echo htmlspecialchars($s['teacher']); ?></div>
                                <?php if($user['role']!=='teacher' || (int)$s['teacher_id']===(int)$user['id']): ?>
                                <details>
                                    <summary>Yoklama</summary>
                                    <?php $enrolls = fetchEnrollmentsByCourse($s['course_id']); if(!$enrolls): ?>
                                        <em>Öğrenci yok</em>
                                    <?php else: ?>
                                    <form method="post" class="flex">
                                        <input type="hidden" name="action" value="record_attendance">
                                        <input type="hidden" name="session_id" value="<?php echo $s['id']; ?>">
                                        <?php foreach($enrolls as $st): ?>
                                            <label style="display:block; min-width:140px;">
                                                <?php echo htmlspecialchars($st['full_name']); ?>
                                                <select name="attendance[<?php echo $st['id']; ?>]">
                                                    <option value="present">Var</option>
                                                    <option value="absent">Yok</option>
                                                </select>
                                            </label>
                                        <?php endforeach; ?>
                                        <button>Kaydet</button>
                                    </form>
                                    <?php endif; ?>
                                </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        </table>
    </section>
    <div class="container">
        <?php if($user['role']==='admin'): ?>
        <form method="post" id="courseForm">
            <h3 class="section-title">Yeni Kurs</h3>
            <input type="hidden" name="action" value="add_course">
            <input name="title" placeholder="Kurs Adı" required>
            <textarea name="description" placeholder="Açıklama"></textarea>
            <label>Öğretmen</label>
            <select name="teacher_id" required>
                <?php foreach($teachers as $t) echo '<option value="'.$t['id'].'">'.htmlspecialchars($t['name']).'</option>'; ?>
            </select>
            <label>Sınıf/Atölye</label>
            <select name="room_id">
                <option value="">Belirsiz</option>
                <?php foreach($rooms as $r) echo '<option value="'.$r['id'].'">'.htmlspecialchars($r['name']).($r['building_name']?' ('.$r['building_name'].')':'').'</option>'; ?>
            </select>
            <label>Gün</label>
            <select name="day_of_week" required>
                <option value="1">Pazartesi</option><option value="2">Salı</option><option value="3">Çarşamba</option><option value="4">Perşembe</option><option value="5">Cuma</option><option value="6">Cumartesi</option><option value="7">Pazar</option>
            </select>
            <div class="flex">
                <input type="date" name="start_date" required>
                <input type="date" name="end_date" required>
            </div>
            <div class="flex">
                <input type="time" name="start_time" required>
                <input type="time" name="end_time" required>
            </div>
            <button>Kursu Kaydet ve Programı Oluştur</button>
        </form>

        <form method="post" id="sessionForm">
            <h3 class="section-title">Ders Güncelle / İptal</h3>
            <input type="hidden" name="action" value="update_sessions">
            <label>Kurs</label>
            <select name="course_id" required>
                <?php foreach($courses as $c) echo '<option value="'.$c['id'].'">'.htmlspecialchars($c['title']).'</option>'; ?>
            </select>
            <label>Hedef Tarih</label>
            <input type="date" name="session_date" required>
            <label>Yeni Sınıf (opsiyonel)</label>
            <select name="new_room_id"><option value="">Aynı kalsın</option><?php foreach($rooms as $r) echo '<option value="'.$r['id'].'">'.htmlspecialchars($r['name']).'</option>'; ?></select>
            <div class="flex">
                <input type="time" name="new_start_time" placeholder="Yeni başlangıç">
                <input type="time" name="new_end_time" placeholder="Yeni bitiş">
            </div>
            <label><input type="checkbox" name="cancel" value="1"> Bu günü iptal et</label>
            <label><input type="checkbox" name="apply_range" value="1"> Tarih aralığı uygulansın</label>
            <input type="date" name="range_end" value="<?php echo date('Y-m-d'); ?>">
            <textarea name="note" placeholder="Not"></textarea>
            <button>Güncelle</button>
        </form>

        <form method="post">
            <h3 class="section-title">Yeni Bina / Sınıf</h3>
            <input type="hidden" name="action" value="add_building">
            <input name="name" placeholder="Bina adı" required>
            <input name="address" placeholder="Adres">
            <button>Bina Ekle</button>
        </form>

        <form method="post">
            <h3 class="section-title">Yeni Sınıf</h3>
            <input type="hidden" name="action" value="add_room">
            <label>Bina</label>
            <select name="building_id"><option value="">Seçiniz</option><?php foreach($buildings as $b) echo '<option value="'.$b['id'].'">'.htmlspecialchars($b['name']).'</option>'; ?></select>
            <input name="name" placeholder="Sınıf/Atölye adı" required>
            <input name="capacity" type="number" placeholder="Kapasite">
            <button>Sınıf Ekle</button>
        </form>

        <form method="post">
            <h3 class="section-title">Yeni Kullanıcı</h3>
            <input type="hidden" name="action" value="add_user">
            <input name="name" placeholder="Ad Soyad" required>
            <input name="email" placeholder="E-posta" required>
            <input type="password" name="password" placeholder="Şifre" required>
            <select name="role"><option value="admin">Yönetici</option><option value="teacher">Öğretmen</option></select>
            <button>Kullanıcı Ekle</button>
        </form>

        <form method="post">
            <h3 class="section-title">Resmi Tatil Ekle</h3>
            <input type="hidden" name="action" value="add_holiday">
            <input type="date" name="holiday_date" required>
            <input name="holiday_name" placeholder="Tatil Adı" required>
            <button>Tatil Kaydet</button>
        </form>
        <?php endif; ?>

        <form method="post">
            <h3 class="section-title">Öğrenci Ekle</h3>
            <input type="hidden" name="action" value="add_student">
            <input name="full_name" placeholder="Ad Soyad" required>
            <input name="phone" placeholder="Telefon">
            <input name="email" placeholder="E-posta">
            <button>Öğrenci Kaydet</button>
        </form>

        <form method="post">
            <h3 class="section-title">Kursa Öğrenci Kaydı</h3>
            <input type="hidden" name="action" value="add_enrollment">
            <label>Kurs</label>
            <select name="course_id" required><?php foreach($courses as $c) echo '<option value="'.$c['id'].'">'.htmlspecialchars($c['title']).'</option>'; ?></select>
            <label>Öğrenci</label>
            <select name="student_id" required><?php foreach($students as $s) echo '<option value="'.$s['id'].'">'.htmlspecialchars($s['full_name']).'</option>'; ?></select>
            <button>Kaydet</button>
        </form>

        <form method="get">
            <h3 class="section-title">Raporlama</h3>
            <input type="hidden" name="download" value="report">
            <div class="flex">
                <input type="date" name="from" required>
                <input type="date" name="to" required>
            </div>
            <button>CSV İndir</button>
        </form>
    </div>
<?php endif; ?>
</main>
<script>
<?php if($user && $user['role']==='admin'): ?>
const calendar = document.getElementById('calendar');
if (calendar) {
    calendar.querySelectorAll('td[data-date]').forEach(cell => {
        cell.style.cursor = 'pointer';
        cell.title = 'Bu gün için yeni kurs oluştur / düzenle';
        cell.addEventListener('click', () => {
            const date = cell.dataset.date;
            const day = cell.dataset.day;
            const courseForm = document.getElementById('courseForm');
            const sessionForm = document.getElementById('sessionForm');
            if (courseForm) {
                courseForm.querySelector('input[name="start_date"]').value = date;
                courseForm.querySelector('input[name="end_date"]').value = date;
                courseForm.querySelector('select[name="day_of_week"]').value = day;
                courseForm.scrollIntoView({behavior:'smooth'});
            }
            if (sessionForm) {
                sessionForm.querySelector('input[name="session_date"]').value = date;
            }
        });
    });
}
<?php endif; ?>
</script>
</body>
</html>
