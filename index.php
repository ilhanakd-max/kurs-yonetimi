<?php
// ============== SECTION 1: CONFIGURATION ==============
session_start();
mb_internal_encoding('UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Istanbul');

// Veritabanı bilgileri
$db_host = getenv('MYSQL_HOST') ?: 'sql211.infinityfree.com';
$db_user = getenv('MYSQL_USER') ?: 'if0_40197167';
$db_pass = getenv('MYSQL_PASSWORD') ?: 'test';
$db_name = getenv('MYSQL_DATABASE') ?: 'if0_40197167_test';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00'"
    ]);
} catch (Exception $e) {
    die('<h2>Veritabanına bağlanılamadı</h2><p>'.htmlspecialchars($e->getMessage()).'</p>');
}

function runMigrations(PDO $pdo): void {
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            username VARCHAR(50) UNIQUE NOT NULL,\n            password VARCHAR(255) NOT NULL,\n            full_name VARCHAR(100) NOT NULL,\n            email VARCHAR(100),\n            phone VARCHAR(20),\n            role ENUM('admin','teacher') DEFAULT 'teacher',\n            status TINYINT DEFAULT 1,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS buildings (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            name VARCHAR(100) NOT NULL,\n            address TEXT,\n            description TEXT,\n            status TINYINT DEFAULT 1\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS classrooms (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            building_id INT,\n            name VARCHAR(100) NOT NULL,\n            capacity INT DEFAULT 0,\n            description TEXT,\n            status TINYINT DEFAULT 1,\n            FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE SET NULL\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS courses (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            name VARCHAR(150) NOT NULL,\n            description TEXT,\n            teacher_id INT,\n            classroom_id INT,\n            day_of_week TINYINT NOT NULL COMMENT '1=Monday,7=Sunday',\n            start_time TIME NOT NULL,\n            end_time TIME NOT NULL,\n            start_date DATE NOT NULL,\n            end_date DATE NOT NULL,\n            color VARCHAR(7) DEFAULT '#3788d8',\n            status TINYINT DEFAULT 1,\n            created_by INT,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,\n            FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS course_sessions (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            course_id INT NOT NULL,\n            session_date DATE NOT NULL,\n            start_time TIME NOT NULL,\n            end_time TIME NOT NULL,\n            classroom_id INT,\n            status ENUM('active','cancelled','modified') DEFAULT 'active',\n            cancel_reason TEXT,\n            notes TEXT,\n            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS students (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            full_name VARCHAR(100) NOT NULL,\n            tc_no VARCHAR(11) UNIQUE,\n            birth_date DATE,\n            phone VARCHAR(20),\n            email VARCHAR(100),\n            address TEXT,\n            guardian_name VARCHAR(100),\n            guardian_phone VARCHAR(20),\n            status TINYINT DEFAULT 1,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS course_registrations (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            course_id INT NOT NULL,\n            student_id INT NOT NULL,\n            registration_date DATE DEFAULT (CURRENT_DATE),\n            status ENUM('active','completed','dropped') DEFAULT 'active',\n            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,\n            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,\n            UNIQUE KEY unique_registration (course_id, student_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS attendance (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            session_id INT NOT NULL,\n            student_id INT NOT NULL,\n            status ENUM('present','absent','excused') NOT NULL,\n            notes TEXT,\n            marked_by INT,\n            marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (session_id) REFERENCES course_sessions(id) ON DELETE CASCADE,\n            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,\n            UNIQUE KEY unique_attendance (session_id, student_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS holidays (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            name VARCHAR(100) NOT NULL,\n            holiday_date DATE NOT NULL,\n            recurring_yearly TINYINT DEFAULT 0,\n            created_by INT\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        "CREATE TABLE IF NOT EXISTS settings (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            setting_key VARCHAR(50) UNIQUE NOT NULL,\n            setting_value TEXT,\n            description VARCHAR(255)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];
    foreach ($tables as $sql) { $pdo->exec($sql); }

    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT IGNORE INTO users (username, password, full_name, role) VALUES ('admin', :p, 'Sistem Yöneticisi', 'admin')")->execute([':p'=>$adminHash]);

    $holidays = [
        ['Yılbaşı', '2025-01-01', 1],
        ['Ulusal Egemenlik ve Çocuk Bayramı', '2025-04-23', 1],
        ['Emek ve Dayanışma Günü', '2025-05-01', 1],
        ['Atatürk\'ü Anma, Gençlik ve Spor Bayramı', '2025-05-19', 1],
        ['Zafer Bayramı', '2025-08-30', 1],
        ['Cumhuriyet Bayramı', '2025-10-29', 1],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO holidays (name, holiday_date, recurring_yearly) VALUES (?,?,?)");
    foreach ($holidays as $h) { $stmt->execute($h); }
}
runMigrations($pdo);

// ============== SECTION 2: HELPER FUNCTIONS ==============
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function check_csrf(): bool { return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function require_login(): void { if (!current_user()) { header('Location: ?page=login'); exit; } }
function json_response($data): void { header('Content-Type: application/json'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function day_name(int $d): string { return [1=>'Pazartesi',2=>'Salı',3=>'Çarşamba',4=>'Perşembe',5=>'Cuma',6=>'Cumartesi',7=>'Pazar'][$d] ?? ''; }
function week_range(string $date): array { $ts=strtotime($date); $start=strtotime('-'.(date('N',$ts)-1).' days',$ts); $end=strtotime('+'.(7-date('N',$ts)).' days',$ts); return [date('Y-m-d',$start), date('Y-m-d',$end)]; }
function fetch_teachers(PDO $pdo): array { return $pdo->query("SELECT id, full_name FROM users WHERE status=1 AND role='teacher' ORDER BY full_name")->fetchAll(); }
function fetch_buildings(PDO $pdo): array { return $pdo->query("SELECT * FROM buildings WHERE status=1 ORDER BY name")->fetchAll(); }
function fetch_classrooms(PDO $pdo): array { return $pdo->query("SELECT * FROM classrooms WHERE status=1 ORDER BY name")->fetchAll(); }
function fetch_users(PDO $pdo): array { return $pdo->query("SELECT * FROM users ORDER BY id")->fetchAll(); }
function get_course(int $id, PDO $pdo): ?array {
    $stmt=$pdo->prepare("SELECT c.*, u.full_name AS teacher_name, cl.name AS classroom_name, b.name AS building_name FROM courses c LEFT JOIN users u ON c.teacher_id=u.id LEFT JOIN classrooms cl ON c.classroom_id=cl.id LEFT JOIN buildings b ON cl.building_id=b.id WHERE c.id=?");
    $stmt->execute([$id]); return $stmt->fetch() ?: null;
}
function get_courses_for_week(PDO $pdo, string $start, string $end): array {
    $stmt=$pdo->prepare("SELECT cs.*, c.name AS course_name, c.color, c.id AS course_id, u.full_name AS teacher_name, cl.name AS classroom_name, b.name AS building_name FROM course_sessions cs JOIN courses c ON cs.course_id=c.id LEFT JOIN users u ON c.teacher_id=u.id LEFT JOIN classrooms cl ON cs.classroom_id=cl.id LEFT JOIN buildings b ON cl.building_id=b.id WHERE cs.session_date BETWEEN ? AND ? AND c.status=1 ORDER BY cs.session_date, cs.start_time");
    $stmt->execute([$start,$end]); return $stmt->fetchAll();
}
function get_holidays(PDO $pdo, string $start, string $end): array {
    $stmt=$pdo->prepare("SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ? OR (recurring_yearly=1 AND DATE_FORMAT(holiday_date,'%m-%d') BETWEEN DATE_FORMAT(?,'%m-%d') AND DATE_FORMAT(?,'%m-%d'))");
    $stmt->execute([$start,$end,$start,$end]); return $stmt->fetchAll();
}
function check_conflict(PDO $pdo, $classroomId, $dayOfWeek, $startTime, $endTime, $courseId=null): ?array {
    $sql="SELECT c.* FROM courses c WHERE c.classroom_id=? AND c.day_of_week=? AND c.status=1";
    $params=[$classroomId,$dayOfWeek];
    if($courseId){ $sql.=" AND c.id!=?"; $params[]=$courseId; }
    $sql.=" AND ((? BETWEEN c.start_time AND c.end_time) OR (? BETWEEN c.start_time AND c.end_time) OR (c.start_time BETWEEN ? AND ?))";
    $params=array_merge($params,[$startTime,$endTime,$startTime,$endTime]);
    $stmt=$pdo->prepare($sql); $stmt->execute($params); return $stmt->fetch() ?: null;
}
function generate_sessions(PDO $pdo, int $courseId): void {
    $course=get_course($courseId,$pdo); if(!$course) return; $pdo->prepare("DELETE FROM course_sessions WHERE course_id=?")->execute([$courseId]);
    $start=strtotime($course['start_date']); $end=strtotime($course['end_date']); $dow=(int)$course['day_of_week'];
    for($t=$start;$t<=$end;$t=strtotime('+1 day',$t)){
        if((int)date('N',$t)!==$dow) continue;
        $pdo->prepare("INSERT INTO course_sessions (course_id, session_date, start_time, end_time, classroom_id) VALUES (?,?,?,?,?)")
            ->execute([$courseId,date('Y-m-d',$t),$course['start_time'],$course['end_time'],$course['classroom_id']]);
    }
}
function ensure_active_user(PDO $pdo): void {
    if(current_user()){
        $st=$pdo->prepare("SELECT status FROM users WHERE id=?"); $st->execute([current_user()['id']]); if($st->fetchColumn()!=1){ session_destroy(); header('Location: ?page=login'); exit; }
    }
}
ensure_active_user($pdo);

// ============== SECTION 3: API ENDPOINTS ==============
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
    $action=$_POST['action'];
    if($action!=='login' && !current_user()) json_response(['status'=>'error','message'=>'Oturum açın.']);
    switch($action){
        case 'login':
            $u=trim($_POST['username']??''); $p=$_POST['password']??'';
            $st=$pdo->prepare("SELECT * FROM users WHERE username=? AND status=1"); $st->execute([$u]); $user=$st->fetch();
            if($user && password_verify($p,$user['password'])){ session_regenerate_id(true); $_SESSION['user']=$user; json_response(['status'=>'success']); }
            json_response(['status'=>'error','message'=>'Geçersiz kullanıcı adı veya şifre.']);
        case 'save_course':
            if(!is_admin()) json_response(['status'=>'error','message'=>'Yetki yok.']);
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            $data=[
                'id'=>$_POST['course_id']?:null,
                'name'=>trim($_POST['name']??''),
                'description'=>trim($_POST['description']??''),
                'teacher_id'=>$_POST['teacher_id']?:null,
                'classroom_id'=>$_POST['classroom_id']?:null,
                'day_of_week'=>(int)($_POST['day_of_week']??1),
                'start_time'=>$_POST['start_time']??'',
                'end_time'=>$_POST['end_time']??'',
                'start_date'=>$_POST['start_date']??'',
                'end_date'=>$_POST['end_date']??'',
                'color'=>$_POST['color']??'#3788d8'
            ];
            if(!$data['name']||!$data['classroom_id']) json_response(['status'=>'error','message'=>'Ad ve sınıf zorunlu.']);
            if($conf=check_conflict($pdo,$data['classroom_id'],$data['day_of_week'],$data['start_time'],$data['end_time'],$data['id']))
                json_response(['status'=>'conflict','message'=>'Çakışma tespit edildi: '.$conf['name']]);
            if($data['id']){
                $pdo->prepare("UPDATE courses SET name=:name, description=:description, teacher_id=:teacher_id, classroom_id=:classroom_id, day_of_week=:day_of_week, start_time=:start_time, end_time=:end_time, start_date=:start_date, end_date=:end_date, color=:color WHERE id=:id")
                    ->execute($data);
                $courseId=(int)$data['id'];
            } else {
                $pdo->prepare("INSERT INTO courses (name,description,teacher_id,classroom_id,day_of_week,start_time,end_time,start_date,end_date,color,created_by) VALUES (:name,:description,:teacher_id,:classroom_id,:day_of_week,:start_time,:end_time,:start_date,:end_date,:color,:created_by)")
                    ->execute($data+['created_by'=>current_user()['id']]);
                $courseId=(int)$pdo->lastInsertId();
            }
            generate_sessions($pdo,$courseId);
            json_response(['status'=>'success','message'=>'Kurs kaydedildi.']);
        case 'delete_course':
            if(!is_admin()) json_response(['status'=>'error','message'=>'Yetki yok.']);
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            $pdo->prepare("DELETE FROM courses WHERE id=?")->execute([(int)($_POST['id']??0)]);
            json_response(['status'=>'success']);
        case 'save_student':
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            $id=$_POST['student_id']??null;
            $data=[
                'full_name'=>trim($_POST['full_name']??''),
                'tc_no'=>trim($_POST['tc_no']??''),
                'birth_date'=>$_POST['birth_date']??null,
                'phone'=>trim($_POST['phone']??''),
                'email'=>trim($_POST['email']??''),
                'address'=>trim($_POST['address']??''),
                'guardian_name'=>trim($_POST['guardian_name']??''),
                'guardian_phone'=>trim($_POST['guardian_phone']??'')
            ];
            if(!$data['full_name']) json_response(['status'=>'error','message'=>'Ad soyad zorunlu.']);
            if($id){ $data['id']=$id; $pdo->prepare("UPDATE students SET full_name=:full_name, tc_no=:tc_no, birth_date=:birth_date, phone=:phone, email=:email, address=:address, guardian_name=:guardian_name, guardian_phone=:guardian_phone WHERE id=:id")->execute($data); }
            else { $pdo->prepare("INSERT INTO students (full_name, tc_no, birth_date, phone, email, address, guardian_name, guardian_phone) VALUES (:full_name,:tc_no,:birth_date,:phone,:email,:address,:guardian_name,:guardian_phone)")->execute($data); }
            json_response(['status'=>'success','message'=>'Öğrenci kaydedildi.']);
        case 'delete_student':
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            if(!is_admin()) json_response(['status'=>'error','message'=>'Yetki yok.']);
            $pdo->prepare("DELETE FROM students WHERE id=?")->execute([(int)($_POST['id']??0)]);
            json_response(['status'=>'success']);
        case 'register_student':
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            $pdo->prepare("INSERT IGNORE INTO course_registrations (course_id, student_id) VALUES (?,?)")->execute([(int)($_POST['course_id']??0),(int)($_POST['student_id']??0)]);
            json_response(['status'=>'success','message'=>'Kayıt eklendi.']);
        case 'save_attendance':
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            $sessionId=(int)($_POST['session_id']??0); $entries=$_POST['attendance']??[];
            foreach($entries as $sid=>$status){ $note=$_POST['notes'][$sid]??''; $pdo->prepare("INSERT INTO attendance (session_id, student_id, status, notes, marked_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), notes=VALUES(notes), marked_by=VALUES(marked_by), marked_at=CURRENT_TIMESTAMP")->execute([$sessionId,$sid,$status,$note,current_user()['id']]); }
            json_response(['status'=>'success','message'=>'Yoklama kaydedildi.']);
        case 'save_settings':
            if(!is_admin()) json_response(['status'=>'error','message'=>'Yetki yok.']);
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            foreach($_POST['settings'] as $k=>$v){ $st=$pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k,:v) ON DUPLICATE KEY UPDATE setting_value=:v2"); $st->execute([':k'=>$k,':v'=>$v,':v2'=>$v]); }
            json_response(['status'=>'success','message'=>'Ayarlar güncellendi.']);
        case 'save_building':
            if(!is_admin()) json_response(['status'=>'error','message'=>'Yetki yok.']);
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            $name=trim($_POST['name']??''); $address=trim($_POST['address']??''); $desc=trim($_POST['description']??'');
            if(!$name) json_response(['status'=>'error','message'=>'Bina adı zorunlu.']);
            $pdo->prepare("INSERT INTO buildings (name,address,description,status) VALUES (?,?,?,1)")->execute([$name,$address,$desc]);
            json_response(['status'=>'success','message'=>'Bina eklendi.']);
        case 'save_classroom':
            if(!is_admin()) json_response(['status'=>'error','message'=>'Yetki yok.']);
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            $name=trim($_POST['name']??''); $building=(int)($_POST['building_id']??0); $cap=(int)($_POST['capacity']??0); $desc=trim($_POST['description']??'');
            if(!$name || !$building) json_response(['status'=>'error','message'=>'Bina ve sınıf adı zorunlu.']);
            $pdo->prepare("INSERT INTO classrooms (building_id,name,capacity,description,status) VALUES (?,?,?,?,1)")->execute([$building,$name,$cap,$desc]);
            json_response(['status'=>'success','message'=>'Sınıf eklendi.']);
        case 'save_teacher':
            if(!is_admin()) json_response(['status'=>'error','message'=>'Yetki yok.']);
            if(!check_csrf()) json_response(['status'=>'error','message'=>'CSRF doğrulaması başarısız.']);
            $id=$_POST['teacher_id']??null; $username=trim($_POST['username']??''); $full=trim($_POST['full_name']??''); $email=trim($_POST['email']??''); $phone=trim($_POST['phone']??''); $pass=$_POST['password']??'';
            if(!$username || !$full) json_response(['status'=>'error','message'=>'Kullanıcı adı ve ad soyad zorunlu.']);
            if($id){
                $data=['username'=>$username,'full_name'=>$full,'email'=>$email,'phone'=>$phone,'id'=>$id];
                $sql="UPDATE users SET username=:username, full_name=:full_name, email=:email, phone=:phone WHERE id=:id";
                if($pass){ $data['password']=password_hash($pass,PASSWORD_DEFAULT); $sql="UPDATE users SET username=:username, full_name=:full_name, email=:email, phone=:phone, password=:password WHERE id=:id"; }
                $pdo->prepare($sql)->execute($data);
            } else {
                if(!$pass) json_response(['status'=>'error','message'=>'Şifre zorunlu.']);
                $pdo->prepare("INSERT INTO users (username,password,full_name,email,phone,role,status) VALUES (?,?,?,?,?,'teacher',1)")
                    ->execute([$username,password_hash($pass,PASSWORD_DEFAULT),$full,$email,$phone]);
            }
            json_response(['status'=>'success','message'=>'Öğretmen kaydedildi.']);
        default: json_response(['status'=>'error','message'=>'Bilinmeyen işlem']);
    }
}

if(($_GET['action']??'')==='logout'){ session_destroy(); header('Location: ?page=login'); exit; }

$page=$_GET['page'] ?? (current_user()? 'calendar':'login');
if($page!=='login') require_login();

// Yardımcı veri
$teachers=fetch_teachers($pdo); $buildings=fetch_buildings($pdo); $classrooms=fetch_classrooms($pdo); $users=fetch_users($pdo);
$today=date('Y-m-d'); [$weekStart,$weekEnd]=week_range($_GET['date']??$today); $coursesWeek=get_courses_for_week($pdo,$weekStart,$weekEnd); $holidaysWeek=get_holidays($pdo,$weekStart,$weekEnd);

// AJAX bilgi istekleri
if(isset($_GET['load'])){ json_response(get_course((int)$_GET['load'],$pdo)); }
if(isset($_GET['attendance'])){
    $sessionId=(int)$_GET['attendance'];
    $courseId=$pdo->prepare("SELECT course_id FROM course_sessions WHERE id=?"); $courseId->execute([$sessionId]); $cid=$courseId->fetchColumn();
    $students=$pdo->prepare("SELECT s.* FROM course_registrations cr JOIN students s ON cr.student_id=s.id WHERE cr.course_id=?"); $students->execute([$cid]);
    $existing=$pdo->prepare("SELECT student_id,status,notes FROM attendance WHERE session_id=?"); $existing->execute([$sessionId]); $map=['existing'=>[], 'notes'=>[]]; foreach($existing->fetchAll() as $row){ $map['existing'][$row['student_id']]=$row['status']; $map['notes'][$row['student_id']]=$row['notes']; }
    json_response(['students'=>$students->fetchAll(),'existing'=>$map['existing'],'notes'=>$map['notes']]);
}
if(isset($_GET['detail'])){
    $c=get_course((int)$_GET['detail'],$pdo); if(!$c) json_response(['status'=>'error']);
    $c['day']=day_name((int)$c['day_of_week']).' '.substr($c['start_time'],0,5).'-'.substr($c['end_time'],0,5);
    json_response($c);
}
if(isset($_GET['export']) && $_GET['export']==='excel'){
    $start=$_GET['start']??$weekStart; $end=$_GET['end']??$weekEnd;
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapor.xls"');
    echo "\xEF\xBB\xBF";
    echo "Kurs\tTarih\tSaat\tSınıf\n";
    foreach(get_courses_for_week($pdo,$start,$end) as $r){
        echo $r['course_name']."\t".$r['session_date']."\t".substr($r['start_time'],0,5)."-".substr($r['end_time'],0,5)."\t".$r['classroom_name']."\n";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çeşme Belediyesi Kültür Müdürlüğü - Kurs Takip Sistemi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background:#f7f9fb; }
        .sidebar { min-height:100vh; background:linear-gradient(180deg,#0d6efd,#0b5ed7); color:#fff; }
        .sidebar a { color:#fff; display:block; padding:10px 14px; text-decoration:none; }
        .sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.15); }
        .calendar-grid { display:grid; grid-template-columns:80px repeat(7,1fr); border:1px solid #e5e7eb; }
        .time-slot { height:60px; border-bottom:1px solid #e5e7eb; font-size:12px; padding:4px; }
        .day-column { position:relative; border-left:1px solid #e5e7eb; }
        .day-header { background:#f0f4f8; padding:8px; border-bottom:1px solid #e5e7eb; text-align:center; font-weight:600; }
        .day-slots { height:840px; position:relative; }
        .course-block { position:absolute; left:6px; right:6px; color:#fff; padding:8px; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.12); cursor:pointer; }
        .holiday-banner { position:absolute; inset:0; background:repeating-linear-gradient(45deg, rgba(255,99,71,0.15), rgba(255,99,71,0.15) 10px, rgba(255,99,71,0.08) 10px, rgba(255,99,71,0.08) 20px); }
        .toast-container { position:fixed; top:1rem; right:1rem; z-index:1055; }
        @media(max-width:992px){ .sidebar{display:none;} .mobile-nav{display:flex!important; gap:8px;} .calendar-grid{grid-template-columns:60px repeat(7,minmax(180px,1fr)); overflow-x:auto;} .day-slots{height:960px;} }
    </style>
</head>
<body>
<div class="toast-container"></div>
<?php if($page!=='login'): ?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-lg-2 sidebar p-0">
            <div class="p-3 border-bottom border-light d-flex align-items-center gap-2">
                <i class="fa-solid fa-school fa-lg"></i>
                <div><div class="fw-bold">Kurs Takip Sistemi</div><small><?= e(current_user()['full_name']) ?></small></div>
            </div>
            <ul class="list-unstyled mb-0">
                <li><a class="<?= $page==='calendar'?'active':'' ?>" href="?page=calendar"><i class="fa-solid fa-calendar me-2"></i>Takvim</a></li>
                <li><a class="<?= $page==='courses'?'active':'' ?>" href="?page=courses"><i class="fa-solid fa-book me-2"></i>Kurslar</a></li>
                <li><a class="<?= $page==='students'?'active':'' ?>" href="?page=students"><i class="fa-solid fa-user-graduate me-2"></i>Öğrenciler</a></li>
                <li><a class="<?= $page==='attendance'?'active':'' ?>" href="?page=attendance"><i class="fa-solid fa-clipboard-check me-2"></i>Yoklama</a></li>
                <?php if(is_admin()): ?>
                <li><a class="<?= $page==='teachers'?'active':'' ?>" href="?page=teachers"><i class="fa-solid fa-chalkboard-teacher me-2"></i>Öğretmenler</a></li>
                <li><a class="<?= $page==='locations'?'active':'' ?>" href="?page=locations"><i class="fa-solid fa-building me-2"></i>Binalar & Sınıflar</a></li>
                <li><a class="<?= $page==='holidays'?'active':'' ?>" href="?page=holidays"><i class="fa-solid fa-umbrella-beach me-2"></i>Tatiller</a></li>
                <li><a class="<?= $page==='reports'?'active':'' ?>" href="?page=reports"><i class="fa-solid fa-chart-bar me-2"></i>Raporlar</a></li>
                <li><a class="<?= $page==='users'?'active':'' ?>" href="?page=users"><i class="fa-solid fa-users-cog me-2"></i>Kullanıcılar</a></li>
                <li><a class="<?= $page==='settings'?'active':'' ?>" href="?page=settings"><i class="fa-solid fa-cog me-2"></i>Ayarlar</a></li>
                <?php endif; ?>
                <li><a href="?action=logout"><i class="fa-solid fa-right-from-bracket me-2"></i>Çıkış</a></li>
            </ul>
        </nav>
        <main class="col-lg-10 p-3">
            <div class="mobile-nav mb-3" style="display:none;">
                <a href="?page=calendar" class="btn btn-primary btn-sm flex-fill">Takvim</a>
                <a href="?page=courses" class="btn btn-outline-primary btn-sm flex-fill">Kurslar</a>
                <a href="?page=students" class="btn btn-outline-primary btn-sm flex-fill">Öğrenciler</a>
                <a href="?action=logout" class="btn btn-outline-danger btn-sm flex-fill">Çıkış</a>
            </div>
            <?php if($page==='calendar'): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary btn-sm" href="?page=calendar&date=<?= date('Y-m-d', strtotime('-7 days', strtotime($weekStart))) ?>">← Önceki Hafta</a>
                        <a class="btn btn-outline-secondary btn-sm" href="?page=calendar">Bugün</a>
                        <a class="btn btn-outline-secondary btn-sm" href="?page=calendar&date=<?= date('Y-m-d', strtotime('+7 days', strtotime($weekStart))) ?>">Sonraki Hafta →</a>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="fw-semibold">Hafta: <?= date('d M', strtotime($weekStart)) ?> - <?= date('d M Y', strtotime($weekEnd)) ?></div>
                        <?php if(is_admin()): ?><button class="btn btn-primary btn-sm" onclick="openCourseModal()"><i class="fa fa-plus"></i> Yeni Kurs</button><?php endif; ?>
                    </div>
                </div>
                <div class="calendar-grid mb-4">
                    <div class="time-column">
                        <?php for($h=8;$h<=22;$h++): ?><div class="time-slot"><?= sprintf('%02d:00',$h) ?></div><?php endfor; ?>
                    </div>
                    <?php for($d=0;$d<7;$d++): $date=date('Y-m-d', strtotime("+{$d} day", strtotime($weekStart))); ?>
                        <div class="day-column">
                            <div class="day-header"><?= day_name((int)date('N', strtotime($date))) ?><br><small><?= date('d M', strtotime($date)) ?></small></div>
                            <div class="day-slots" onclick="<?php if(is_admin()): ?>openCourseModal('<?= $date ?>')<?php else: ?>''<?php endif; ?>">
                                <?php foreach($holidaysWeek as $hol): if($hol['holiday_date']===$date || ($hol['recurring_yearly'] && date('m-d', strtotime($hol['holiday_date']))===date('m-d', strtotime($date)))): ?><div class="holiday-banner" title="<?= e($hol['name']) ?>"></div><?php endif; endforeach; ?>
                                <?php foreach($coursesWeek as $cs): if($cs['session_date']===$date): $startMin=((int)date('G', strtotime($cs['start_time']))-8)*60 + (int)date('i', strtotime($cs['start_time'])); $dur=(strtotime($cs['end_time'])-strtotime($cs['start_time']))/60; $top=max(0,$startMin); $height=max(45,$dur); ?>
                                    <div class="course-block" style="top:<?= $top ?>px; height:<?= $height ?>px; background:<?= e($cs['color']?:'#3788d8') ?>;" onclick="event.stopPropagation();openCourseDetail(<?= (int)$cs['course_id'] ?>);">
                                        <strong><?= e($cs['course_name']) ?></strong><br>
                                        <small><?= substr($cs['start_time'],0,5) ?> - <?= substr($cs['end_time'],0,5) ?></small><br>
                                        <small><?= e($cs['teacher_name'] ?? 'Öğretmen') ?></small><br>
                                        <small><?= e(($cs['building_name']??'').' '.$cs['classroom_name']) ?></small>
                                    </div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php elseif($page==='courses'): ?>
                <div class="d-flex justify-content-between align-items-center mb-3"><h5>Kurslar</h5><?php if(is_admin()): ?><button class="btn btn-primary btn-sm" onclick="openCourseModal()"><i class="fa fa-plus"></i> Yeni Kurs</button><?php endif; ?></div>
                <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Adı</th><th>Öğretmen</th><th>Gün/Saat</th><th>Sınıf</th><th></th></tr></thead><tbody>
                <?php foreach($pdo->query("SELECT c.*, u.full_name AS teacher_name, cl.name AS classroom_name FROM courses c LEFT JOIN users u ON c.teacher_id=u.id LEFT JOIN classrooms cl ON c.classroom_id=cl.id ORDER BY c.start_date DESC") as $r): ?>
                    <tr><td><?= e($r['name']) ?></td><td><?= e($r['teacher_name']) ?></td><td><?= day_name((int)$r['day_of_week']).' '.substr($r['start_time'],0,5).'-'.substr($r['end_time'],0,5) ?></td><td><?= e($r['classroom_name']) ?></td><td class="text-end"><button class="btn btn-outline-secondary btn-sm" onclick="openCourseModal(null,<?= (int)$r['id'] ?>)"><i class="fa fa-pen"></i></button><?php if(is_admin()): ?><button class="btn btn-outline-danger btn-sm" onclick="deleteCourse(<?= (int)$r['id'] ?>)"><i class="fa fa-trash"></i></button><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php elseif($page==='students'): ?>
                <div class="d-flex justify-content-between align-items-center mb-3"><h5>Öğrenciler</h5><button class="btn btn-primary btn-sm" onclick="openStudentModal()"><i class="fa fa-plus"></i> Yeni Öğrenci</button></div>
                <div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Ad Soyad</th><th>Telefon</th><th>E-posta</th><th></th></tr></thead><tbody>
                <?php foreach($pdo->query("SELECT * FROM students ORDER BY created_at DESC") as $s): ?>
                    <tr><td><?= e($s['full_name']) ?></td><td><?= e($s['phone']) ?></td><td><?= e($s['email']) ?></td><td class="text-end"><button class="btn btn-outline-secondary btn-sm" onclick='openStudentModal(<?= json_encode($s) ?>)'><i class="fa fa-pen"></i></button><?php if(is_admin()): ?><button class="btn btn-outline-danger btn-sm" onclick="deleteStudent(<?= (int)$s['id'] ?>)"><i class="fa fa-trash"></i></button><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php elseif($page==='attendance'): ?>
                <div class="d-flex align-items-center justify-content-between mb-3"><h5>Yoklama</h5></div>
                <?php $upcoming=$pdo->query("SELECT cs.*, c.name AS course_name FROM course_sessions cs JOIN courses c ON cs.course_id=c.id WHERE cs.session_date>=CURDATE() ORDER BY cs.session_date, cs.start_time LIMIT 50")->fetchAll(); ?>
                <div class="row g-3">
                <?php foreach($upcoming as $cs): ?>
                    <div class="col-md-6"><div class="card shadow-sm"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="fw-bold"><?= e($cs['course_name']) ?></div><div class="text-muted small"><?= e($cs['session_date'].' '.$cs['start_time'].'-'.$cs['end_time']) ?></div></div><button class="btn btn-primary btn-sm" onclick="openAttendanceModal(<?= (int)$cs['id'] ?>)">Yoklama Al</button></div></div></div>
                <?php endforeach; ?>
                </div>
            <?php elseif($page==='teachers'): ?>
                <div class="d-flex justify-content-between align-items-center mb-3"><h5>Öğretmenler</h5><button class="btn btn-primary btn-sm" onclick="openTeacherModal()"><i class="fa fa-plus"></i> Yeni Öğretmen</button></div>
                <div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Ad Soyad</th><th>Kullanıcı</th><th></th></tr></thead><tbody><?php foreach($pdo->query("SELECT * FROM users WHERE role='teacher'") as $t): ?><tr><td><?= e($t['full_name']) ?></td><td><?= e($t['username']) ?></td><td class="text-end"><button class="btn btn-outline-secondary btn-sm" onclick='openTeacherModal(<?= json_encode($t) ?>)'><i class="fa fa-pen"></i></button></td></tr><?php endforeach; ?></tbody></table></div>
            <?php elseif($page==='locations'): ?>
                <div class="d-flex justify-content-between align-items-center mb-3"><h5>Binalar ve Sınıflar</h5><div class="d-flex gap-2"><button class="btn btn-primary btn-sm" onclick="openBuildingModal()"><i class="fa fa-plus"></i> Yeni Bina</button><button class="btn btn-outline-primary btn-sm" onclick="openClassroomModal()"><i class="fa fa-door-open"></i> Yeni Sınıf</button></div></div><div class="row g-3"><div class="col-md-6"><div class="card h-100"><div class="card-header">Binalar</div><div class="card-body"><ul class="list-group"><?php foreach($buildings as $b): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($b['name']) ?></span><small class="text-muted">ID:<?= (int)$b['id'] ?></small></li><?php endforeach; ?></ul></div></div></div><div class="col-md-6"><div class="card h-100"><div class="card-header">Sınıflar</div><div class="card-body"><ul class="list-group"><?php foreach($classrooms as $c): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($c['name']) ?></span><small class="text-muted">Bina <?= (int)$c['building_id'] ?> · Kapasite <?= (int)$c['capacity'] ?></small></li><?php endforeach; ?></ul></div></div></div></div>
            <?php elseif($page==='holidays'): ?>
                <h5>Tatiller</h5><div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Ad</th><th>Tarih</th><th>Tekrar</th></tr></thead><tbody><?php foreach($pdo->query("SELECT * FROM holidays ORDER BY holiday_date") as $h): ?><tr><td><?= e($h['name']) ?></td><td><?= e($h['holiday_date']) ?></td><td><?= $h['recurring_yearly']?'Evet':'Hayır' ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php elseif($page==='reports'): ?>
                <div class="card"><div class="card-body"><h5>Raporlar</h5><p class="text-muted">Tarih aralığı seçerek kurs oturumlarını Excel olarak indirebilirsiniz.</p><div class="d-flex gap-2 mb-3"><input type="date" id="rStart" class="form-control" value="<?= e($weekStart) ?>"><input type="date" id="rEnd" class="form-control" value="<?= e($weekEnd) ?>"><button class="btn btn-success" onclick="exportExcel()"><i class="fa fa-file-excel"></i> Excel İndir</button><button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa fa-print"></i> Yazdır</button></div></div></div>
            <?php elseif($page==='users'): ?>
                <h5>Kullanıcılar</h5><div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Ad Soyad</th><th>Kullanıcı</th><th>Rol</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?= e($u['full_name']) ?></td><td><?= e($u['username']) ?></td><td><?= e($u['role']) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php elseif($page==='settings'): ?>
                <div class="card"><div class="card-body"><h5>Ayarlar</h5><form id="settingsForm"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label">Site Başlığı</label><input class="form-control" name="settings[site_title]" value="<?= e($pdo->query("SELECT setting_value FROM settings WHERE setting_key='site_title'")->fetchColumn() ?: 'Çeşme Belediyesi Kültür Müdürlüğü') ?>"></div><div class="col-md-6"><label class="form-label">Alt Başlık</label><input class="form-control" name="settings[site_subtitle]" value="<?= e($pdo->query("SELECT setting_value FROM settings WHERE setting_key='site_subtitle'")->fetchColumn() ?: 'Kurs Takip Sistemi') ?>"></div><div class="col-md-4"><label class="form-label">Çalışma Başlangıç</label><input type="time" class="form-control" name="settings[working_hours_start]" value="<?= e($pdo->query("SELECT setting_value FROM settings WHERE setting_key='working_hours_start'")->fetchColumn() ?: '08:00') ?>"></div><div class="col-md-4"><label class="form-label">Çalışma Bitiş</label><input type="time" class="form-control" name="settings[working_hours_end]" value="<?= e($pdo->query("SELECT setting_value FROM settings WHERE setting_key='working_hours_end'")->fetchColumn() ?: '22:00') ?>"></div><div class="col-md-4"><label class="form-label">Zaman Dilimi (dk)</label><input type="number" class="form-control" name="settings[time_slot_duration]" value="<?= e($pdo->query("SELECT setting_value FROM settings WHERE setting_key='time_slot_duration'")->fetchColumn() ?: '30') ?>"></div></div><div class="text-end mt-3"><button type="button" class="btn btn-primary" onclick="submitSettings()">Kaydet</button></div></form></div></div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php else: ?>
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-center mb-3"><i class="fa-solid fa-school fa-2x text-primary"></i><h5 class="mt-2">Kurs Takip Sistemi</h5><p class="text-muted small">Çeşme Belediyesi Kültür Müdürlüğü</p></div><form id="loginForm"><input type="hidden" name="action" value="login"><div class="mb-3"><label class="form-label">Kullanıcı Adı</label><input class="form-control" name="username" required></div><div class="mb-3"><label class="form-label">Şifre</label><input type="password" class="form-control" name="password" required></div><button class="btn btn-primary w-100" type="submit">Giriş Yap</button></form></div></div></div></div></div>
<?php endif; ?>

<div class="modal fade" id="courseModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Kurs Formu</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="courseForm"><input type="hidden" name="action" value="save_course"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="course_id" id="course_id"><div class="row g-3"><div class="col-md-6"><label class="form-label">Kurs Adı</label><input class="form-control" name="name" id="course_name" required></div><div class="col-md-6"><label class="form-label">Öğretmen</label><select class="form-select" name="teacher_id" id="course_teacher"><option value="">Seçiniz</option><?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['full_name']) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label">Açıklama</label><textarea class="form-control" name="description" id="course_description"></textarea></div><div class="col-md-4"><label class="form-label">Gün</label><select class="form-select" name="day_of_week" id="course_day"><?php for($i=1;$i<=7;$i++): ?><option value="<?= $i ?>"><?= day_name($i) ?></option><?php endfor; ?></select></div><div class="col-md-4"><label class="form-label">Başlangıç</label><input type="time" class="form-control" name="start_time" id="course_start" required></div><div class="col-md-4"><label class="form-label">Bitiş</label><input type="time" class="form-control" name="end_time" id="course_end" required></div><div class="col-md-6"><label class="form-label">Kurs Başlangıç</label><input type="date" class="form-control" name="start_date" id="course_start_date" required></div><div class="col-md-6"><label class="form-label">Kurs Bitiş</label><input type="date" class="form-control" name="end_date" id="course_end_date" required></div><div class="col-md-6"><label class="form-label">Bina</label><select class="form-select" id="course_building" onchange="filterClassrooms(this.value)"><option value="">Seçiniz</option><?php foreach($buildings as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Sınıf</label><select class="form-select" name="classroom_id" id="course_classroom" required><option value="">Seçiniz</option><?php foreach($classrooms as $c): ?><option data-building="<?= $c['building_id'] ?>" value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Renk</label><input type="color" class="form-control form-control-color" name="color" id="course_color" value="#3788d8"></div></div></form><div class="alert alert-warning mt-3 d-none" id="conflictAlert"></div></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button><button class="btn btn-primary" onclick="saveCourse()">Kaydet</button></div></div></div></div>

<div class="modal fade" id="studentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Öğrenci Formu</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="studentForm"><input type="hidden" name="action" value="save_student"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="student_id" id="student_id"><div class="mb-2"><label class="form-label">Ad Soyad</label><input class="form-control" name="full_name" id="student_full_name" required></div><div class="mb-2"><label class="form-label">T.C. No</label><input class="form-control" name="tc_no" id="student_tc" maxlength="11"></div><div class="mb-2"><label class="form-label">Doğum Tarihi</label><input type="date" class="form-control" name="birth_date" id="student_birth"></div><div class="mb-2"><label class="form-label">Telefon</label><input class="form-control" name="phone" id="student_phone"></div><div class="mb-2"><label class="form-label">E-posta</label><input class="form-control" name="email" id="student_email"></div><div class="mb-2"><label class="form-label">Adres</label><textarea class="form-control" name="address" id="student_address"></textarea></div><div class="mb-2"><label class="form-label">Veli Adı</label><input class="form-control" name="guardian_name" id="student_guardian"></div><div class="mb-2"><label class="form-label">Veli Telefonu</label><input class="form-control" name="guardian_phone" id="student_guardian_phone"></div></form></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button><button class="btn btn-primary" onclick="saveStudent()">Kaydet</button></div></div></div></div>

<div class="modal fade" id="attendanceModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Yoklama</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="attendanceBody">Yükleniyor...</div></div></div></div>
<div class="modal fade" id="courseDetailModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Kurs Detayı</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="courseDetailBody">Yükleniyor...</div></div></div></div>

<?php if(is_admin()): ?>
<div class="modal fade" id="buildingModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Bina Ekle</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="buildingForm"><input type="hidden" name="action" value="save_building"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><div class="mb-2"><label class="form-label">Bina Adı</label><input class="form-control" name="name" required></div><div class="mb-2"><label class="form-label">Adres</label><textarea class="form-control" name="address"></textarea></div><div class="mb-2"><label class="form-label">Açıklama</label><textarea class="form-control" name="description"></textarea></div></form></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button><button class="btn btn-primary" onclick="saveBuilding()">Kaydet</button></div></div></div></div>

<div class="modal fade" id="classroomModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Sınıf Ekle</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="classroomForm"><input type="hidden" name="action" value="save_classroom"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><div class="mb-2"><label class="form-label">Bina</label><select class="form-select" name="building_id" required><option value="">Seçiniz</option><?php foreach($buildings as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div><div class="mb-2"><label class="form-label">Sınıf Adı</label><input class="form-control" name="name" required></div><div class="mb-2"><label class="form-label">Kapasite</label><input type="number" class="form-control" name="capacity" value="0" min="0"></div><div class="mb-2"><label class="form-label">Açıklama</label><textarea class="form-control" name="description"></textarea></div></form></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button><button class="btn btn-primary" onclick="saveClassroom()">Kaydet</button></div></div></div></div>

<div class="modal fade" id="teacherModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Öğretmen Formu</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="teacherForm"><input type="hidden" name="action" value="save_teacher"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="teacher_id" id="teacher_id"><div class="mb-2"><label class="form-label">Kullanıcı Adı</label><input class="form-control" name="username" id="teacher_username" required></div><div class="mb-2"><label class="form-label">Şifre <small class="text-muted">(Düzenlemede boş bırakabilirsiniz)</small></label><input type="password" class="form-control" name="password" id="teacher_password"></div><div class="mb-2"><label class="form-label">Ad Soyad</label><input class="form-control" name="full_name" id="teacher_full_name" required></div><div class="mb-2"><label class="form-label">E-posta</label><input class="form-control" name="email" id="teacher_email"></div><div class="mb-2"><label class="form-label">Telefon</label><input class="form-control" name="phone" id="teacher_phone"></div></form></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button><button class="btn btn-primary" onclick="saveTeacher()">Kaydet</button></div></div></div></div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const courseModal=new bootstrap.Modal(document.getElementById('courseModal')); const studentModal=new bootstrap.Modal(document.getElementById('studentModal')); const attendanceModal=new bootstrap.Modal(document.getElementById('attendanceModal')); const courseDetailModal=new bootstrap.Modal(document.getElementById('courseDetailModal'));
function showToast(message,type='success'){ const cont=document.querySelector('.toast-container'); const div=document.createElement('div'); div.className='toast align-items-center text-bg-'+(type==='error'?'danger':'primary')+' border-0'; div.innerHTML=`<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`; cont.appendChild(div); const t=new bootstrap.Toast(div,{delay:3000}); t.show(); div.addEventListener('hidden.bs.toast',()=>div.remove()); }
function ajax(action,data,cb){ const params=new URLSearchParams(data); params.append('action',action); fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()}).then(r=>r.json()).then(cb).catch(()=>showToast('Hata','error')); }
function openCourseModal(date=null,id=null){ document.getElementById('courseForm').reset(); document.getElementById('conflictAlert').classList.add('d-none'); document.getElementById('course_id').value=''; if(date){ document.getElementById('course_start_date').value=date; document.getElementById('course_end_date').value=date; } if(id){ fetch('?load='+id).then(r=>r.json()).then(d=>{ document.getElementById('course_id').value=d.id; document.getElementById('course_name').value=d.name; document.getElementById('course_teacher').value=d.teacher_id; document.getElementById('course_description').value=d.description; document.getElementById('course_day').value=d.day_of_week; document.getElementById('course_start').value=d.start_time; document.getElementById('course_end').value=d.end_time; document.getElementById('course_start_date').value=d.start_date; document.getElementById('course_end_date').value=d.end_date; document.getElementById('course_classroom').value=d.classroom_id; document.getElementById('course_color').value=d.color; }); }
    courseModal.show(); }
function saveCourse(){ const form=document.getElementById('courseForm'); const data=new FormData(form); fetch('',{method:'POST',body:data}).then(r=>r.json()).then(res=>{ if(res.status==='success'){ showToast(res.message); courseModal.hide(); setTimeout(()=>location.reload(),600);} else if(res.status==='conflict'){ const al=document.getElementById('conflictAlert'); al.textContent=res.message; al.classList.remove('d-none'); } else showToast(res.message,'error'); }).catch(()=>showToast('Kayıt hatası','error')); }
function deleteCourse(id){ if(!confirm('Kurs silinsin mi?')) return; ajax('delete_course',{id:id,csrf_token:'<?= csrf_token() ?>'},res=>{ if(res.status==='success'){ showToast('Silindi'); location.reload(); } else showToast(res.message,'error'); }); }
function filterClassrooms(bid){ document.querySelectorAll('#course_classroom option').forEach(o=>{ if(!o.value) return o.hidden=false; o.hidden = bid && o.dataset.building!==bid; }); }
function openStudentModal(data=null){ document.getElementById('studentForm').reset(); document.getElementById('student_id').value=''; if(data){ document.getElementById('student_id').value=data.id; document.getElementById('student_full_name').value=data.full_name; document.getElementById('student_tc').value=data.tc_no; document.getElementById('student_birth').value=data.birth_date; document.getElementById('student_phone').value=data.phone; document.getElementById('student_email').value=data.email; document.getElementById('student_address').value=data.address; document.getElementById('student_guardian').value=data.guardian_name; document.getElementById('student_guardian_phone').value=data.guardian_phone; } studentModal.show(); }
function saveStudent(){ const form=new FormData(document.getElementById('studentForm')); fetch('',{method:'POST',body:form}).then(r=>r.json()).then(res=>{ if(res.status==='success'){ showToast(res.message); studentModal.hide(); setTimeout(()=>location.reload(),600);} else showToast(res.message,'error'); }); }
function deleteStudent(id){ if(!confirm('Öğrenci silinsin mi?')) return; ajax('delete_student',{id:id,csrf_token:'<?= csrf_token() ?>'},res=>{ if(res.status==='success'){ location.reload(); } else showToast(res.message,'error'); }); }
function openAttendanceModal(sessionId){ const body=document.getElementById('attendanceBody'); body.innerHTML='Yükleniyor...'; fetch('?attendance='+sessionId).then(r=>r.json()).then(data=>{ let html=`<form id="attendanceForm"><input type="hidden" name="action" value="save_attendance"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="session_id" value="${sessionId}">`; data.students.forEach(st=>{ const status=data.existing[st.id]||''; html+=`<div class="border rounded p-2 mb-2"><div class="fw-semibold">${st.full_name}</div><div class="d-flex gap-2 mt-1"><label class="form-check"><input class="form-check-input" type="radio" name="attendance[${st.id}]" value="present" ${status==='present'?'checked':''}> <span class="text-success">Geldi</span></label><label class="form-check"><input class="form-check-input" type="radio" name="attendance[${st.id}]" value="absent" ${status==='absent'?'checked':''}> <span class="text-danger">Gelmedi</span></label><label class="form-check"><input class="form-check-input" type="radio" name="attendance[${st.id}]" value="excused" ${status==='excused'?'checked':''}> <span class="text-warning">İzinli</span></label></div><textarea class="form-control mt-2" name="notes[${st.id}]" placeholder="Not">${data.notes[st.id]||''}</textarea></div>`; }); html+=`<div class="text-end"><button class="btn btn-primary" type="button" onclick="saveAttendance()">Kaydet</button></div></form>`; body.innerHTML=html; }); attendanceModal.show(); }
function saveAttendance(){ const form=new FormData(document.getElementById('attendanceForm')); fetch('',{method:'POST',body:form}).then(r=>r.json()).then(res=>{ if(res.status==='success'){ showToast(res.message); attendanceModal.hide(); } else showToast(res.message,'error'); }); }
function openCourseDetail(id){ fetch('?detail='+id).then(r=>r.json()).then(d=>{ document.getElementById('courseDetailBody').innerHTML=`<div class="mb-2"><strong>${d.name}</strong></div><div><i class="fa fa-user"></i> ${d.teacher_name||''}</div><div><i class="fa fa-clock"></i> ${d.day}</div><div><i class="fa fa-location-dot"></i> ${d.building_name||''} / ${d.classroom_name||''}</div>`; courseDetailModal.show(); }); }
function submitSettings(){ const form=document.getElementById('settingsForm'); fetch('',{method:'POST',body:new FormData(form)}).then(r=>r.json()).then(res=>{ if(res.status==='success') showToast(res.message); else showToast(res.message,'error'); }); }
function exportExcel(){ const s=document.getElementById('rStart').value; const e=document.getElementById('rEnd').value; window.location='?export=excel&start='+s+'&end='+e; }
const lf=document.getElementById('loginForm'); if(lf){ lf.addEventListener('submit',function(ev){ ev.preventDefault(); const fd=new FormData(lf); fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{ if(res.status==='success') window.location='?page=calendar'; else showToast(res.message,'error'); }); }); }
function openBuildingModal(){ document.getElementById('buildingForm').reset(); new bootstrap.Modal(document.getElementById('buildingModal')).show(); }
function saveBuilding(){ const form=new FormData(document.getElementById('buildingForm')); fetch('',{method:'POST',body:form}).then(r=>r.json()).then(res=>{ if(res.status==='success'){ showToast(res.message); location.reload(); } else showToast(res.message,'error'); }); }
function openClassroomModal(){ document.getElementById('classroomForm').reset(); new bootstrap.Modal(document.getElementById('classroomModal')).show(); }
function saveClassroom(){ const form=new FormData(document.getElementById('classroomForm')); fetch('',{method:'POST',body:form}).then(r=>r.json()).then(res=>{ if(res.status==='success'){ showToast(res.message); location.reload(); } else showToast(res.message,'error'); }); }
function openTeacherModal(data=null){ document.getElementById('teacherForm').reset(); document.getElementById('teacher_id').value=''; if(data){ document.getElementById('teacher_id').value=data.id; document.getElementById('teacher_username').value=data.username; document.getElementById('teacher_full_name').value=data.full_name; document.getElementById('teacher_email').value=data.email; document.getElementById('teacher_phone').value=data.phone; } new bootstrap.Modal(document.getElementById('teacherModal')).show(); }
function saveTeacher(){ const form=new FormData(document.getElementById('teacherForm')); fetch('',{method:'POST',body:form}).then(r=>r.json()).then(res=>{ if(res.status==='success'){ showToast(res.message); location.reload(); } else showToast(res.message,'error'); }); }
</script>
</body>
</html>
