<?php
// api.php
// Tüm AJAX istekleri için merkezi giriş noktası.

header('Content-Type: application/json');
require_once 'config.php';

// Hataları yakalamak için bir error handler
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode(['error' => $exception->getMessage()]);
    exit;
});

// Gelen isteği al
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// İsteğe göre yönlendirme yap
switch ($action) {
    case 'login':
        if ($method === 'POST') {
            handle_login($pdo, $input);
        }
        break;

    // --- ÖĞRENCİ YÖNETİMİ EYLEMLERİ (Sadece Admin) ---
    case 'get_students':
        if ($method === 'GET' && isAdmin()) {
            handle_get_students($pdo);
        }
        break;

    case 'add_student':
        if ($method === 'POST' && isAdmin()) {
            handle_add_student($pdo, $input);
        }
        break;

    case 'delete_student':
        if ($method === 'POST' && isAdmin()) {
            handle_delete_student($pdo, $input);
        }
        break;

    // --- DERS PROGRAMI YÖNETİMİ (Sadece Admin) ---
    case 'get_form_data':
        if ($method === 'GET' && isAdmin()) {
            handle_get_form_data($pdo);
        }
        break;

    case 'add_schedule':
        if ($method === 'POST' && isAdmin()) {
            handle_add_schedule($pdo, $input);
        }
        break;

    // --- YOKLAMA YÖNETİMİ ---
    case 'get_attendance_data':
        if ($method === 'GET' && isset($_GET['schedule_id']) && isset($_SESSION['user_id'])) {
            handle_get_attendance_data($pdo, $_GET['schedule_id']);
        }
        break;

    case 'save_attendance':
        if ($method === 'POST' && isset($_SESSION['user_id'])) {
            handle_save_attendance($pdo, $input);
        }
        break;

    case 'logout':
        if ($method === 'POST') {
            handle_logout();
        }
        break;

    case 'get_schedules':
        if ($method === 'GET') {
            // Oturum kontrolü ekle
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401); // Unauthorized
                echo json_encode(['error' => 'Bu işlemi yapmak için giriş yapmalısınız.']);
                exit;
            }
            handle_get_schedules($pdo);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Belirtilen eylem bulunamadı.']);
        break;
}

/**
 * Belirtilen tarih aralığındaki ders programlarını veritabanından çeker.
 * @param PDO $pdo Veritabanı bağlantı nesnesi.
 */
function handle_get_schedules($pdo) {
    $startDate = $_GET['start'] ?? date('Y-m-d');
    $endDate = $_GET['end'] ?? date('Y-m-d', strtotime('+7 days'));

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.start_time,
            s.end_time,
            c.name as course_name,
            cr.name as classroom_name,
            b.name as building_name,
            u.full_name as teacher_name
        FROM schedules s
        JOIN courses c ON s.course_id = c.id
        JOIN classrooms cr ON s.classroom_id = cr.id
        JOIN buildings b ON cr.building_id = b.id
        JOIN users u ON c.teacher_id = u.id
        WHERE s.start_time BETWEEN ? AND ?
        AND s.status = 'scheduled'
    ");
    $stmt->execute([$startDate, $endDate]);
    $schedules = $stmt->fetchAll();

    echo json_encode($schedules);
}

/**
 * Kullanıcı girişini işler ve oturum başlatır.
 * @param PDO $pdo
 * @param array $input Gelen JSON verisi ('username', 'password').
 */
function handle_login($pdo, $input) {
    if (empty($input['username']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Kullanıcı adı ve şifre zorunludur.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$input['username']]);
    $user = $stmt->fetch();

    // Güvenli şifre doğrulama
    if ($user && password_verify($input['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Geçersiz kullanıcı adı veya şifre.']);
    }
}

/**
 * Kullanıcı oturumunu sonlandırır.
 */
function handle_logout() {
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
}


// --- ÖĞRENCİ YÖNETİMİ FONKSİYONLARI ---

function handle_get_students($pdo) {
    $stmt = $pdo->query("SELECT id, full_name FROM students ORDER BY full_name");
    $students = $stmt->fetchAll();
    echo json_encode($students);
}

function handle_add_student($pdo, $input) {
    if (empty($input['full_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Öğrenci adı boş olamaz.']);
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO students (full_name) VALUES (?)");
    $stmt->execute([$input['full_name']]);
    $newStudentId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $newStudentId, 'full_name' => $input['full_name']]);
}

function handle_delete_student($pdo, $input) {
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Öğrenci ID\'si belirtilmelidir.']);
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$input['id']]);
    echo json_encode(['success' => true]);
}


// --- DERS PROGRAMI YÖNETİMİ FONKSİYONLARI ---

function handle_get_form_data($pdo) {
    $data = [];
    $data['courses'] = $pdo->query("SELECT id, name FROM courses ORDER BY name")->fetchAll();
    $data['classrooms'] = $pdo->query("SELECT id, name FROM classrooms ORDER BY name")->fetchAll();
    $data['students'] = $pdo->query("SELECT id, full_name FROM students ORDER BY full_name")->fetchAll();
    // Not: Gerçekte kurs ve öğretmen ilişkisi daha karmaşık olabilir. Şimdilik tüm öğretmenleri getiriyoruz.
    $data['teachers'] = $pdo->query("SELECT id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name")->fetchAll();
    echo json_encode($data);
}

function handle_add_schedule($pdo, $input) {
    // Gerekli alanların kontrolü
    $required = ['course_id', 'classroom_id', 'start_date', 'end_date', 'day_of_week', 'start_time', 'end_time', 'student_ids'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "$field alanı zorunludur."]);
            return;
        }
    }

    $pdo->beginTransaction();
    try {
        // 1. Yeni bir kurs oluştur (eğer seçildiyse) veya mevcut kursu kullan
        // Bu kısım şimdilik basitleştirilmiştir. Formda yeni kurs adı da olabilir.
        $courseId = $input['course_id'];

        // 2. Öğrencileri kursa kaydet (enrollments)
        $enrollStmt = $pdo->prepare("INSERT IGNORE INTO course_enrollments (course_id, student_id) VALUES (?, ?)");
        foreach ($input['student_ids'] as $studentId) {
            $enrollStmt->execute([$courseId, $studentId]);
        }

        // 3. Ders programlarını oluştur
        $startDate = new DateTime($input['start_date']);
        $endDate = new DateTime($input['end_date']);
        $dayOfWeek = intval($input['day_of_week']);

        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            if (intval($currentDate->format('w')) === $dayOfWeek) {
                $startDateTime = $currentDate->format('Y-m-d') . ' ' . $input['start_time'];
                $endDateTime = $currentDate->format('Y-m-d') . ' ' . $input['end_time'];

                // Çakışma kontrolü (iyileştirilmiş sorgu)
                $conflictStmt = $pdo->prepare("
                    SELECT id FROM schedules
                    WHERE classroom_id = :classroom_id AND status = 'scheduled' AND
                    NOT (end_time <= :start_time OR start_time >= :end_time)
                ");
                $conflictStmt->execute([
                    ':classroom_id' => $input['classroom_id'],
                    ':start_time' => $startDateTime,
                    ':end_time' => $endDateTime
                ]);
                if ($conflictStmt->fetch()) {
                    throw new Exception("Çakışma: " . $currentDate->format('Y-m-d') . " tarihinde bu saatte sınıf dolu.");
                }

                $scheduleStmt = $pdo->prepare("INSERT INTO schedules (course_id, classroom_id, start_time, end_time) VALUES (?, ?, ?, ?)");
                $scheduleStmt->execute([$courseId, $input['classroom_id'], $startDateTime, $endDateTime]);
            }
            $currentDate->modify('+1 day');
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Ders programı başarıyla oluşturuldu.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(409); // Conflict
        echo json_encode(['error' => $e->getMessage()]);
    }
}


// --- YOKLAMA YÖNETİMİ FONKSİYONLARI ---

function handle_get_attendance_data($pdo, $scheduleId) {
    // 1. Derse kayıtlı öğrencileri getir
    $stmtStudents = $pdo->prepare("
        SELECT s.id, s.full_name
        FROM students s
        JOIN course_enrollments ce ON s.id = ce.student_id
        JOIN schedules sc ON ce.course_id = sc.course_id
        WHERE sc.id = ?
    ");
    $stmtStudents->execute([$scheduleId]);
    $students = $stmtStudents->fetchAll();

    // 2. Mevcut yoklama kaydını getir
    $stmtAttendance = $pdo->prepare("SELECT student_id FROM attendance WHERE schedule_id = ? AND is_present = 1");
    $stmtAttendance->execute([$scheduleId]);
    $presentStudentIds = $stmtAttendance->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['students' => $students, 'present_ids' => $presentStudentIds]);
}

function handle_save_attendance($pdo, $input) {
    if (empty($input['schedule_id']) || !isset($input['present_ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Eksik parametre.']);
        return;
    }

    $scheduleId = $input['schedule_id'];
    $presentStudentIds = $input['present_ids'];

    $pdo->beginTransaction();
    try {
        // Önceki kayıtları temizle
        $stmtDelete = $pdo->prepare("DELETE FROM attendance WHERE schedule_id = ?");
        $stmtDelete->execute([$scheduleId]);

        // Yeni kayıtları ekle
        $stmtInsert = $pdo->prepare("INSERT INTO attendance (schedule_id, student_id, is_present) VALUES (?, ?, 1)");
        foreach ($presentStudentIds as $studentId) {
            $stmtInsert->execute([$scheduleId, $studentId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Yoklama kaydedildi.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e; // Hata yönetimi için hatayı yeniden fırlat
    }
}
?>
